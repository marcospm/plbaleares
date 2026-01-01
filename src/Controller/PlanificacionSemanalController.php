<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controlador obsoleto - Redirige todas las rutas al nuevo sistema de planificaciones
 * @deprecated Use PlanificacionController instead
 */
#[Route('/planificacion-semanal')]
#[IsGranted('ROLE_PROFESOR')]
class PlanificacionSemanalController extends AbstractController
{
    #[Route('/', name: 'app_planificacion_semanal_index', methods: ['GET'])]
    public function index(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }

    #[Route('/new', name: 'app_planificacion_semanal_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_new');
    }

    #[Route('/{id}', name: 'app_planificacion_semanal_show', methods: ['GET'])]
    public function show(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }

    #[Route('/{id}/franja/new', name: 'app_planificacion_semanal_franja_new', methods: ['GET', 'POST'])]
    public function newFranja(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }

    #[Route('/franja/{id}/edit', name: 'app_planificacion_semanal_franja_edit', methods: ['GET', 'POST'])]
    public function editFranja(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }

    #[Route('/franja/{id}', name: 'app_planificacion_semanal_franja_delete', methods: ['POST'])]
    public function deleteFranja(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }

    #[Route('/{id}/edit', name: 'app_planificacion_semanal_edit', methods: ['GET', 'POST'])]
    public function edit(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }

    #[Route('/{id}/asignar', name: 'app_planificacion_semanal_asignar', methods: ['GET', 'POST'])]
    public function asignar(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }

    #[Route('/{id}/desasignar/{usuarioId}', name: 'app_planificacion_semanal_desasignar', methods: ['POST'])]
    public function desasignarUsuario(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }

    #[Route('/{id}', name: 'app_planificacion_semanal_delete', methods: ['POST'])]
    public function delete(): Response
    {
        // Redirigir al nuevo sistema
        return $this->redirectToRoute('app_planificacion_index');
    }
}
