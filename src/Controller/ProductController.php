<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/products', name: 'app_product')]
class ProductController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository
    ) {
    }

    #[Route('/', name: '_index')]
    public function index(): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $this->productRepository->findBy(['enabled' => true])
        ]);
    }

    #[Route('/show/{slug}', name: '_show')]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product
        ]);
    }
}
