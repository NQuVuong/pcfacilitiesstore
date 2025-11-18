<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminReportController extends AbstractController
{
    #[Route('/admin/report', name: 'app_admin_report')]
    public function revenue(EntityManagerInterface $em): Response
    {
        // Chỉ cho staff & admin
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $paidStatuses = ['PAID', 'COMPLETED'];

        // Hàm tính tổng tiền trong 1 khoảng thời gian
        $sumBetween = function (\DateTimeImmutable $from, \DateTimeImmutable $to) use ($em, $paidStatuses): float {
            $dql = '
                SELECT COALESCE(SUM(o.total), 0)
                FROM App\Entity\Order o
                WHERE o.status IN (:st)
                  AND o.createdAt >= :from
                  AND o.createdAt < :to
            ';

            return (float) $em->createQuery($dql)
                ->setParameter('st', $paidStatuses)
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getSingleScalarResult();
        };

        $today = new \DateTimeImmutable('today');

        // Doanh thu hôm nay
        $startToday = $today;
        $endToday   = $startToday->modify('+1 day');
        $revenueToday = $sumBetween($startToday, $endToday);

        // Doanh thu tuần này (tính từ thứ 2)
        $startWeek = $today->modify('monday this week');
        $endWeek   = $startWeek->modify('+7 days');
        $revenueWeek = $sumBetween($startWeek, $endWeek);

        // Doanh thu tháng này
        $startMonth = $today->modify('first day of this month');
        $endMonth   = $startMonth->modify('+1 month');
        $revenueMonth = $sumBetween($startMonth, $endMonth);

        // Top 5 sản phẩm bán nhiều nhất (theo quantity)
        $dqlTopProducts = '
            SELECT p.id, p.name AS productName,
                   SUM(i.quantity) AS qty,
                   SUM(i.quantity * i.unitPrice) AS revenue
            FROM App\Entity\OrderItem i
            JOIN i.order o
            JOIN i.product p
            WHERE o.status IN (:st)
            GROUP BY p.id, p.name
            ORDER BY qty DESC
        ';

        $topProducts = $em->createQuery($dqlTopProducts)
            ->setParameter('st', $paidStatuses)
            ->setMaxResults(5)
            ->getResult();

        // Doanh thu 6 tháng gần nhất (chart)
        $chartLabels = [];
        $chartRevenue = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = $startMonth->modify("-{$i} month");
            $end   = $start->modify('+1 month');

            $chartLabels[]  = $start->format('m/Y');
            $chartRevenue[] = $sumBetween($start, $end);
        }

        // Tổng số order & khách hàng
        $totalOrders = (int) $em->createQuery(
            'SELECT COUNT(o.id) FROM App\Entity\Order o WHERE o.status IN (:st)'
        )
            ->setParameter('st', $paidStatuses)
            ->getSingleScalarResult();

        $totalCustomers = (int) $em->createQuery(
            'SELECT COUNT(DISTINCT o.user) FROM App\Entity\Order o WHERE o.status IN (:st)'
        )
            ->setParameter('st', $paidStatuses)
            ->getSingleScalarResult();

        return $this->render('admin/report/revenue.html.twig', [
            'revenueToday'  => $revenueToday,
            'revenueWeek'   => $revenueWeek,
            'revenueMonth'  => $revenueMonth,
            'totalOrders'   => $totalOrders,
            'totalCustomers'=> $totalCustomers,
            'topProducts'   => $topProducts,
            'chartLabels'   => $chartLabels,
            'chartRevenue'  => $chartRevenue,
        ]);
    }
}
