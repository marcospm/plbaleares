<?php

namespace App\Controller;

use App\Repository\ArticuloRepository;
use App\Repository\LeyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/articulos')]
class ArticuloPublicoController extends AbstractController
{
    #[Route('/', name: 'app_articulo_publico_index', methods: ['GET'])]
    public function index(
        ArticuloRepository $articuloRepository,
        LeyRepository $leyRepository,
        Request $request
    ): Response {
        $leyId = $request->query->getInt('ley');
        $search = $request->query->get('search', '');

        // Solo leyes activas
        $leyes = array_filter($leyRepository->findAll(), function($ley) {
            return $ley->isActivo();
        });
        
        // Obtener artículos activos ordenados por número, con filtros aplicados
        $articulos = $articuloRepository->findActivosOrdenadosPorNumero(
            $leyId > 0 ? $leyId : null,
            !empty($search) ? $search : null
        );

        return $this->render('articulo/publico_index.html.twig', [
            'articulos' => $articulos,
            'leyes' => $leyes,
            'leySeleccionada' => $leyId,
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_articulo_publico_show', methods: ['GET'])]
    public function show(int $id, ArticuloRepository $articuloRepository, Request $request): Response
    {
        $articulo = $articuloRepository->find($id);

        if (!$articulo || !$articulo->isActivo() || !$articulo->getLey() || !$articulo->getLey()->isActivo()) {
            throw $this->createNotFoundException('Artículo no encontrado o no disponible');
        }

        // Obtener parámetros de filtro de la query string para mantenerlos al volver
        $leyId = $request->query->getInt('ley');
        $search = $request->query->get('search', '');

        return $this->render('articulo/publico_show.html.twig', [
            'articulo' => $articulo,
            'leySeleccionada' => $leyId,
            'search' => $search,
        ]);
    }
}

