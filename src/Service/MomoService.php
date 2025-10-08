<?php
namespace App\Service;

use App\Entity\Order;

class MomoService
{
    public function __construct(
        private string $partnerCode,
        private string $accessKey,
        private string $secretKey,
        private string $endpoint // ví dụ: https://test-payment.momo.vn/v2/gateway/api/create
    ) {}

    public function createPayment(Order $order, string $redirectUrl, string $ipnUrl): array
    {
        // Lưu ý: total của Order đang là VND -> ép về int và tối thiểu 2.000 VND
        $amount = (int) round((float) $order->getTotal());
        $amount = max(2000, $amount);

        // orderId & requestId phải duy nhất
        $orderId   = sprintf('PC-%d-%s', $order->getId(), (string) microtime(true));
        $requestId = 'req-'.bin2hex(random_bytes(8));

        // orderInfo chỉ ASCII để tránh sai chữ ký
        $orderInfo = $this->ascii('Thanh toan don hang #'.$order->getId());

        $requestType = 'captureWallet';

        // Extra data lưu id nội bộ để đối soát ở return/IPN
        $extraData = base64_encode(json_encode([
            'internalOrderId' => $order->getId(),
        ], JSON_UNESCAPED_UNICODE));

        // Chuỗi ký HMAC (đúng thứ tự tham số MoMo yêu cầu)
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
            'amount'      => (string) $amount,
            'orderId'     => $orderId,
            'orderInfo'   => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl'      => $ipnUrl,
            'lang'        => 'vi',
            'extraData'   => $extraData,
            'requestType' => $requestType,
            // thời gian hết hạn QR (giây) – tránh đợi quá lâu sinh lỗi 9000
            'orderExpire' => 600,
            'signature'   => $signature,
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST   => 'POST',
            CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 45,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['resultCode' => -1, 'message' => 'cURL error: '.$err];
        }
        curl_close($ch);

        $data = json_decode($res, true);
        return is_array($data) ? $data : ['resultCode' => -1, 'message' => 'Invalid JSON', 'raw' => $res];
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

    /** Loại bỏ ký tự non-ASCII để an toàn chữ ký */
    private function ascii(string $s): string
    {
        // iconv có thể không có trong một số môi trường; dùng preg_replace fallback
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        return preg_replace('/[^\x20-\x7E]/', '', $s) ?? $s;
    }
}
