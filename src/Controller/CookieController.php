<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cookies')]
class CookieController extends AbstractController
{
    #[Route('/politica', name: 'app_cookie_politica', methods: ['GET'])]
    public function politica(): Response
    {
        return $this->render('cookie/politica.html.twig');
    }

    #[Route('/aceptar', name: 'app_cookie_aceptar', methods: ['POST'])]
    public function aceptar(Request $request): Response
    {
        // Esta ruta es principalmente para AJAX, pero también puede ser usada como fallback
        $response = new Response();
        $response->headers->setCookie(
            new Cookie(
                'cookie_consent',
                'accepted',
                time() + (365 * 24 * 60 * 60), // 1 año
                '/',
                null,
                $request->isSecure(),
                true, // httpOnly
                false,
                'strict'
            )
        );
        
        if ($request->isXmlHttpRequest()) {
            return new Response(json_encode(['success' => true]), 200, [
                'Content-Type' => 'application/json',
            ]);
        }
        
        return $this->redirectToRoute('app_home');
    }
}

