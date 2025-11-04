<?php
// src/Controller/PaymentMomoController.php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Service\MomoService;
use App\Service\CartService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaymentMomoController extends AbstractController
{
    public function __construct(
        private MomoService $momo,
        private EntityManagerInterface $em,
        private CartService $cart
    ) {}

    #[Route('/payment/momo/ipn', name: 'momo_ipn', methods: ['POST'])]
    public function ipn(Request $req, OrderRepository $orders): Response
    {
        $data = json_decode($req->getContent(), true) ?? [];
        if (!$this->momo->verifyIpnSignature($data)) {
            return new JsonResponse(['resultCode' => 1, 'message' => 'Invalid signature'], 400);
        }

        $extra = json_decode(base64_decode($data['extraData'] ?? ''), true) ?? [];
        $internalId = (int)($extra['internalOrderId'] ?? 0);
        $order = $internalId ? $orders->find($internalId) : null;
        if (!$order) {
            return new JsonResponse(['resultCode' => 2, 'message' => 'Order not found'], 404);
        }

        if ((int)$data['resultCode'] === 0) {
            if ($order->getStatus() !== 'PAID') {
                foreach ($order->getItems() as $oi) {
                    $prod = $oi->getProduct();
                    if ($prod) {
                        $prod->setQuantity(max(0, (int)$prod->getQuantity() - (int)$oi->getQuantity()));
                    }
                }
                $order->setStatus('PAID');
                $order->setPaidAt(new DateTimeImmutable());
                $order->setPaymentMethod('MOMO');

                if (!empty($data['transId']) && !$order->getPaymentTxnId()) {
                    $order->setPaymentTxnId((string)$data['transId']);
                }

                // Khởi tạo số tiền có thể hoàn
                if ($order->getRefundableRemaining() === 0) {
                    $order->setRefundableRemaining((int) round((float) $order->getTotal()));
                }
            }
        } else {
            if ($order->getStatus() !== 'PAID') {
                $order->setStatus('FAILED');
            }
        }

        $this->em->flush();

        return new JsonResponse(['resultCode' => 0, 'message' => 'Confirm Success']);
    }

    #[Route('/payment/momo/return', name: 'momo_return', methods: ['GET'])]
    public function returnPage(Request $req, OrderRepository $orders): Response
    {
        $data = $req->query->all();
        if (!$this->momo->verifyIpnSignature($data)) {
            $this->addFlash('shop.error', 'MoMo: chữ ký không hợp lệ.');
            return $this->redirectToRoute('app_orders_index');
        }

        $extra = json_decode(base64_decode($data['extraData'] ?? ''), true) ?? [];
        $internalId = (int)($extra['internalOrderId'] ?? 0);
        $order = $internalId ? $orders->find($internalId) : null;
        if (!$order) {
            $this->addFlash('shop.error', 'Không tìm thấy đơn hàng để cập nhật.');
            return $this->redirectToRoute('app_orders_index');
        }

        $code = (int) ($data['resultCode'] ?? -999);
        $msg  = (string) ($data['message'] ?? '');

        if ($code === 0) {
            if ($order->getStatus() !== 'PAID') {
                foreach ($order->getItems() as $oi) {
                    $prod = $oi->getProduct();
                    if ($prod) {
                        $prod->setQuantity(max(0, (int)$prod->getQuantity() - (int)$oi->getQuantity()));
                    }
                }
                $order->setStatus('PAID');
                $order->setPaidAt(new DateTimeImmutable());
                $order->setPaymentMethod('MOMO');

                if (!empty($data['transId']) && !$order->getPaymentTxnId()) {
                    $order->setPaymentTxnId((string)$data['transId']);
                }

                if ($order->getRefundableRemaining() === 0) {
                    $order->setRefundableRemaining((int) round((float) $order->getTotal()));
                }

                $this->em->flush();
            }

            // Xóa khỏi giỏ
            $ids = [];
            foreach ($order->getItems() as $oi) {
                $pid = $oi->getProduct()?->getId();
                if ($pid) { $ids[] = $pid; }
            }
            if ($ids) {
                if (method_exists($this->cart, 'removeMany')) {
                    $this->cart->removeMany($ids);
                } else {
                    foreach ($ids as $pid) { $this->cart->remove($pid); }
                }
            }

            $this->addFlash('shop.success', 'Thanh toán thành công.');
        } elseif ($code === 1006 || stripos($msg, 'cancel') !== false) {
            if ($order->getStatus() !== 'PAID') {
                $order->setStatus('CANCELED');
                $order->setPaidAt(null);
                $this->em->flush();
            }
            $this->addFlash('shop.info', 'Bạn đã hủy thanh toán.');
        } else {
            if ($order->getStatus() !== 'PAID') {
                $order->setStatus('FAILED');
                $order->setPaidAt(null);
                $this->em->flush();
            }
            $this->addFlash('shop.error', 'Thanh toán thất bại: '.$msg);
        }

        return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
    }

    #[Route('/payment/momo/refund-ipn', name: 'momo_refund_ipn', methods: ['POST'])]
    public function refundIpn(Request $req, OrderRepository $orders): Response
    {
        $data = json_decode($req->getContent(), true) ?? [];

        if (!$this->momo->verifyRefundIpnSignature($data)) {
            // để MoMo retry
            return new Response('', 400);
        }

        $transId = $data['transId'] ?? null;
        if (!$transId) {
            return new Response('', 400);
        }

        $order = $orders->findOneBy(['paymentTxnId' => (string)$transId]);
        if (!$order) {
            return new Response('', 404);
        }

        // Chỉ cập nhật nếu đang PROCESSING
        if ($order->getStatus() !== 'REFUND_PROCESSING') {
            return new Response('', 204);
        }

        $resultCode = (int) ($data['resultCode'] ?? -1);
        if ($resultCode === 0) {
            $amount = isset($data['amount']) ? (int)$data['amount'] : 0;
            if ($amount > 0) { $order->addRefunded($amount); }
            $order->setStatus('REFUNDED');
            $order->setLastRefundRequestId(null);
            $order->setLastRefundOrderId(null);
        } else {
            $order->setStatus('REFUND_FAILED');
        }

        $this->em->flush();

        // chuẩn webhook: 204 No Content
        return new Response('', 204);
    }
}
