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

    #[Route('/tutorial-profesor', name: 'app_tutorial_profesor')]
    #[IsGranted('ROLE_PROFESOR')]
    public function profesor(): Response
    {
        return $this->render('tutorial/profesor.html.twig');
    }

    #[Route('/tutorial-admin', name: 'app_tutorial_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(): Response
    {
        return $this->render('tutorial/admin.html.twig');
    }
}

