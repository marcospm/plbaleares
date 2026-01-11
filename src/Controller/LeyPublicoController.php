<?php

namespace App\Controller;

use App\Repository\LeyRepository;
use App\Service\BoeLeyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/leyes')]
class LeyPublicoController extends AbstractController
{
    #[Route('/', name: 'app_ley_publico_index', methods: ['GET'])]
    public function index(
        LeyRepository $leyRepository,
        BoeLeyService $boeLeyService,
        Request $request
    ): Response {
        // Solo leyes activas, excluyendo "Accidentes de Tráfico" ya que no es una ley en sí mismo
        $leyes = array_filter($leyRepository->findAll(), function($ley) {
            return $ley->isActivo() && $ley->getNombre() !== 'Accidentes de Tráfico';
        });

        // Obtener información del BOE para cada ley
        $leyesConBoe = [];
        foreach ($leyes as $ley) {
            $infoBoe = $boeLeyService->getInfoLey($ley->getId());
            $leyesConBoe[] = [
                'ley' => $ley,
                'boe_link' => $infoBoe['boe_link'],
                'ultima_actualizacion' => $infoBoe['ultima_actualizacion'],
                'tiene_link' => $infoBoe['tiene_link'],
                'articulos_afectados' => $infoBoe['articulos_afectados'],
            ];
        }

        // Filtro por año de última actualización
        $filtrarPorAno = $request->query->getBoolean('filtrar_ano', false);
        $anoFiltro = $request->query->getInt('ano', date('Y'));
        
        if ($filtrarPorAno && $anoFiltro > 0) {
            $leyesConBoe = array_filter($leyesConBoe, function($item) use ($anoFiltro) {
                if ($item['ultima_actualizacion']) {
                    return (int)$item['ultima_actualizacion']->format('Y') === $anoFiltro;
                }
                return false;
            });
        }

        // Ordenar por nombre
        usort($leyesConBoe, function($a, $b) {
            return strcmp($a['ley']->getNombre(), $b['ley']->getNombre());
        });

        // Obtener años disponibles (año actual y anterior)
        $anoActual = (int)date('Y');
        $anoAnterior = $anoActual - 1;
        // Ordenar: año actual primero, luego el anterior
        $anosDisponibles = [$anoActual, $anoAnterior];

        return $this->render('ley/publico_index.html.twig', [
            'leyesConBoe' => $leyesConBoe,
            'filtrarPorAno' => $filtrarPorAno,
            'anoFiltro' => $anoFiltro,
            'anosDisponibles' => $anosDisponibles,
            'anoActual' => $anoActual,
        ]);
    }

    #[Route('/{id}', name: 'app_ley_publico_show', methods: ['GET'])]
    public function show(
        int $id,
        LeyRepository $leyRepository,
        BoeLeyService $boeLeyService
    ): Response {
        $ley = $leyRepository->find($id);

        if (!$ley || !$ley->isActivo() || $ley->getNombre() === 'Accidentes de Tráfico') {
            throw $this->createNotFoundException('Ley no encontrada o no disponible');
        }

        $infoBoe = $boeLeyService->getInfoLey($ley->getId());

        return $this->render('ley/publico_show.html.twig', [
            'ley' => $ley,
            'boe_link' => $infoBoe['boe_link'],
            'ultima_actualizacion' => $infoBoe['ultima_actualizacion'],
            'tiene_link' => $infoBoe['tiene_link'],
            'articulos_afectados' => $infoBoe['articulos_afectados'],
        ]);
    }
}

