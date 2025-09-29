<?php
namespace App\Service;

use App\Entity\Order;

class MomoService
{
    public function __construct(
        private string $partnerCode,
        private string $accessKey,
        private string $secretKey,
        private string $endpoint
    ) {}

    public function createPayment(Order $order, string $redirectUrl, string $ipnUrl): array
    {
        $amount = (string)(int)round((float)$order->getTotal()); // VND integer
        $orderId   = 'PC-'.$order->getId().'-'.time();
        $requestId = 'req-'.bin2hex(random_bytes(6));
        $orderInfo = 'Thanh toan don hang #'.$order->getId();
        $requestType = 'captureWallet';
        $extraData = base64_encode(json_encode(['internalOrderId' => $order->getId()], JSON_UNESCAPED_UNICODE));

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
            'partnerCode' => $this->partnerCode,
            'partnerName' => 'PC Store',
            'storeId'     => 'PC-Store',
            'requestId'   => $requestId,
            'amount'      => $amount,
            'orderId'     => $orderId,
            'orderInfo'   => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl'      => $ipnUrl,
            'lang'        => 'vi',
            'extraData'   => $extraData,
            'requestType' => $requestType,
            'signature'   => $signature,
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER=> true,
            CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT       => 30,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            return ['resultCode' => -1, 'message' => curl_error($ch)];
        }
        curl_close($ch);

        return json_decode($res, true) ?: ['resultCode' => -1, 'message' => 'Invalid JSON'];
    }

    public function verifyIpnSignature(array $data): bool
    {
        $get = fn($k) => isset($data[$k]) ? (string)$data[$k] : '';
        $raw = "accessKey={$this->accessKey}"
             . "&amount=".$get('amount')
             . "&extraData=".$get('extraData')
             . "&message=".$get('message')
             . "&orderId=".$get('orderId')
             . "&orderInfo=".$get('orderInfo')
             . "&orderType=".$get('orderType')
             . "&partnerCode=".$get('partnerCode')
             . "&payType=".$get('payType')
             . "&requestId=".$get('requestId')
             . "&responseTime=".$get('responseTime')
             . "&resultCode=".$get('resultCode')
             . "&transId=".$get('transId');

        $calc = hash_hmac('sha256', $raw, $this->secretKey);
        return hash_equals($calc, (string)($data['signature'] ?? ''));
    }
}
