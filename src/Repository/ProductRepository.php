<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function add(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** Basic examples (kept for compatibility) */
    public function findProByCate($value): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.name, p.image, p.priceExport')
            ->innerJoin('p.procat', 'c')
            ->where('c.id = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getResult();
    }

    public function findByCategoryId(int $id): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findProByBrand($value): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.name, p.image, p.priceExport')
            ->innerJoin('p.brand', 'b')
            ->where('b.id = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getResult();
    }

    public function searchByName($name): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.name, p.image, p.priceExport')
            ->where('p.name LIKE :name')
            ->setParameter('name', '%'.$name.'%')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Catalog with optional search, category filtering and sorting.
     * Uses MAX(pr.exportPrice) as computed price for sorting.
     */
    public function findCatalog(?string $q = null, ?int $categoryId = null, string $sort = 'newest'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->leftJoin('p.prices', 'pr')
            ->addSelect('COALESCE(MAX(pr.exportPrice), 0) AS HIDDEN sortPrice')
            ->groupBy('p.id');

        if ($q !== null && $q !== '') {
            $q = mb_strtolower($q);
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(c.name) LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        if ($categoryId) {
            $qb->andWhere('c.id = :cat')->setParameter('cat', (int) $categoryId);
        }

        switch ($sort) {
        case 'oldest':
            $qb->orderBy('p.id', 'ASC');
            break;
        case 'name_asc':
            $qb->orderBy('p.name', 'ASC');
            break;
        case 'name_desc':
            $qb->orderBy('p.name', 'DESC');
            break;
        case 'price_asc':
            $qb->orderBy('sortPrice', 'ASC');
            break;
        case 'price_desc':
            $qb->orderBy('sortPrice', 'DESC');
            break;

        // CHỈNH LẠI 2 NHÁNH NÀY CHO ĐÚNG TÊN FIELD
        case 'qty_asc':
            $qb->orderBy('p.Quantity', 'ASC');   // nếu property ở entity là "Quantity"
            break;
        case 'qty_desc':
            $qb->orderBy('p.Quantity', 'DESC');  // nếu property ở entity là "Quantity"
            break;

        case 'newest':
        default:
            $qb->orderBy('p.id', 'DESC');
            break;
    }

        return $qb->getQuery()->getResult();
    }

    /** Top products by one Category (newest by id). */
    public function findTopByCategory(Category $category, int $limit = 12): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :cat')
            ->setParameter('cat', $category)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Helper: top by category id. */
    public function findTopByCategoryId(int $categoryId, int $limit = 12): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->andWhere('c.id = :cid')
            ->setParameter('cid', $categoryId)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
