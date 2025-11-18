<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_STAFF')]
class AdminRevenueController extends AbstractController
{
    #[Route('/revenue', name: 'app_admin_revenue', methods: ['GET'])]
    public function index(
        OrderRepository $orders,
        ProductRepository $products
    ): Response {
        $now        = new \DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0);
        $tomorrow   = $todayStart->modify('+1 day');
        $weekStart  = $now->modify('monday this week')->setTime(0, 0);
        $monthStart = $now->modify('first day of this month')->setTime(0, 0);

        // doanh thu & số order
        $todayRevenue  = $orders->getRevenue($todayStart, $tomorrow);
        $weekRevenue   = $orders->getRevenue($weekStart,  $tomorrow);
        $monthRevenue  = $orders->getRevenue($monthStart, $tomorrow);

        $todayOrders   = $orders->countOrders($todayStart, $tomorrow);
        $weekOrders    = $orders->countOrders($weekStart,  $tomorrow);
        $monthOrders   = $orders->countOrders($monthStart, $tomorrow);

        $topProducts   = $orders->getTopProductsByQuantity(5);

        // Lợi nhuận tháng này (cost & profit)
        $rcMonth    = $orders->getRevenueAndCost($monthStart, $tomorrow);
        $monthCost   = $rcMonth['cost'];
        $monthProfit = $rcMonth['profit'];

        // Chart profit 6 tháng gần nhất
        $profitData = $orders->getProfitLastMonths(6);

        // Nếu Product đã có field views (int) thì có thể lấy top viewed
        $topViewedProducts = $products->createQueryBuilder('p')
            ->orderBy('p.views', 'DESC')   // nếu chưa có cột views thì tạm thời bỏ đoạn này
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('admin/revenue/index.html.twig', [
            'todayRevenue'   => $todayRevenue,
            'weekRevenue'    => $weekRevenue,
            'monthRevenue'   => $monthRevenue,
            'todayOrders'    => $todayOrders,
            'weekOrders'     => $weekOrders,
            'monthOrders'    => $monthOrders,
            'topProducts'    => $topProducts,
            'topViewed'      => $topViewedProducts,
            'monthCost'      => $monthCost,
            'monthProfit'    => $monthProfit,
            'profitLabels'   => $profitData['labels'],
            'profitRevenues' => $profitData['revenues'],
            'profitCosts'    => $profitData['costs'],
            'profitProfits'  => $profitData['profits'],
        ]);
    }
}
