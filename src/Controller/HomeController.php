<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route(path: '/home', name: 'homepage')]
    public function indexPageAction(Request $request): Response
    {
        // Giữ query (?q=...&sort=...&cat=...) rồi chuyển về app_homepage
        return $this->redirectToRoute('app_homepage', $request->query->all());
    }


}
