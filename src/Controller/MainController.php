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
    private ProductRepository $repo;

    public function __construct(ProductRepository $repo)
    {
        $this->repo = $repo;
    }

    #[Route('/', name: 'app_homepage')]
    public function indexAction(Request $request, CategoryRepository $catRepo): Response
    {
        $q    = trim((string)$request->query->get('q', ''));
        $sort = (string)$request->query->get('sort', 'newest');
        $cat  = $request->query->get('cat');

        $catId = is_numeric($cat) ? (int)$cat : null;
        $products = $this->repo->findCatalog($q ?: null, $catId, $sort);

        return $this->render('home.html.twig', [
            'products'   => $products,
            'q'          => $q,
            'sort'       => $sort,
            'cat'        => $catId,
            'categories' => $catRepo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/admin', name: 'app_admin')]
    public function adminAction(): Response
    {
        return $this->render('admin.html.twig', [
            'controller_name' => 'MainController'
        ]);
    }

    #[Route('/admin/products', name: 'app_product_index')]
    public function productIndexAction(): Response
    {
        $products = $this->repo->findAll();
        return $this->render('admin/product/index.html.twig', [
            'products' => $products
        ]);
    }
}
