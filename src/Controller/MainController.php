<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
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
        // giữ kết quả catalog hiện tại (nếu có)
        $q    = trim((string)$request->query->get('q', ''));
        $sort = (string)$request->query->get('sort', 'newest');
        $cat  = $request->query->get('cat');
        $catId    = is_numeric($cat) ? (int)$cat : null;
        $products = $this->repo->findCatalog($q ?: null, $catId, $sort);

        // === PRIORITY ORDER ===
        $priorityNames = ['Keyboard', 'Mouse', 'Monitor'];

        $allCats = $catRepo->findBy([], ['name' => 'ASC']);

        usort($allCats, function ($a, $b) use ($priorityNames) {
            $pa = array_search($a->getName(), $priorityNames, true);
            $pb = array_search($b->getName(), $priorityNames, true);
            $pa = ($pa === false) ? PHP_INT_MAX : $pa;
            $pb = ($pb === false) ? PHP_INT_MAX : $pb;
            // ưu tiên theo danh sách, phần còn lại giữ alpha
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

        return $this->render('home.html.twig', [
            'products'               => $products,
            'q'                      => $q,
            'sort'                   => $sort,
            'cat'                    => $catId,
            'categories'             => $catRepo->findBy([], ['name' => 'ASC']),
            'categoriesWithProducts' => $categoriesWithProducts,
        ]);
    }

    #[Route('/admin', name: 'app_admin')]
    public function adminAction(): Response
    {
        return $this->render('admin.html.twig');
    }

    #[Route('/admin/products', name: 'app_product_index')]
    public function productIndexAction(): Response
    {
        $products = $this->repo->findAll();
        return $this->render('admin/product/index.html.twig', [
            'products' => $products
        ]);
    }
     #[Route('/catalog', name: 'catalog')]
    public function catalog(Request $request, CategoryRepository $catRepo): Response
    {
        $q    = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'newest');
        $cat  = $request->query->get('cat');
        $catId = is_numeric($cat) ? (int)$cat : null;

        $products = $this->repo->findCatalog($q ?: null, $catId, $sort);

        return $this->render('catalog.html.twig', [
            'products'   => $products,
            'q'          => $q,
            'sort'       => $sort,
            'cat'        => $catId,
            'categories' => $catRepo->findBy([], ['name' => 'ASC']),
        ]);
    }

}
