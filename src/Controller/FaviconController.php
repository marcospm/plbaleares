<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class FaviconController extends AbstractController
{
    #[Route('/favicon.ico', name: 'app_favicon', methods: ['GET'])]
    public function favicon(): Response
    {
        // Intentar servir el favicon.png si existe, sino devolver 204 No Content
        $faviconPath = $this->getParameter('kernel.project_dir') . '/public/images/favicon.png';
        
        if (file_exists($faviconPath)) {
            $response = new BinaryFileResponse($faviconPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            return $response;
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
            $response = new BinaryFileResponse($faviconPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            return $response;
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
            $response = new BinaryFileResponse($robotsPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            return $response;
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
            $response = new BinaryFileResponse($sitemapPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            return $response;
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
            $response = new BinaryFileResponse($faviconPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            return $response;
        }
        
        // Si no existe, devolver 204 No Content para evitar errores en logs
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/wp-config.php', name: 'app_wp_config', methods: ['GET'])]
    #[Route('/wp-config-{suffix}', name: 'app_wp_config_variants', requirements: ['suffix' => '.+'], methods: ['GET'])]
    #[Route('/wp-admin', name: 'app_wp_admin', methods: ['GET'])]
    #[Route('/wp-admin/{path}', name: 'app_wp_admin_path', requirements: ['path' => '.+'], methods: ['GET'])]
    #[Route('/wp-{path}', name: 'app_wp_common', requirements: ['path' => '.+'], methods: ['GET'])]
    #[Route('/.well-known/security.txt', name: 'app_security_txt', methods: ['GET'])]
    #[Route('/.git/config', name: 'app_git_config', methods: ['GET'])]
    #[Route('/.env', name: 'app_env', methods: ['GET'])]
    #[Route('/config.php', name: 'app_config_php', methods: ['GET'])]
    #[Route('/phpinfo.php', name: 'app_phpinfo', methods: ['GET'])]
    #[Route('/admin', name: 'app_admin_common', methods: ['GET'])]
    #[Route('/administrator', name: 'app_administrator', methods: ['GET'])]
    public function commonBotRequests(): Response
    {
        // Devolver 204 No Content para evitar errores en logs
        // Estos son archivos que los bots/scanners buscan pero no existen en nuestra aplicación
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
