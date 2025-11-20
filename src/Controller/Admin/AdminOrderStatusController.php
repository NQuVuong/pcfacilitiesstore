<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/order-status')]
#[IsGranted('ROLE_STAFF')]
class AdminOrderStatusController extends AbstractController
{
    #[Route('/{id}/update', name: 'admin_order_update_status', methods: ['POST'])]
    public function updateStatus(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('update_status_'.$order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('shop.error', 'Invalid token, cannot update status.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $next = (string) $request->request->get('next_status');
        $current = $order->getStatus();
        $method  = $order->getPaymentMethod();

        // Flow cho COD
        $codMap = [
            'NEW'       => 'PREPARING',     // chờ chuẩn bị → đang chuẩn bị
            'PREPARING' => 'SHIPPING',      // đang chuẩn bị → đang giao
            'SHIPPING'  => 'DELIVERED',     // đang giao → đã giao tới
            'PAID'      => 'PAID_CONFIRMED' // user đã báo đã trả tiền → shop xác nhận đã nhận tiền
        ];

        // Flow cho MoMo
        $momoMap = [
            'PENDING'   => 'PAID',        // dự phòng, IPN thường set
            'PAID'      => 'SHIPPING',    // đã trả tiền → đang giao
            'SHIPPING'  => 'DELIVERED',   // đang giao → đã giao tới
            'DELIVERED' => 'COMPLETED',   // đã giao tới → hoàn tất
        ];

        $map = ($method === 'COD') ? $codMap : $momoMap;

        if (!isset($map[$current])) {
            $this->addFlash('shop.error', 'This order cannot be updated further.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $expectedNext = $map[$current];

        if ($next !== $expectedNext) {
            $this->addFlash('shop.error', 'Invalid next status.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $order->setStatus($expectedNext);

        // Nếu chuyển sang trạng thái đã thanh toán/hoàn tất mà chưa có paidAt thì set luôn
        if (in_array($expectedNext, ['PAID', 'PAID_CONFIRMED', 'COMPLETED'], true) && $order->getPaidAt() === null) {
            $order->setPaidAt(new \DateTimeImmutable());
        }

        $em->flush();

        $this->addFlash('shop.success', 'Order status updated to: '.$expectedNext);
        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }
}
