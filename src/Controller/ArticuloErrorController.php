<?php

namespace App\Controller;

use App\Entity\Articulo;
use App\Repository\ArticuloRepository;
use App\Service\NotificacionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/articulo')]
class ArticuloErrorController extends AbstractController
{
    #[Route('/{id}/reportar-error', name: 'app_articulo_reportar_error', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportarError(
        Articulo $articulo,
        Request $request,
        NotificacionService $notificacionService
    ): Response {
        $mensaje = $request->request->get('mensaje');
        
        if (empty($mensaje) || trim($mensaje) === '') {
            $this->addFlash('error', 'El mensaje no puede estar vacío.');
            return $this->redirectToRoute('app_articulo_publico_show', [
                'id' => $articulo->getId(),
                'ley' => $request->query->get('ley', 0),
                'search' => $request->query->get('search', '')
            ]);
        }

        $alumno = $this->getUser();
        
        try {
            $notificacionService->crearNotificacionErrorArticulo($articulo, $alumno, trim($mensaje));
            $this->addFlash('success', 'Error reportado correctamente. Los profesores y administradores han sido notificados.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al reportar el problema. Por favor, inténtalo de nuevo.');
        }

        return $this->redirectToRoute('app_articulo_publico_show', [
            'id' => $articulo->getId(),
            'ley' => $request->query->get('ley', 0),
            'search' => $request->query->get('search', '')
        ]);
    }
}



