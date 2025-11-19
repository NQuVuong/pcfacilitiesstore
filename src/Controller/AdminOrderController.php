<?php
// src/Controller/AdminOrderController.php
namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\MomoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/order')]
#[IsGranted('ROLE_ADMIN')]
class AdminOrderController extends AbstractController
{
    #[Route('/', name: 'app_admin_order_index')]
    public function index(OrderRepository $repo): Response
    {
        $orders = $repo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin_order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_order_show')]
    public function show(Order $order): Response
    {
        return $this->render('admin_order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/refund', name: 'app_admin_order_refund', methods: ['POST'])]
    public function refundOrder(
        Order $order,
        Request $request,
        MomoService $momo,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('refund-order-'.$order->getId(), $request->request->get('_token'))) {
            $this->addFlash('admin.error', 'Invalid CSRF token, cannot refund.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        if ($order->getPaymentMethod() !== 'MOMO' || $order->getStatus() !== 'REFUND_REQUESTED') {
            $this->addFlash('admin.error', 'Order is not requested for refund or not paid by MoMo.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $amount = min(
            (int) round((float) $order->getTotal()),
            max(0, (int) $order->getRefundableRemaining())
        );
        if ($amount < 1000) {
            $this->addFlash('admin.error', 'Refundable amount is not valid.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        if (!$order->getLastRefundRequestId()) {
            $order->setLastRefundRequestId('refund-'.bin2hex(random_bytes(8)));
        }
        if (!$order->getLastRefundOrderId()) {
            $order->setLastRefundOrderId(sprintf('REFUND-%d-%s', $order->getId(), (string) microtime(true)));
        }

        $order->setStatus('REFUND_PROCESSING');
        $em->flush();

        $response = $momo->createRefund(
            $order,
            $amount,
            'Refund order #'.$order->getId(),
            $order->getLastRefundRequestId(),
            $order->getLastRefundOrderId()
        );

        $resultCode = (int) ($response['resultCode'] ?? -1);
        $message    = (string)($response['message'] ?? '');

        if ($resultCode === 0) {
            $order->addRefunded($amount);
            $order->setStatus('REFUNDED');
            $order->setLastRefundRequestId(null);
            $order->setLastRefundOrderId(null);
            $em->flush();

            $this->addFlash('admin.success', 'Refund successful. '.$message);
        } else {
            $order->setStatus('REFUND_FAILED');
            $em->flush();

            $this->addFlash(
                'admin.error',
                'Refund failed: '.$message.' (code '.$resultCode.')'
            );
        }

        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }
}
