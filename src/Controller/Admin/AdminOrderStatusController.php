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
    public function updateStatus(Order $order, Request $req, EntityManagerInterface $em): Response
    {
        $next = $req->request->get('next_status');

        $valid = [
            'NEW'        => 'PREPARING',
            'PREPARING'  => 'SHIPPING',
            'SHIPPING'   => 'PAID',  // sau khi user bấm đã nhận hàng
        ];

        if (!isset($valid[$order->getStatus()])) {
            $this->addFlash('shop.error', 'This order cannot be updated further.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        if ($next !== $valid[$order->getStatus()]) {
            $this->addFlash('shop.error', 'Invalid next status.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $order->setStatus($next);
        $em->flush();

        $this->addFlash('shop.success', "Order status updated to: $next");
        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }
}
