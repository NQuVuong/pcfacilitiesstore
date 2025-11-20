<?php
// src/Service/MomoService.php
namespace App\Service;

use App\Entity\Order;

class MomoService
{
    private string $refundEndpoint;

    public function __construct(
        private string $partnerCode,
        private string $accessKey,
        private string $secretKey,
        private string $endpoint,           // /v2/gateway/api/create
        string $momoRefundEndpoint          // /v2/gateway/api/refund
    ) {
        $this->refundEndpoint = $momoRefundEndpoint;
    }

    public function createPayment(Order $order, string $redirectUrl, string $ipnUrl): array
    {
        $amount = (int) round((float) $order->getTotal());
        $amount = max(2000, $amount);

        $orderId   = sprintf('PC-%d-%s', $order->getId(), (string) microtime(true));
        $requestId = 'req-'.bin2hex(random_bytes(8));

        $order->setMomoOrderId($orderId);

        $orderInfo = $this->ascii('Order payment #'.$order->getId());
        $requestType = 'captureWallet';
        $extraData = base64_encode(json_encode([
            'internalOrderId' => $order->getId(),
        ], JSON_UNESCAPED_UNICODE));

        $rawHash = "accessKey={$this->accessKey}"
                 . "&amount={$amount}"
                 . "&extraData={$extraData}"
                 . "&ipnUrl={$ipnUrl}"
                 . "&orderId={$orderId}"
                 . "&orderInfo={$orderInfo}"
                 . "&partnerCode={$this->partnerCode}"
                 . "&redirectUrl={$redirectUrl}"
                 . "&requestId={$requestId}"
                 . "&requestType={$requestType}";

        $signature = hash_hmac('sha256', $rawHash, $this->secretKey);
        $payload = [
            'partnerCode' => $this->partnerCode, 'partnerName' => 'PC Store',
            'storeId'     => 'PC-Store', 'requestId'   => $requestId,
            'amount'      => (string) $amount, 'orderId'     => $orderId,
            'orderInfo'   => $orderInfo, 'redirectUrl' => $redirectUrl,
            'ipnUrl'      => $ipnUrl, 'lang'        => 'vi',
            'extraData'   => $extraData, 'requestType' => $requestType,
            'orderExpire' => 600, 'signature'   => $signature,
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45,
        ]);
        $res = curl_exec($ch);
        if ($res === false) { $err = curl_error($ch); curl_close($ch); return ['resultCode' => -1, 'message' => 'cURL error: '.$err]; }
        curl_close($ch);
        $data = json_decode($res, true);
        return is_array($data) ? $data : ['resultCode' => -1, 'message' => 'Invalid JSON', 'raw' => $res];
    }

    public function verifyIpnSignature(array $data): bool
    {
        $get = fn($k) => isset($data[$k]) ? (string)$data[$k] : '';
        $raw = "accessKey={$this->accessKey}"
             . "&amount=".$get('amount') . "&extraData=".$get('extraData')
             . "&message=".$get('message') . "&orderId=".$get('orderId')
             . "&orderInfo=".$get('orderInfo') . "&orderType=".$get('orderType')
             . "&partnerCode=".$get('partnerCode') . "&payType=".$get('payType')
             . "&requestId=".$get('requestId') . "&responseTime=".$get('responseTime')
             . "&resultCode=".$get('resultCode') . "&transId=".$get('transId');
        $calc = hash_hmac('sha256', $raw, $this->secretKey);
        return hash_equals($calc, (string)($data['signature'] ?? ''));
    }

    public function verifyRefundIpnSignature(array $data): bool
    {
        $get = fn($k) => isset($data[$k]) ? (string)$data[$k] : '';
        $raw = "accessKey={$this->accessKey}"
             . "&amount=".$get('amount') . "&description=".$get('description')
             . "&lastUpdated=".$get('lastUpdated') . "&message=".$get('message')
             . "&orderId=".$get('orderId') . "&partnerCode=".$get('partnerCode')
             . "&requestId=".$get('requestId') . "&resultCode=".$get('resultCode')
             . "&transId=".$get('transId');
        $calc = hash_hmac('sha256', $raw, $this->secretKey);
        return hash_equals($calc, (string)($data['signature'] ?? ''));
    }

    /**
     * Gọi Refund (idempotent nếu truyền lại requestId/orderId giữa các lần retry)
     */
    public function createRefund(
        Order $order,
        int $amount,
        string $description = 'Order refund',
        ?string $forcedRequestId = null,
        ?string $forcedRefundOrderId = null
    ): array {
        $momoTransId = $order->getPaymentTxnId();
        if (empty($momoTransId)) {
            return ['resultCode' => -1, 'message' => 'Error: Order missing MoMo transaction ID'];
        }

        $newRefundOrderId = $forcedRefundOrderId ?: ($order->getLastRefundOrderId() ?: sprintf('REFUND-%d-%s', $order->getId(), (string) microtime(true)));
        $requestId        = $forcedRequestId ?: ($order->getLastRefundRequestId() ?: 'refund-'.bin2hex(random_bytes(8)));

        $rawHash = "accessKey={$this->accessKey}"
                 . "&amount={$amount}"
                 . "&description={$description}"
                 . "&orderId={$newRefundOrderId}"
                 . "&partnerCode={$this->partnerCode}"
                 . "&requestId={$requestId}"
                 . "&transId={$momoTransId}";

        $signature = hash_hmac('sha256', $rawHash, $this->secretKey);

        $payload = [
            'partnerCode' => $this->partnerCode,
            'requestId'   => $requestId,
            'orderId'     => $newRefundOrderId,
            'amount'      => (string) $amount,
            'transId'     => (int) $momoTransId,
            'lang'        => 'en',
            'description' => $description,
            'signature'   => $signature,
        ];

        $ch = curl_init($this->refundEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 45,
        ]);
        $res = curl_exec($ch);
        if ($res === false) { $err = curl_error($ch); curl_close($ch); return ['resultCode' => -1, 'message' => 'cURL error: '.$err]; }
        curl_close($ch);
        $data = json_decode($res, true);
        return is_array($data) ? $data : ['resultCode' => -1, 'message' => 'Invalid JSON', 'raw' => $res];
    }

    private function ascii(string $s): string
    {
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        return preg_replace('/[^\x20-\x7E]/', '', $s) ?? $s;
    }
}
