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
}
