<?php

namespace App\Controller;

use App\Service\NotificacionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notificacion-general')]
#[IsGranted('ROLE_ADMIN')]
class NotificacionGeneralController extends AbstractController
{
    #[Route('/enviar', name: 'app_notificacion_general_enviar', methods: ['GET', 'POST'])]
    public function enviar(Request $request, NotificacionService $notificacionService): Response
    {
        $admin = $this->getUser();
        if (!$admin) {
            throw $this->createAccessDeniedException('Usuario no autenticado');
        }

        if ($request->isMethod('POST')) {
            $titulo = trim($request->request->get('titulo', ''));
            $mensaje = trim($request->request->get('mensaje', ''));

            // Validar campos
            if (empty($titulo)) {
                $this->addFlash('error', 'El título es obligatorio.');
                return $this->render('notificacion_general/enviar.html.twig', [
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                ]);
            }

            if (empty($mensaje)) {
                $this->addFlash('error', 'El mensaje es obligatorio.');
                return $this->render('notificacion_general/enviar.html.twig', [
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                ]);
            }

            if (strlen($titulo) > 255) {
                $this->addFlash('error', 'El título no puede exceder 255 caracteres.');
                return $this->render('notificacion_general/enviar.html.twig', [
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                ]);
            }

            try {
                $notificacionService->crearNotificacionGeneral($titulo, $mensaje, $admin);
                $this->addFlash('success', 'Notificación general enviada exitosamente a todos los alumnos.');
                return $this->redirectToRoute('app_notificacion_general_enviar');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al enviar la notificación: ' . $e->getMessage());
                return $this->render('notificacion_general/enviar.html.twig', [
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                ]);
            }
        }

        return $this->render('notificacion_general/enviar.html.twig');
    }
}
