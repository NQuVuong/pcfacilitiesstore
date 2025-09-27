<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders')]
// #[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    #[Route('', name: 'app_orders_index')]
    public function index(OrderRepository $repo): Response
    {
        $orders = $repo->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']);
        return $this->render('orders/index.html.twig', ['orders' => $orders]);
    }

    #[Route('/{id}', name: 'app_orders_show', requirements: ['id' => '\d+'])]
    public function show(Order $order): Response
    {
        // chặn xem đơn của người khác
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        return $this->render('orders/show.html.twig', ['order' => $order]);
    }
}
