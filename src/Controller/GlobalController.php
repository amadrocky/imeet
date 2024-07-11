<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GlobalController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function contactPage(): Response
    {
        return $this->render('global/contact.html.twig');
    }

    #[Route('/legal', name: 'app_legal')]
    public function legalPage(): Response
    {
        return $this->render('global/legal.html.twig');
    }

    #[Route('/cgv', name: 'app_cgv')]
    public function cgvPage(): Response
    {
        return $this->render('global/CGV.html.twig');
    }
}
