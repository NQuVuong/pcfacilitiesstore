<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function indexAction(): Response
    {
        $products = $this->repo->findAll();
        return $this->render('home.html.twig', [
            'products' => $products
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

