<?php

namespace App\Controller;

use App\Repository\PartidaJuegoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gamificacion')]
class GamificacionController extends AbstractController
{
    public function __construct(
        private PartidaJuegoRepository $partidaJuegoRepository
    ) {
    }

    #[Route('/historial', name: 'app_gamificacion_historial', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function historial(Request $request): Response
    {
        $user = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');
        $esProfesor = $this->isGranted('ROLE_PROFESOR');

        // Si es profesor o admin, redirigir a la vista de administración
        if ($esAdmin || $esProfesor) {
            return $this->redirectToRoute('app_gamificacion_admin');
        }

        // Obtener conteo por tipo de juego
        $conteoPorTipo = $this->partidaJuegoRepository->getConteoPorTipoJuego($user);

        // Obtener historial reciente (últimas 50 partidas)
        $historial = $this->partidaJuegoRepository->getHistorialUsuario($user, 50);

        // Tipos de juegos disponibles
        $tiposJuegos = [
            'adivina_numero_articulo' => '¿Qué Número Tiene el Artículo?',
            'adivina_nombre_articulo' => '¿Cómo se Llama el Artículo?',
            'completa_fecha_ley' => '¿Cuándo se Publicó la Ley?',
            'completa_texto_legal' => 'Completa el Artículo',
        ];

        return $this->render('gamificacion/historial.html.twig', [
            'conteoPorTipo' => $conteoPorTipo,
            'historial' => $historial,
            'tiposJuegos' => $tiposJuegos,
        ]);
    }

    #[Route('/ranking', name: 'app_gamificacion_ranking', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function ranking(Request $request): Response
    {
        $tipoJuego = $request->query->get('juego', 'adivina_numero_articulo');

        // Validar tipo de juego
        $tiposJuegos = [
            'adivina_numero_articulo' => '¿Qué Número Tiene el Artículo?',
            'adivina_nombre_articulo' => '¿Cómo se Llama el Artículo?',
            'completa_fecha_ley' => '¿Cuándo se Publicó la Ley?',
            'completa_texto_legal' => 'Completa el Artículo',
        ];

        if (!isset($tiposJuegos[$tipoJuego])) {
            $tipoJuego = 'adivina_numero_articulo';
        }

        // Obtener ranking (ya filtrado en el repositorio)
        $rankingFiltrado = $this->partidaJuegoRepository->getRankingPorTipoJuego($tipoJuego);

        // Obtener posición del usuario actual (si es alumno)
        $posicionUsuario = null;
        $user = $this->getUser();
        if ($user && !$this->isGranted('ROLE_PROFESOR') && !$this->isGranted('ROLE_ADMIN')) {
            foreach ($rankingFiltrado as $index => $entry) {
                if ($entry['usuario']->getId() === $user->getId()) {
                    $posicionUsuario = $index + 1;
                    break;
                }
            }
        }

        return $this->render('gamificacion/ranking.html.twig', [
            'ranking' => $rankingFiltrado,
            'tipoJuego' => $tipoJuego,
            'nombreJuego' => $tiposJuegos[$tipoJuego],
            'tiposJuegos' => $tiposJuegos,
            'posicionUsuario' => $posicionUsuario,
        ]);
    }

    #[Route('/admin', name: 'app_gamificacion_admin', methods: ['GET'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function admin(Request $request): Response
    {
        $user = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');

        // Tipos de juegos disponibles
        $tiposJuegos = [
            'adivina_numero_articulo' => '¿Qué Número Tiene el Artículo?',
            'adivina_nombre_articulo' => '¿Cómo se Llama el Artículo?',
            'completa_fecha_ley' => '¿Cuándo se Publicó la Ley?',
            'completa_texto_legal' => 'Completa el Artículo',
        ];

        // Obtener tipo de juego seleccionado
        $tipoJuegoSeleccionado = $request->query->get('juego');

        // Obtener IDs de alumnos asignados si no es admin
        $alumnosIds = null;
        if (!$esAdmin) {
            $alumnosIds = array_map(function($alumno) {
                return $alumno->getId();
            }, $user->getAlumnos()->toArray());
            
            if (empty($alumnosIds)) {
                $alumnosIds = [-1]; // ID que no existe para que no muestre nada
            }
        }

        // Obtener partidas por usuario (ya filtrado en el repositorio)
        $partidasPorUsuario = $this->partidaJuegoRepository->getPartidasPorUsuario($alumnosIds);

        // Obtener rankings por tipo de juego (ya filtrado en el repositorio)
        $rankings = [];
        foreach ($tiposJuegos as $tipo => $nombre) {
            $rankingFiltrado = $this->partidaJuegoRepository->getRankingPorTipoJuego($tipo, $alumnosIds);
            
            $rankings[$tipo] = [
                'nombre' => $nombre,
                'ranking' => $rankingFiltrado,
            ];
        }

        return $this->render('gamificacion/admin.html.twig', [
            'partidasPorUsuario' => $partidasPorUsuario,
            'rankings' => $rankings,
            'tiposJuegos' => $tiposJuegos,
            'tipoJuegoSeleccionado' => $tipoJuegoSeleccionado,
            'esAdmin' => $esAdmin,
        ]);
    }
}
