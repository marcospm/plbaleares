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
        
        // Solo artículos activos
        $articulos = array_filter($articuloRepository->findAll(), function($articulo) {
            return $articulo->isActivo() && $articulo->getLey() && $articulo->getLey()->isActivo();
        });

        // Filtrar por ley si se selecciona
        if ($leyId > 0) {
            $articulos = array_filter($articulos, function($articulo) use ($leyId) {
                return $articulo->getLey() && $articulo->getLey()->getId() === $leyId;
            });
        }

        // Filtrar por búsqueda
        if (!empty($search)) {
            $articulos = array_filter($articulos, function($articulo) use ($search) {
                return stripos($articulo->getNumero(), $search) !== false ||
                       stripos($articulo->getExplicacion() ?? '', $search) !== false ||
                       ($articulo->getLey() && stripos($articulo->getLey()->getNombre(), $search) !== false);
            });
        }

        return $this->render('articulo/publico_index.html.twig', [
            'articulos' => $articulos,
            'leyes' => $leyes,
            'leySeleccionada' => $leyId,
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_articulo_publico_show', methods: ['GET'])]
    public function show(int $id, ArticuloRepository $articuloRepository): Response
    {
        $articulo = $articuloRepository->find($id);

        if (!$articulo || !$articulo->isActivo() || !$articulo->getLey() || !$articulo->getLey()->isActivo()) {
            throw $this->createNotFoundException('Artículo no encontrado o no disponible');
        }

        return $this->render('articulo/publico_show.html.twig', [
            'articulo' => $articulo,
        ]);
    }
}

