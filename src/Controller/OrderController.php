<?php
// src/Controller/OrderController.php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\MomoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    #[Route('', name: 'app_orders_index')]
    public function index(OrderRepository $repo, Request $request): Response
    {
        $user = $this->getUser();

        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;

        $all = $repo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $total     = \count($all);
        $pageCount = (int) \ceil(max(1, $total) / $perPage);
        $page      = min($page, max(1, $pageCount));

        $offset = ($page - 1) * $perPage;
        $orders = \array_slice($all, $offset, $perPage);

        return $this->render('orders/index.html.twig', [
            'orders'    => $orders,
            'page'      => $page,
            'pageCount' => $pageCount,
            'total'     => $total,
        ]);
    }

    #[Route('/{id}', name: 'app_orders_show', requirements: ['id' => '\d+'])]
    public function show(Order $order): Response
    {
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $status = $order->getStatus();

        // Tính 5 phút kể từ lúc thanh toán (hoặc tạo đơn nếu paidAt null)
        $now     = new \DateTimeImmutable();
        $created = $order->getPaidAt() ?: $order->getCreatedAt();
        $diffSeconds = $now->getTimestamp() - $created->getTimestamp();

        // Chỉ cho refund khi:
        // - Đơn đã COMPLETED
        // - Trong vòng 5 phút
        // - Không ở các trạng thái refund khác
        $canRequestRefund =
            $status === 'COMPLETED'
            && $diffSeconds <= 300
            && !\in_array($status, [
                'REFUND_REQUESTED',
                'REFUND_PROCESSING',
                'REFUNDED',
                'REFUND_REJECTED',
            ], true);

        return $this->render('orders/show.html.twig', [
            'order'            => $order,
            'canRequestRefund' => $canRequestRefund,
        ]);
    }

    #[Route('/{id}/resume', name: 'app_orders_resume', methods: ['GET'])]
    public function resume(Order $order, MomoService $momo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!\in_array($order->getStatus(), ['NEW', 'PENDING'], true)) {
            $this->addFlash('shop.error', 'This order cannot be paid.');
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

        $this->addFlash('shop.error', 'MoMo error: '.($pay['message'] ?? 'unknown'));
        return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/request-refund', name: 'app_orders_request_refund', methods: ['POST'])]
    public function requestRefund(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('request_refund_'.$order->getId(), $request->request->get('_token'))) {
            $this->addFlash('shop.error', 'Invalid token.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        // Chỉ cho hoàn tiền với đơn đã COMPLETED
        if ($order->getStatus() !== 'COMPLETED') {
            $this->addFlash('shop.error', 'Refund can only be requested for completed orders.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        // Giới hạn thời gian 5 phút kể từ lúc thanh toán (hoặc tạo đơn)
        $now     = new \DateTimeImmutable();
        $created = $order->getPaidAt() ?: $order->getCreatedAt();
        $diffSeconds = $now->getTimestamp() - $created->getTimestamp();

        if ($diffSeconds > 300) { // 5 * 60
            $this->addFlash('shop.error', 'Refund/return period has expired.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        // Đặt trạng thái vào REFUND_REQUESTED, sau đó admin xử lý
        $order->setStatus('REFUND_REQUESTED');
        $em->flush();

        $this->addFlash(
            'shop.success',
            'Refund/return request for order #'.$order->getId().' has been sent. We will contact you soon.'
        );

        return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
    }

    /**
     * Customer xác nhận đã nhận hàng.
     * COD: đồng thời xác nhận đã trả tiền nếu trước đó chưa set paidAt.
     * MoMo: chỉ xác nhận đã nhận hàng (tiền đã trả online).
     */
    #[Route('/{id}/confirm-delivered', name: 'app_orders_confirm_delivered', methods: ['POST'])]
    public function confirmDelivered(Order $order, Request $req, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('confirm_delivered_'.$order->getId(), $req->request->get('_token'))) {
            $this->addFlash('shop.error', 'Invalid token.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        $status = $order->getStatus();

        // Chỉ cho confirm khi admin đã set DELIVERED
        if ($status !== 'DELIVERED') {
            $this->addFlash('shop.error', 'You can confirm the order only after it is marked as delivered by admin.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        // Đơn đã giao xong, user xác nhận -> COMPLETED
        if ($order->getPaidAt() === null) {
            $order->setPaidAt(new \DateTimeImmutable());
        }

        $order->setStatus('COMPLETED');
        $em->flush();

        $this->addFlash('shop.success', 'Thank you! The order has been completed.');
        return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
    }
}
