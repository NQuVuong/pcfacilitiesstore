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
#[IsGranted('ROLE_ADMIN')] // chỉ ADMIN quản lý order
class AdminOrderController extends AbstractController
{
    #[Route('/', name: 'app_admin_order_index')]
    public function index(OrderRepository $repo, Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = 12;

        $all = $repo->findBy([], ['createdAt' => 'DESC']);

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $all = \array_values(\array_filter(
                $all,
                function (Order $o) use ($needle) {
                    $email = '';
                    if ($o->getCustomerEmail()) {
                        $email = $o->getCustomerEmail();
                    } elseif ($o->getUser()) {
                        $email = $o->getUser()->getEmail() ?? '';
                    }
                    return $email !== '' && mb_stripos($email, $needle) !== false;
                }
            ));
        }

        $total     = \count($all);
        $pageCount = (int) \ceil(max(1, $total) / $perPage);
        $page      = min($page, max(1, $pageCount));

        $offset = ($page - 1) * $perPage;
        $orders = \array_slice($all, $offset, $perPage);

        return $this->render('admin_order/index.html.twig', [
            'orders'    => $orders,
            'q'         => $q,
            'page'      => $page,
            'pageCount' => $pageCount,
            'total'     => $total,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_order_show')]
    public function show(Order $order): Response
    {
        return $this->render('admin_order/show.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * ADMIN – cập nhật trạng thái đơn (NEW/PAID -> PREPARING -> SHIPPING -> DELIVERED).
     * Sau DELIVERED, admin không cập nhật nữa, user tự confirm thành COMPLETED.
     */
    #[Route('/{id}/status', name: 'app_admin_order_update_status', methods: ['POST'])]
    public function updateStatus(
        Order $order,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('update_status_'.$order->getId(), $request->request->get('_token'))) {
            $this->addFlash('admin.error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $current = $order->getStatus();

        // Không cho chỉnh nếu đã kết thúc/refund
        if (\in_array($current, [
            'DELIVERED',
            'COMPLETED',
            'CANCELED',
            'REFUND_REQUESTED',
            'REFUND_PROCESSING',
            'REFUNDED',
            'REFUND_REJECTED',
            'REFUND_FAILED',
        ], true)) {
            $this->addFlash('admin.error', 'This order cannot be updated anymore.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $map = [
            'NEW'       => 'PREPARING',
            'PAID'      => 'PREPARING',
            'PREPARING' => 'SHIPPING',
            'SHIPPING'  => 'DELIVERED',
        ];

        $expectedNext = $map[$current] ?? null;
        $next = (string) $request->request->get('next_status', '');

        if ($expectedNext === null || $next !== $expectedNext) {
            $this->addFlash('admin.error', 'Invalid next status.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $order->setStatus($next);
        $em->flush();

        $this->addFlash(
            'admin.success',
            sprintf('Order #%d status updated from %s to %s.', $order->getId(), $current, $next)
        );

        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }

    /**
     * ADMIN – danh sách đơn đang chờ admin duyệt hoàn tiền
     */
    #[Route('/refunds', name: 'app_admin_order_refund_list', methods: ['GET'])]
    public function refundList(OrderRepository $repo): Response
    {
        $orders = $repo->findBy(
            ['status' => 'REFUND_REQUESTED'],
            ['createdAt' => 'DESC']
        );

        return $this->render('admin_order/refunds.html.twig', [
            'orders' => $orders,
        ]);
    }

    /**
     * ADMIN – Duyệt hoàn tiền MoMo
     */
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
            $this->addFlash('admin.error', 'Order is not in refund-requested state or not paid by MoMo.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $adminNote = trim((string) $request->request->get('admin_note', ''));
        if (method_exists($order, 'setRefundAdminNote')) {
            $order->setRefundAdminNote($adminNote ?: 'MoMo refund approved by admin.');
        }

        $amount = min(
            (int) \round((float) $order->getTotal()),
            max(0, (int) $order->getRefundableRemaining())
        );
        if ($amount < 1000) {
            $this->addFlash('admin.error', 'Refundable amount is not valid.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        if (!$order->getLastRefundRequestId()) {
            $order->setLastRefundRequestId('refund-'.\bin2hex(\random_bytes(8)));
        }
        if (!$order->getLastRefundOrderId()) {
            $order->setLastRefundOrderId(\sprintf('REFUND-%d-%s', $order->getId(), (string) \microtime(true)));
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

    /**
     * ADMIN – Duyệt hoàn tiền COD
     */
    #[Route('/{id}/cod-refund', name: 'app_admin_order_cod_refund', methods: ['POST'])]
    public function refundCodOrder(
        Order $order,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('cod-refund-order-'.$order->getId(), $request->request->get('_token'))) {
            $this->addFlash('admin.error', 'Invalid CSRF token, cannot refund COD order.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        if ($order->getPaymentMethod() !== 'COD' || $order->getStatus() !== 'REFUND_REQUESTED') {
            $this->addFlash('admin.error', 'Order is not in refund-requested COD state.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $adminNote = trim((string) $request->request->get('admin_note', ''));
        if (method_exists($order, 'setRefundAdminNote')) {
            $order->setRefundAdminNote($adminNote ?: 'COD refund approved by admin.');
        }

        $amount = (int) \round((float) $order->getTotal());
        $order->addRefunded($amount);
        $order->setStatus('REFUNDED');
        $em->flush();

        $this->addFlash('admin.success', 'COD refund marked as completed.');
        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }

    /**
     * ADMIN – Từ chối hoàn tiền
     */
    #[Route('/{id}/refund/reject', name: 'app_admin_order_refund_reject', methods: ['POST'])]
    public function rejectRefundAdmin(
        Order $order,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('admin-refund-reject-'.$order->getId(), $request->request->get('_token'))) {
            $this->addFlash('admin.error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_order_refund_list');
        }

        if ($order->getStatus() !== 'REFUND_REQUESTED') {
            $this->addFlash('admin.error', 'Order is not in refund-requested state.');
            return $this->redirectToRoute('app_admin_order_refund_list');
        }

        $adminNote = trim((string) $request->request->get('admin_note', ''));
        $order->setStatus('REFUND_REJECTED');
        if (method_exists($order, 'setRefundAdminNote')) {
            $order->setRefundAdminNote($adminNote ?: 'Refund rejected by admin.');
        }
        $em->flush();

        $this->addFlash('admin.success', 'Refund rejected for order #'.$order->getId().'.');
        return $this->redirectToRoute('app_admin_order_refund_list');
    }
}
