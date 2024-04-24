<?php

namespace App\Controller;

use App\Repository\CompositionRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CompositionRepository $compositionRepository
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'products' => $this->productRepository->findBy(['enabled' => true]),
            'compositions' => $this->compositionRepository->findAll()
        ]);
    }
}
