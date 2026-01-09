<?php

namespace App\Controller;

use App\Service\PartidaPreguntasService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class PartidaPreguntasController extends AbstractController
{
    public function __construct(
        private PartidaPreguntasService $partidaService
    ) {
    }

    #[Route('/juegos/partida-preguntas', name: 'app_partida_preguntas_index')]
    public function index(): Response
    {
        return $this->render('juego/partida_preguntas/index.html.twig');
    }

    #[Route('/juegos/partida-preguntas/crear', name: 'app_partida_preguntas_crear', methods: ['GET', 'POST'])]
    public function crear(Request $request, SessionInterface $session): Response
    {
        if ($request->isMethod('POST')) {
            $numPreguntas = $request->request->getInt('numPreguntas', 10);
            $tiempoLimite = $request->request->getInt('tiempoLimite', 20);
            $dificultad = $request->request->get('dificultad');

            // Validar
            if ($numPreguntas < 5 || $numPreguntas > 20) {
                $this->addFlash('error', 'El número de preguntas debe estar entre 5 y 20');
                return $this->redirectToRoute('app_partida_preguntas_crear');
            }

            // Validar tiempo límite (5, 10, 15 o 20 minutos)
            if (!in_array($tiempoLimite, [5, 10, 15, 20])) {
                $this->addFlash('error', 'El tiempo límite debe ser 5, 10, 15 o 20 minutos');
                return $this->redirectToRoute('app_partida_preguntas_crear');
            }

            // Normalizar dificultad (null si está vacío)
            if ($dificultad === '' || $dificultad === null) {
                $dificultad = null;
            }

            // Obtener ID del usuario si está logueado
            $creadoPorId = $this->getUser() ? $this->getUser()->getId() : null;

            try {
                $codigo = $this->partidaService->crearPartida($numPreguntas, $tiempoLimite, $dificultad, $creadoPorId);
                return $this->redirectToRoute('app_partida_preguntas_compartir', ['codigo' => $codigo]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al crear la partida: ' . $e->getMessage());
                return $this->redirectToRoute('app_partida_preguntas_crear');
            }
        }

        return $this->render('juego/partida_preguntas/crear.html.twig');
    }

    #[Route('/juegos/partida-preguntas/{codigo}', name: 'app_partida_preguntas_unirse', methods: ['GET', 'POST'])]
    public function unirse(string $codigo, Request $request, SessionInterface $session): Response
    {
        $partida = $this->partidaService->obtenerPartida($codigo);
        
        if (!$partida) {
            $this->addFlash('error', 'Partida no encontrada o ha expirado');
            return $this->redirectToRoute('app_partida_preguntas_index');
        }

        if ($partida['estado'] === 'finalizada') {
            return $this->redirectToRoute('app_partida_preguntas_finalizado', ['codigo' => $codigo]);
        }

        // Verificar si ya está unido
        $participanteId = $session->get('participante_id_' . $codigo);
        if ($participanteId && isset($partida['participantes'][$participanteId])) {
            return $this->redirectToRoute('app_partida_preguntas_jugar', ['codigo' => $codigo]);
        }

        if ($request->isMethod('POST')) {
            $nombre = trim($request->request->get('nombre', ''));
            
            if (empty($nombre) || strlen($nombre) > 50) {
                $this->addFlash('error', 'El nombre debe tener entre 1 y 50 caracteres');
                return $this->render('juego/partida_preguntas/unirse.html.twig', [
                    'codigo' => $codigo,
                    'partida' => $partida,
                ]);
            }

            try {
                $participanteId = $this->partidaService->agregarParticipante($codigo, $nombre);
                $session->set('participante_id_' . $codigo, $participanteId);
                return $this->redirectToRoute('app_partida_preguntas_jugar', ['codigo' => $codigo]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al unirse a la partida: ' . $e->getMessage());
            }
        }

        return $this->render('juego/partida_preguntas/unirse.html.twig', [
            'codigo' => $codigo,
            'partida' => $partida,
        ]);
    }

    #[Route('/juegos/partida-preguntas/{codigo}/jugar', name: 'app_partida_preguntas_jugar', methods: ['GET'])]
    public function jugar(string $codigo, SessionInterface $session): Response
    {
        $partida = $this->partidaService->obtenerPartida($codigo);
        
        if (!$partida) {
            $this->addFlash('error', 'Partida no encontrada o ha expirado');
            return $this->redirectToRoute('app_partida_preguntas_index');
        }

        $participanteId = $session->get('participante_id_' . $codigo);
        if (!$participanteId || !isset($partida['participantes'][$participanteId])) {
            return $this->redirectToRoute('app_partida_preguntas_unirse', ['codigo' => $codigo]);
        }

        // Si la partida está finalizada, redirigir a página de resultados
        if ($partida['estado'] === 'finalizada') {
            return $this->redirectToRoute('app_partida_preguntas_finalizado', ['codigo' => $codigo]);
        }

        return $this->render('juego/partida_preguntas/jugar.html.twig', [
            'codigo' => $codigo,
            'partida' => $partida,
            'participanteId' => $participanteId,
        ]);
    }

    #[Route('/juegos/partida-preguntas/{codigo}/iniciar', name: 'app_partida_preguntas_iniciar', methods: ['POST'])]
    public function iniciar(string $codigo, SessionInterface $session): JsonResponse
    {
        $participanteId = $session->get('participante_id_' . $codigo);
        if (!$participanteId) {
            return new JsonResponse(['error' => 'No estás unido a esta partida'], 403);
        }

        try {
            $this->partidaService->iniciarJuego($codigo, $participanteId);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/juegos/partida-preguntas/{codigo}/responder', name: 'app_partida_preguntas_responder', methods: ['POST'])]
    public function responder(string $codigo, Request $request, SessionInterface $session): JsonResponse
    {
        $participanteId = $session->get('participante_id_' . $codigo);
        if (!$participanteId) {
            return new JsonResponse(['error' => 'No estás unido a esta partida'], 403);
        }

        $preguntaId = $request->request->getInt('preguntaId');
        $respuesta = $request->request->get('respuesta'); // Puede ser null para en blanco

        if ($preguntaId <= 0) {
            return new JsonResponse(['error' => 'ID de pregunta inválido'], 400);
        }

        // Validar que la respuesta sea A, B, C, D o null
        if ($respuesta !== null && !in_array($respuesta, ['A', 'B', 'C', 'D'])) {
            return new JsonResponse(['error' => 'Respuesta inválida'], 400);
        }

        try {
            $this->partidaService->guardarRespuesta($codigo, $participanteId, $preguntaId, $respuesta);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/juegos/partida-preguntas/{codigo}/finalizar', name: 'app_partida_preguntas_finalizar', methods: ['POST'])]
    public function finalizar(string $codigo, SessionInterface $session): JsonResponse
    {
        $participanteId = $session->get('participante_id_' . $codigo);
        if (!$participanteId) {
            return new JsonResponse(['error' => 'No estás unido a esta partida'], 403);
        }

        try {
            $resultado = $this->partidaService->finalizarPartida($codigo, $participanteId);
            return new JsonResponse($resultado);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/partida-preguntas/{codigo}/ranking', name: 'app_api_partida_preguntas_ranking', methods: ['GET'])]
    public function obtenerRanking(string $codigo): JsonResponse
    {
        $ranking = $this->partidaService->obtenerRanking($codigo);
        return new JsonResponse($ranking);
    }

    #[Route('/api/partida-preguntas/{codigo}/estado', name: 'app_api_partida_preguntas_estado', methods: ['GET'])]
    public function obtenerEstado(string $codigo): JsonResponse
    {
        $estado = $this->partidaService->obtenerEstado($codigo);
        return new JsonResponse($estado);
    }

    #[Route('/api/partida-preguntas/{codigo}/preguntas', name: 'app_api_partida_preguntas_preguntas', methods: ['GET'])]
    public function obtenerPreguntas(string $codigo): JsonResponse
    {
        try {
            $partida = $this->partidaService->obtenerPartida($codigo);
            if (!$partida) {
                return new JsonResponse(['error' => 'Partida no encontrada'], 404);
            }

            $preguntas = $this->partidaService->obtenerPreguntas($codigo);
            if (empty($preguntas)) {
                return new JsonResponse(['error' => 'No hay preguntas disponibles para esta partida'], 404);
            }

            return new JsonResponse($preguntas);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al obtener preguntas: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/juegos/partida-preguntas/{codigo}/compartir', name: 'app_partida_preguntas_compartir', methods: ['GET'])]
    public function compartir(string $codigo, Request $request): Response
    {
        $partida = $this->partidaService->obtenerPartida($codigo);
        
        if (!$partida) {
            $this->addFlash('error', 'Partida no encontrada o ha expirado');
            return $this->redirectToRoute('app_partida_preguntas_index');
        }

        // Generar URL completa para compartir
        $urlCompartir = $request->getSchemeAndHttpHost() . $this->generateUrl('app_partida_preguntas_unirse', ['codigo' => $codigo]);

        return $this->render('juego/partida_preguntas/compartir.html.twig', [
            'codigo' => $codigo,
            'partida' => $partida,
            'urlCompartir' => $urlCompartir,
        ]);
    }

    #[Route('/juegos/partida-preguntas/{codigo}/finalizado', name: 'app_partida_preguntas_finalizado', methods: ['GET'])]
    public function finalizado(string $codigo, SessionInterface $session): Response
    {
        $partida = $this->partidaService->obtenerPartida($codigo);
        
        if (!$partida) {
            $this->addFlash('error', 'Partida no encontrada o ha expirado');
            return $this->redirectToRoute('app_partida_preguntas_index');
        }

        // Asegurar que todos los participantes estén finalizados si la partida está finalizada
        if ($partida['estado'] === 'finalizada') {
            $this->partidaService->verificarTiempoLimite($codigo);
            $partida = $this->partidaService->obtenerPartida($codigo); // Re-obtener
        }

        $participanteId = $session->get('participante_id_' . $codigo);
        $ranking = $this->partidaService->obtenerRanking($codigo);
        
        // Obtener preguntas con respuestas si hay participante
        $preguntasConRespuestas = [];
        if ($participanteId) {
            $preguntasConRespuestas = $this->partidaService->obtenerPreguntasConRespuestas($codigo, $participanteId);
        }

        return $this->render('juego/partida_preguntas/finalizado.html.twig', [
            'codigo' => $codigo,
            'partida' => $partida,
            'participanteId' => $participanteId,
            'ranking' => $ranking,
            'preguntasConRespuestas' => $preguntasConRespuestas,
        ]);
    }
}

