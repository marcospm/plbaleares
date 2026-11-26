<?php

namespace App\Controller;

use App\Entity\Flashcard;
use App\Entity\User;
use App\Repository\FlashcardRepository;
use App\Repository\PartidaJuegoRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gamificacion')]
class GamificacionController extends AbstractController
{
    public function __construct(
        private PartidaJuegoRepository $partidaJuegoRepository,
        private FlashcardRepository $flashcardRepository,
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
            'articulo_correcto' => 'El artículo correcto',
            'pena_articulo' => '¿Multa o cárcel?',
            'tablas_multiplicar' => 'Tablas de multiplicar',
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
            'articulo_correcto' => 'El artículo correcto',
            'pena_articulo' => '¿Multa o cárcel?',
            'tablas_multiplicar' => 'Tablas de multiplicar',
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
            'articulo_correcto' => 'El artículo correcto',
            'pena_articulo' => '¿Multa o cárcel?',
            'tablas_multiplicar' => 'Tablas de multiplicar',
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
        $rankingFlashcards = $this->flashcardRepository->rankingPorAlumno($alumnosIds);
        $partidasPorUsuario = $this->incorporarFlashcards($partidasPorUsuario, $rankingFlashcards);

        // Obtener rankings por tipo de juego (ya filtrado en el repositorio)
        $rankings = [];
        foreach ($tiposJuegos as $tipo => $nombre) {
            $rankingFiltrado = $this->partidaJuegoRepository->getRankingPorTipoJuego($tipo, $alumnosIds);
            
            $rankings[$tipo] = [
                'nombre' => $nombre,
                'ranking' => $rankingFiltrado,
                'unidad' => 'Partidas',
            ];
        }
        $rankings['flashcards'] = [
            'nombre' => 'Flashcards guardadas',
            'ranking' => $rankingFlashcards,
            'unidad' => 'Tarjetas',
        ];

        return $this->render('gamificacion/admin.html.twig', [
            'partidasPorUsuario' => $partidasPorUsuario,
            'rankings' => $rankings,
            'tiposJuegos' => $tiposJuegos,
            'tipoJuegoSeleccionado' => $tipoJuegoSeleccionado,
            'esAdmin' => $esAdmin,
        ]);
    }

    #[Route('/admin/flashcards/{id}', name: 'app_gamificacion_admin_flashcards', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_PROFESOR')]
    public function flashcardsAlumno(#[MapEntity(id: 'id')] User $alumno): Response
    {
        if (!$this->puedeVerAlumno($alumno)) {
            throw $this->createAccessDeniedException();
        }

        $flashcards = $this->flashcardRepository->findPropias($alumno);

        return $this->render('gamificacion/flashcards_alumno.html.twig', [
            'alumno' => $alumno,
            'grupos' => $this->agruparFlashcards($flashcards),
            'total' => count($flashcards),
        ]);
    }

    /**
     * @param list<array{usuario: \App\Entity\User, partidas: array<string, int>}> $partidasPorUsuario
     * @param list<array{usuario: \App\Entity\User, cantidad: int}> $rankingFlashcards
     * @return list<array{usuario: \App\Entity\User, partidas: array<string, int>, flashcards: int}>
     */
    private function incorporarFlashcards(array $partidasPorUsuario, array $rankingFlashcards): array
    {
        $porId = [];
        foreach ($partidasPorUsuario as $entry) {
            $id = $entry['usuario']->getId();
            if ($id === null) {
                continue;
            }
            $entry['flashcards'] = 0;
            $porId[$id] = $entry;
        }

        foreach ($rankingFlashcards as $entry) {
            $id = $entry['usuario']->getId();
            if ($id === null) {
                continue;
            }
            if (!isset($porId[$id])) {
                $porId[$id] = [
                    'usuario' => $entry['usuario'],
                    'partidas' => [],
                    'flashcards' => $entry['cantidad'],
                ];
                continue;
            }
            $porId[$id]['flashcards'] = $entry['cantidad'];
        }

        $listado = array_values($porId);
        usort($listado, static function (array $a, array $b): int {
            return strcasecmp($a['usuario']->getNombreDisplay(), $b['usuario']->getNombreDisplay());
        });

        return $listado;
    }

    private function puedeVerAlumno(User $alumno): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $profesor = $this->getUser();
        if (!$profesor instanceof User) {
            return false;
        }

        foreach ($profesor->getAlumnos() as $asignado) {
            if ($asignado->getId() === $alumno->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Flashcard[] $flashcards
     * @return list<array{nombre: string, flashcards: Flashcard[]}>
     */
    private function agruparFlashcards(array $flashcards): array
    {
        $grupos = [];
        foreach ($flashcards as $flashcard) {
            $clave = $flashcard->getClaveTema();
            if (!isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'nombre' => $flashcard->getNombreTema(),
                    'flashcards' => [],
                ];
            }
            $grupos[$clave]['flashcards'][] = $flashcard;
        }

        uasort($grupos, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));

        return array_values($grupos);
    }
}
