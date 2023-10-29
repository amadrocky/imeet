<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/products', name: 'app_product')]
class ProductController extends AbstractController
{
    #[Route('/', name: '_index')]
    public function index(): Response
    {
        return $this->render('product/index.html.twig', [
            
        ]);
    }

    #[Route('/show', name: '_show')]
    public function show(): Response
    {
        return $this->render('product/show.html.twig', [
            
        ]);
    }
}
