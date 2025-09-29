<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    //
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

       /**
    * @return Product[] Returns an array of Product objects
    */
   public function findProByCate($value): array
   {
    // SELECT p.id,c.id,p.name,p.image,p.price_export 
    // FROM `product` p, `category` c 
    // WHERE p.procat_id=c.id AND c.id=1
       return $this->createQueryBuilder('p')
           ->select('p.id,p.name,p.image,p.priceExport') 
           ->innerJoin('p.procat','c')
           ->Where('c.id = :val')
           ->setParameter('val', $value)
           ->getQuery()
           ->getResult()
       ;
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
    // SELECT p.id,c.id,p.name,p.image,p.price_export 
    // FROM `product` p, `brand` c 
    // WHERE p.procat_id=b.id AND b.id=1
       return $this->createQueryBuilder('p')
           ->select('p.id,p.name,p.image,p.priceExport') 
           ->innerJoin('p.brand','b')
           ->Where('b.id = :val')
           ->setParameter('val', $value)
           ->getQuery()
           ->getResult()
       ;
   }

      /**
    * @return Product[] Returns an array of Product objects
    */
   public function searchByName($name): array
   {
       return $this->createQueryBuilder('p')
           ->select('p.id,p.name,p.image,p.priceExport') 
           ->where('p.name LIKE :name')
           ->setParameter('name', '%'.$name.'%')
           ->getQuery()
           ->getArrayResult()
       ;
   }
    /**
     * Tìm sản phẩm có search, lọc category và sắp xếp.
     *
     * @param string|null $q         Từ khóa (tìm theo tên sp hoặc tên category)
     * @param int|null    $categoryId ID category cần lọc
     * @param string      $sort      newest|oldest|name_asc|name_desc|price_asc|price_desc|qty_asc|qty_desc
     *
     * @return Product[]
     */
    public function findCatalog(?string $q = null, ?int $categoryId = null, string $sort = 'newest'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            // join bảng Price để phục vụ sort theo giá
            ->leftJoin('p.prices', 'pr')
            // dùng MAX(exportPrice) làm "giá hiện tại" để sắp xếp (đơn giản, đủ dùng)
            ->addSelect('COALESCE(MAX(pr.exportPrice), 0) AS HIDDEN sortPrice')
            ->groupBy('p.id');

        if ($q !== null && $q !== '') {
            $q = mb_strtolower($q);
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(c.name) LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        if ($categoryId) {
            $qb->andWhere('c.id = :cat')->setParameter('cat', (int)$categoryId);
        }

        switch ($sort) {
            case 'oldest':
                $qb->orderBy('p.created', 'ASC');
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
            case 'qty_asc':
                $qb->orderBy('p.Quantity', 'ASC');
                break;
            case 'qty_desc':
                $qb->orderBy('p.Quantity', 'DESC');
                break;
            case 'newest':
            default:
                $qb->orderBy('p.created', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }
}
