<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\PageView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'app_admin_')]
#[IsGranted('ROLE_STAFF')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $paidStatuses = ['PAID', 'PAID_CONFIRMED', 'SHIPPED', 'COMPLETED'];

        $now        = new \DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0);
        $weekStart  = $todayStart->modify('-6 days');
        $monthStart = $todayStart->modify('first day of this month');

        // helper doanh thu
        $sumBetween = function (\DateTimeImmutable $from, \DateTimeImmutable $to) use ($em, $paidStatuses): float {
            return (float) $em->createQuery(
                'SELECT COALESCE(SUM(o.total), 0)
                 FROM App\Entity\Order o
                 WHERE o.status IN (:st)
                   AND o.createdAt >= :from
                   AND o.createdAt < :to'
            )
                ->setParameter('st', $paidStatuses)
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getSingleScalarResult();
        };

        $todayRevenue  = $sumBetween($todayStart, $todayStart->modify('+1 day'));
        $weekRevenue   = $sumBetween($weekStart, $todayStart->modify('+1 day'));
        $monthRevenue  = $sumBetween($monthStart, $todayStart->modify('+1 day'));

        $ordersToday = (int) $em->createQuery(
            'SELECT COUNT(o.id)
             FROM App\Entity\Order o
             WHERE o.status IN (:st)
               AND o.createdAt >= :from
               AND o.createdAt < :to'
        )
            ->setParameter('st', $paidStatuses)
            ->setParameter('from', $todayStart)
            ->setParameter('to', $todayStart->modify('+1 day'))
            ->getSingleScalarResult();

        $totalOrders = (int) $em->createQuery(
            'SELECT COUNT(o.id) FROM App\Entity\Order o'
        )->getSingleScalarResult();

        // ===== Visits =====
        $totalVisits = (int) $em->createQuery(
            'SELECT COUNT(v.id) FROM App\Entity\PageView v'
        )->getSingleScalarResult();

        $todayVisits = (int) $em->createQuery(
            'SELECT COUNT(v.id)
             FROM App\Entity\PageView v
             WHERE v.createdAt >= :from
               AND v.createdAt < :to'
        )
            ->setParameter('from', $todayStart)
            ->setParameter('to', $todayStart->modify('+1 day'))
            ->getSingleScalarResult();

        // ===== Chart 7 ngày: revenue + visits =====
        $chartDays    = [];
        $chartRevenue = [];
        $visitDays    = [];
        $visitCounts  = [];

        for ($i = 0; $i < 7; $i++) {
            $from = $weekStart->modify("+{$i} day");
            $to   = $from->modify('+1 day');

            $chartDays[]    = $from->format('Y-m-d');
            $chartRevenue[] = $sumBetween($from, $to);

            $visitDays[] = $from->format('Y-m-d');
            $visitCounts[] = (int) $em->createQuery(
                'SELECT COUNT(v.id)
                 FROM App\Entity\PageView v
                 WHERE v.createdAt >= :from AND v.createdAt < :to'
            )
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getSingleScalarResult();
        }

        // ===== Order status pie =====
        $statusRows = $em->createQuery(
            'SELECT o.status AS status, COUNT(o.id) AS cnt
             FROM App\Entity\Order o
             GROUP BY o.status'
        )->getArrayResult();

        $statusLabels = array_column($statusRows, 'status');
        $statusCounts = array_map('intval', array_column($statusRows, 'cnt'));

        // ===== Products by brand =====
        $rowsBrand = $em->createQuery(
            'SELECT b.name AS name, COUNT(p.id) AS total
             FROM App\Entity\Product p
             JOIN p.brand b
             GROUP BY b.id, b.name
             ORDER BY total DESC'
        )->getArrayResult();

        $brandLabels = array_column($rowsBrand, 'name');
        $brandCount  = array_map('intval', array_column($rowsBrand, 'total'));

        // ===== Low stock (<=5) =====
        $lowStock = $em->createQuery(
            'SELECT p
             FROM App\Entity\Product p
             WHERE p.Quantity <= :q
             ORDER BY p.Quantity ASC'
        )
            ->setParameter('q', 5)
            ->setMaxResults(5)
            ->getResult();

        // ===== Top 5 best-selling products =====
        $bestSellers = $em->createQuery(
            'SELECT p.id AS productId,
                    p.name AS productName,
                    SUM(oi.quantity) AS qty,
                    SUM(oi.lineTotal) AS revenue
             FROM App\Entity\OrderItem oi
             JOIN oi.order o
             JOIN oi.product p
             WHERE o.status IN (:st)
             GROUP BY p.id, p.name
             ORDER BY qty DESC'
        )
            ->setParameter('st', $paidStatuses)
            ->setMaxResults(5)
            ->getArrayResult();

        // ===== Most visited pages =====
        $topPages = $em->createQuery(
            'SELECT v.path AS path,
                    COUNT(v.id) AS visits
             FROM App\Entity\PageView v
             GROUP BY v.path
             ORDER BY visits DESC'
        )
            ->setMaxResults(5)
            ->getArrayResult();

        // ===== Browsers =====
        $topBrowsers = $em->createQuery(
            'SELECT COALESCE(v.browser, :unknown) AS browser,
                    COUNT(v.id) AS visits
             FROM App\Entity\PageView v
             GROUP BY browser
             ORDER BY visits DESC'
        )
            ->setParameter('unknown', 'Unknown')
            ->getArrayResult();

        return $this->render('admin/dashboard/index.html.twig', [
            'todayRevenue'   => $todayRevenue,
            'weekRevenue'    => $weekRevenue,
            'monthRevenue'   => $monthRevenue,
            'ordersToday'    => $ordersToday,
            'totalOrders'    => $totalOrders,
            'totalVisits'    => $totalVisits,
            'todayVisits'    => $todayVisits,

            'chartDays'      => $chartDays,
            'chartRevenue'   => $chartRevenue,
            'visitDays'      => $visitDays,
            'visitCounts'    => $visitCounts,

            'statusLabels'   => $statusLabels,
            'statusCounts'   => $statusCounts,
            'brandLabels'    => $brandLabels,
            'brandCount'     => $brandCount,
            'lowStock'       => $lowStock,
            'bestSellers'    => $bestSellers,
            'topPages'       => $topPages,
            'topBrowsers'    => $topBrowsers,
        ]);
    }
}
