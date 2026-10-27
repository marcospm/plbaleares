<?php

namespace App\Controller;

use App\Service\SupresionDatosService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route('/privacidad', name: 'app_privacidad', methods: ['GET'])]
    public function privacidad(): Response
    {
        return $this->render('legal/privacidad.html.twig', [
            'retencionAnios' => SupresionDatosService::RETENCION_RESULTADOS_ANIOS,
            'retencionContactoAnios' => SupresionDatosService::RETENCION_CONTACTO_ANIOS,
        ]);
    }

    #[Route('/aviso-legal', name: 'app_aviso_legal', methods: ['GET'])]
    public function avisoLegal(): Response
    {
        return $this->render('legal/aviso_legal.html.twig');
    }
}
