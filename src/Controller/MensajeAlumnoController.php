<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\NotificacionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mensaje-alumno')]
#[IsGranted('ROLE_USER')]
class MensajeAlumnoController extends AbstractController
{
    public function __construct(
        private NotificacionService $notificacionService
    ) {
    }

    #[Route('', name: 'app_mensaje_alumno_index', methods: ['GET'])]
    public function index(): Response
    {
        $alumno = $this->getUser();
        
        if (!$alumno) {
            throw $this->createAccessDeniedException('Debes iniciar sesión.');
        }

        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta funcionalidad es solo para alumnos.');
        }

        $profesores = $alumno->getProfesores();

        return $this->render('mensaje_alumno/index.html.twig', [
            'profesores' => $profesores,
        ]);
    }

    #[Route('/{id}/enviar', name: 'app_mensaje_alumno_enviar', methods: ['GET', 'POST'])]
    public function enviar(Request $request, User $profesor): Response
    {
        $alumno = $this->getUser();
        
        if (!$alumno) {
            throw $this->createAccessDeniedException('Debes iniciar sesión.');
        }

        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta funcionalidad es solo para alumnos.');
        }

        // Verificar que el profesor está asignado al alumno
        if (!$alumno->getProfesores()->contains($profesor)) {
            $this->addFlash('error', 'Este profesor no está asignado a tu cuenta.');
            return $this->redirectToRoute('app_mensaje_alumno_index');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->get('token');
            if (!$this->isCsrfTokenValid('enviar_mensaje_' . $profesor->getId(), $token)) {
                $this->addFlash('error', 'Token CSRF inválido.');
                return $this->redirectToRoute('app_mensaje_alumno_index');
            }

            $mensaje = trim($request->request->get('mensaje', ''));
            
            if (empty($mensaje)) {
                $this->addFlash('error', 'El mensaje no puede estar vacío.');
                return $this->render('mensaje_alumno/enviar.html.twig', [
                    'profesor' => $profesor,
                ]);
            }

            if (strlen($mensaje) > 1000) {
                $this->addFlash('error', 'El mensaje no puede exceder los 1000 caracteres.');
                return $this->render('mensaje_alumno/enviar.html.twig', [
                    'profesor' => $profesor,
                ]);
            }

            try {
                $this->notificacionService->crearNotificacionMensajeAlumno($alumno, $profesor, $mensaje);
                $this->addFlash('success', 'Mensaje enviado correctamente a ' . $profesor->getUsername() . '.');
                return $this->redirectToRoute('app_mensaje_alumno_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al enviar el mensaje: ' . $e->getMessage());
                return $this->render('mensaje_alumno/enviar.html.twig', [
                    'profesor' => $profesor,
                ]);
            }
        }

        return $this->render('mensaje_alumno/enviar.html.twig', [
            'profesor' => $profesor,
        ]);
    }
}




