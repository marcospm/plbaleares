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

        // Parámetros de paginación
        $itemsPerPage = 20; // Número de artículos por página
        $page = max(1, $request->query->getInt('page', 1));

        // Solo leyes activas
        $leyes = $leyRepository->findActivasOrderedByNombre();
        
        // Obtener total de artículos activos con filtros (sin cargar entidades)
        $totalItems = $articuloRepository->countActivosOrdenadosPorNumero(
            $leyId > 0 ? $leyId : null,
            !empty($search) ? $search : null
        );

        // Calcular paginación
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages); // Asegurar que la página no exceda el total
        
        // Obtener los items de la página actual usando paginación SQL
        $offset = ($page - 1) * $itemsPerPage;
        $articulosPaginated = $articuloRepository->findActivosOrdenadosPorNumeroPaginated(
            $leyId > 0 ? $leyId : null,
            !empty($search) ? $search : null,
            $offset,
            $itemsPerPage
        );

        return $this->render('articulo/publico_index.html.twig', [
            'articulos' => $articulosPaginated,
            'leyes' => $leyes,
            'leySeleccionada' => $leyId,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
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
        $page = $request->query->getInt('page', 1);

        return $this->render('articulo/publico_show.html.twig', [
            'articulo' => $articulo,
            'leySeleccionada' => $leyId,
            'search' => $search,
            'page' => $page,
        ]);
    }
}

