<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    #[Route('/index', name: 'app_homepage')]
    public function index(): Response
    {
        return $this->render('index.html.twig', [
            'user' => $this->getUser()
        ]);
    }
}