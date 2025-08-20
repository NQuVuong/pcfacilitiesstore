<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class HomeController extends AbstractController
{
    private ProductRepository $repo;
    public function __construct(ProductRepository $repo)
   {
      $this->repo = $repo;
   }
    
    #[Route(path: '/', name: 'homepage')]
    public function indexPageAction(): Response
    {
        $products = $this->repo->findAll();
        return $this->render('home.html.twig', [
            'products'=>$products
        ]);
    }

    #[Route(path: '/admin', name: 'app_admin')]
    public function adminPageAction(): Response{
        return $this->render('admin.html.twig', [
            
        ]);
    }

}
