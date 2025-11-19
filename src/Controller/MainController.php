<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\BrandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    public function __construct(private ProductRepository $repo) {}

    #[Route('/', name: 'app_homepage')]
    public function indexAction(Request $request, CategoryRepository $catRepo): Response
    {
        // Giữ state catalog hiện tại (nếu user đi từ catalog sang)
        $q    = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'newest');
        $cat  = $request->query->get('cat');
        $catId = is_numeric($cat) ? (int) $cat : null;

        $products = $this->repo->findCatalog($q ?: null, $catId, $sort);

        // === PRIORITY ORDER cho category trên homepage ===
        $priorityNames = ['Keyboard', 'Mouse', 'Monitor'];

        $allCats = $catRepo->findBy([], ['name' => 'ASC']);

        usort($allCats, function ($a, $b) use ($priorityNames) {
            $pa = array_search($a->getName(), $priorityNames, true);
            $pb = array_search($b->getName(), $priorityNames, true);

            $pa = ($pa === false) ? PHP_INT_MAX : $pa;
            $pb = ($pb === false) ? PHP_INT_MAX : $pb;

            if ($pa === $pb) {
                return strcasecmp($a->getName(), $b->getName());
            }

            return $pa <=> $pb;
        });

        $categoriesWithProducts = [];
        foreach ($allCats as $category) {
            $top = $this->repo->findTopByCategory($category, 12);
            if (\count($top) > 0) {
                $categoriesWithProducts[] = [
                    'category' => $category,
                    'products' => $top,
                ];
            }
        }

        // ================== RECENTLY VIEWED ==================
        $session = $request->getSession();
        $ids = $session->get('recent_products', []);
        if (!\is_array($ids)) {
            $ids = [];
        }

        $recentProducts = [];
        if ($ids) {
            $qb = $this->repo->createQueryBuilder('p')
                ->where('p.id IN (:ids)')
                ->setParameter('ids', $ids);

            /** @var Product[] $found */
            $found = $qb->getQuery()->getResult();

            $map = [];
            foreach ($found as $p2) {
                $map[$p2->getId()] = $p2;
            }

            // Giữ đúng thứ tự đã xem
            foreach ($ids as $id) {
                if (isset($map[$id])) {
                    $recentProducts[] = $map[$id];
                }
            }
        }

        // lấy tối đa 5 sản phẩm gần nhất
        $recentProducts = \array_slice($recentProducts, 0, 5);

        return $this->render('home.html.twig', [
            'products'               => $products,
            'q'                      => $q,
            'sort'                   => $sort,
            'cat'                    => $catId,
            'categories'             => $catRepo->findBy([], ['name' => 'ASC']),
            'categoriesWithProducts' => $categoriesWithProducts,
            'recentProducts'         => $recentProducts,
        ]);
    }

    #[Route('/admin', name: 'app_admin')]
    public function adminAction(): Response
    {
        return $this->render('admin.html.twig');
    }

    #[Route('/catalog', name: 'catalog')]
    public function catalog(
        Request $request,
        CategoryRepository $catRepo,
        BrandRepository $brandRepo
    ): Response {
        $q    = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'newest');
        $cat  = $request->query->get('cat');
        $catId = is_numeric($cat) ? (int) $cat : null;

        $brand   = $request->query->get('brand');
        $brandId = is_numeric($brand) ? (int) $brand : null;

        // Lấy tất cả theo filter category + sort cơ bản
        $all = $this->repo->findCatalog($q ?: null, $catId, $sort);

        // Lọc thêm theo brand nếu có
        if ($brandId) {
            $all = \array_values(\array_filter($all, function (Product $p) use ($brandId) {
                return $p->getBrand() && $p->getBrand()->getId() === $brandId;
            }));
        }

        // Sort theo rating (cao -> thấp) nếu chọn "rating"
        if ($sort === 'rating') {
            usort($all, function (Product $a, Product $b) {
                return $b->getAverageRating() <=> $a->getAverageRating();
            });
        }

        // ========== PHÂN TRANG ==========
        $page    = max(1, (int) $request->query->get('page', 1));

        // 👉 Nếu muốn chắc chắn nhìn thấy pagination với 11 sản phẩm,
        // anh có thể giảm số này xuống 4 / 6 / 8
        $perPage = 12;

        $total     = \count($all);
        $pageCount = (int) ceil(max(1, $total) / $perPage);
        $page      = min($page, max(1, $pageCount));

        $offset   = ($page - 1) * $perPage;
        $products = \array_slice($all, $offset, $perPage);

        return $this->render('catalog.html.twig', [
            'products'   => $products,
            'q'          => $q,
            'sort'       => $sort,
            'cat'        => $catId,
            'brand'      => $brandId,
            'categories' => $catRepo->findBy([], ['name' => 'ASC']),
            'brands'     => $brandRepo->findBy([], ['name' => 'ASC']),
            'page'       => $page,
            'pageCount'  => $pageCount,
            'total'      => $total,
        ]);
    }
}
