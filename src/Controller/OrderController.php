<?php

namespace App\Controller;

use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/order', name: 'app_order')]
class OrderController extends AbstractController
{
    #[Route('/{slug}', name: '_recap')]
    public function index(Product $product): Response
    {
        return $this->render('order/index.html.twig', [
            'product' => $product
        ]);
    }
}
