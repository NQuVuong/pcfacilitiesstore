<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/refunds')]
#[IsGranted('ROLE_ADMIN')]
class AdminRefundController extends AbstractController
{
    #[Route('', name: 'admin_refunds_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $orders = $em->getRepository(Order::class)
                     ->findBy(['status' => 'REFUND_SENT_TO_ADMIN']);

        return $this->render('admin/refunds/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_refund_approve')]
    public function approve(Order $order, EntityManagerInterface $em): Response
    {
        $order->setStatus('REFUND_PROCESSING');
        $em->flush();

        // TẠM: bạn sẽ chèn gọi MomoService/hoặc refund COD sau
        // Khi xong:
        // $order->setStatus('REFUNDED');

        $this->addFlash('admin.success', 'Admin đã đồng ý hoàn tiền.');
        return $this->redirectToRoute('admin_refunds_index');
    }

    #[Route('/{id}/reject', name: 'admin_refund_reject')]
    public function reject(Order $order, EntityManagerInterface $em): Response
    {
        $order->setStatus('ADMIN_REJECTED');
        $em->flush();

        $this->addFlash('admin.error', 'Admin đã từ chối hoàn tiền.');
        return $this->redirectToRoute('admin_refunds_index');
    }
}
