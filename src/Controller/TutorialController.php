<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TutorialController extends AbstractController
{
    #[Route('/tutorial', name: 'app_tutorial')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('tutorial/index.html.twig');
    }
}

