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
   
    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
