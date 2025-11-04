<?php
// src/Controller/OrderController.php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\MomoService;

#[Route('/orders')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    #[Route('', name: 'app_orders_index')]
    public function index(OrderRepository $repo): Response
    {
        $orders = $repo->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']);
        return $this->render('orders/index.html.twig', ['orders' => $orders]);
    }

    #[Route('/{id}', name: 'app_orders_show', requirements: ['id' => '\d+'])]
    public function show(Order $order): Response
    {
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        return $this->render('orders/show.html.twig', ['order' => $order]);
    }

    #[Route('/{id}/resume', name: 'app_orders_resume', methods: ['GET'])]
    public function resume(Order $order, MomoService $momo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($order->getStatus(), ['NEW', 'PENDING'], true)) {
            $this->addFlash('shop.error', 'Đơn không thể thanh toán.');
            return $this->redirectToRoute('app_orders_index');
        }

        $returnUrl = $this->generateUrl('momo_return', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $ipnUrl    = $this->generateUrl('momo_ipn',    [], UrlGeneratorInterface::ABSOLUTE_URL);

        $pay = $momo->createPayment($order, $returnUrl, $ipnUrl);

        if (($pay['resultCode'] ?? -1) === 0 && !empty($pay['payUrl'])) {
            $order->setStatus('PENDING');
            $em->flush();
            return $this->redirect($pay['payUrl']);
        }

        $this->addFlash('shop.error', 'MoMo error: ' . ($pay['message'] ?? 'unknown'));
        return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_orders_cancel', methods: ['POST'])]
    public function cancel(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel_order_'.$order->getId(), $request->request->get('_token'))) {
            $this->addFlash('shop.error', 'Token không hợp lệ.');
            return $this->redirectToRoute('app_orders_index');
        }

        if ($order->getStatus() === 'PAID') {
            $this->addFlash('shop.error', 'Đơn đã thanh toán, không thể huỷ.');
        } elseif ($order->getStatus() !== 'CANCELED') {
            $order->setStatus('CANCELED')->setPaidAt(null);
            $em->flush();
            $this->addFlash('shop.success', 'Đã huỷ đơn #'.$order->getId());
        }

        return $this->redirectToRoute('app_orders_index');
    }

    #[Route('/{id}/request-refund', name: 'app_orders_request_refund', methods: ['POST'])]
    public function requestRefund(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('request_refund_'.$order->getId(), $request->request->get('_token'))) {
            $this->addFlash('shop.error', 'Token không hợp lệ.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        if ($order->getStatus() !== 'PAID') {
            $this->addFlash('shop.error', 'Không thể yêu cầu hoàn tiền cho đơn hàng này.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        $order->setStatus('REFUND_REQUESTED');
        $em->flush();

        $this->addFlash('shop.success', 'Đã gửi yêu cầu hoàn tiền cho đơn hàng #'.$order->getId().'. Shop sẽ sớm liên hệ và xác nhận với bạn.');
        return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
    }
}
