<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Tổng doanh thu đã thanh toán trong khoảng [from, to)
     */
    public function getRevenue(\DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.total), 0)')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->andWhere('o.status IN (:paidStatuses)')
            ->setParameter('from', $from)
            ->setParameter('to',   $to)
            ->setParameter('paidStatuses', ['PAID', 'PAID_CONFIRMED', 'SHIPPED', 'COMPLETED']);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Tổng số đơn đã thanh toán trong khoảng [from, to)
     */
    public function countOrders(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->andWhere('o.status IN (:paidStatuses)')
            ->setParameter('from', $from)
            ->setParameter('to',   $to)
            ->setParameter('paidStatuses', ['PAID', 'PAID_CONFIRMED', 'SHIPPED', 'COMPLETED']);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Top sản phẩm bán nhiều nhất (theo quantity)
     * Trả về mảng đơn giản để Twig dùng: productId, productName, qty, revenue
     */
    public function getTopProductsByQuantity(int $limit = 5): array
    {
        $em = $this->getEntityManager();

        $qb = $em->createQueryBuilder()
            ->select('IDENTITY(oi.product) AS productId')
            ->addSelect('p.name AS productName')
            ->addSelect('SUM(oi.quantity) AS qty')
            ->addSelect('SUM(oi.quantity * oi.unitPrice) AS revenue')
            ->from(OrderItem::class, 'oi')
            ->join('oi.order', 'o')
            ->join('oi.product', 'p')
            ->where('o.status IN (:paidStatuses)')
            ->setParameter('paidStatuses', ['PAID', 'PAID_CONFIRMED', 'SHIPPED', 'COMPLETED'])
            ->groupBy('productId', 'productName')
            ->orderBy('qty', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Tính revenue, cost, profit trong khoảng [from, to)
     * Cost = quantity * importPrice (importPrice lấy từ Price cũ nhất của product)
     * Làm bằng PHP để khỏi dính lỗi DQL.
     */
    public function getRevenueAndCost(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $em = $this->getEntityManager();

        $qb = $em->createQueryBuilder()
            ->select('oi', 'o', 'p')
            ->from(OrderItem::class, 'oi')
            ->join('oi.order', 'o')
            ->join('oi.product', 'p')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->andWhere('o.status IN (:paidStatuses)')
            ->setParameter('from', $from)
            ->setParameter('to',   $to)
            ->setParameter('paidStatuses', ['PAID', 'PAID_CONFIRMED', 'SHIPPED', 'COMPLETED']);

        /** @var OrderItem[] $items */
        $items = $qb->getQuery()->getResult();

        $revenue = 0.0;
        $cost    = 0.0;

        foreach ($items as $item) {
            $qty    = $item->getQuantity();
            $sell   = (float) $item->getUnitPrice();
            $revenue += $qty * $sell;

            $product = $item->getProduct();
            if ($product) {
                // dùng helper có sẵn trong Product: original import price
                $importPrice = $product->getOriginalImportPrice() ?? 0.0;
                $cost += $qty * $importPrice;
            }
        }

        return [
            'revenue' => $revenue,
            'cost'    => $cost,
            'profit'  => $revenue - $cost,
        ];
    }

    /**
     * Data cho chart Revenue/Cost/Profit 6 tháng gần nhất
     */
    public function getProfitLastMonths(int $months = 6): array
    {
        $now        = new \DateTimeImmutable('first day of this month');
        $labels     = [];
        $revenues   = [];
        $costs      = [];
        $profits    = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $now->modify("-{$i} month");
            $end   = $start->modify('+1 month');

            $rc = $this->getRevenueAndCost($start, $end);

            $labels[]   = $start->format('m/Y');
            $revenues[] = $rc['revenue'];
            $costs[]    = $rc['cost'];
            $profits[]  = $rc['profit'];
        }

        return [
            'labels'   => $labels,
            'revenues' => $revenues,
            'costs'    => $costs,
            'profits'  => $profits,
        ];
    }
}
