<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FaviconController extends AbstractController
{
    #[Route('/favicon.ico', name: 'app_favicon', methods: ['GET'])]
    public function favicon(): Response
    {
        // Intentar servir el favicon.png si existe, sino devolver 204 No Content
        $faviconPath = $this->getParameter('kernel.project_dir') . '/public/images/favicon.png';
        
        if (file_exists($faviconPath)) {
            return $this->file($faviconPath, null, Response::HEADER_DISPOSITION_INLINE);
        }
        
        // Si no existe, devolver 204 No Content para evitar errores en logs
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/apple-touch-icon.png', name: 'app_apple_touch_icon', methods: ['GET'])]
    #[Route('/apple-touch-icon-precomposed.png', name: 'app_apple_touch_icon_precomposed', methods: ['GET'])]
    public function appleTouchIcon(): Response
    {
        // Intentar servir el favicon.png si existe, sino devolver 204 No Content
        $faviconPath = $this->getParameter('kernel.project_dir') . '/public/images/favicon.png';
        
        if (file_exists($faviconPath)) {
            return $this->file($faviconPath, null, Response::HEADER_DISPOSITION_INLINE);
        }
        
        // Si no existe, devolver 204 No Content para evitar errores en logs
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        // Intentar servir robots.txt si existe
        $robotsPath = $this->getParameter('kernel.project_dir') . '/public/robots.txt';
        
        if (file_exists($robotsPath)) {
            return $this->file($robotsPath, null, Response::HEADER_DISPOSITION_INLINE);
        }
        
        // Si no existe, devolver 204 No Content para evitar errores en logs
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        // Intentar servir sitemap.xml si existe
        $sitemapPath = $this->getParameter('kernel.project_dir') . '/public/sitemap.xml';
        
        if (file_exists($sitemapPath)) {
            return $this->file($sitemapPath, null, Response::HEADER_DISPOSITION_INLINE);
        }
        
        // Si no existe, devolver 204 No Content para evitar errores en logs
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/favicon-{width}x{height}.png', name: 'app_favicon_sized', requirements: ['width' => '\d+', 'height' => '\d+'], methods: ['GET'])]
    public function faviconSized(int $width, int $height): Response
    {
        // Intentar servir el favicon.png si existe, sino devolver 204 No Content
        $faviconPath = $this->getParameter('kernel.project_dir') . '/public/images/favicon.png';
        
        if (file_exists($faviconPath)) {
            return $this->file($faviconPath, null, Response::HEADER_DISPOSITION_INLINE);
        }
        
        // Si no existe, devolver 204 No Content para evitar errores en logs
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
