<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/order', name: 'app_order')]
class OrderController extends AbstractController
{
    #[Route('/', name: '_recap')]
    public function index(int $quantity = 1): Response
    {
        return $this->render('order/index.html.twig', [
            'quantity' => $quantity
        ]);
    }
}
