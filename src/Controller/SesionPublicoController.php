<?php

namespace App\Controller;

use App\Entity\Sesion;
use App\Repository\SesionRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\ConvocatoriaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sesiones')]
class SesionPublicoController extends AbstractController
{
    #[Route('/', name: 'app_sesion_publico_index', methods: ['GET'])]
    public function index(
        SesionRepository $sesionRepository,
        TemaRepository $temaRepository,
        TemaMunicipalRepository $temaMunicipalRepository,
        MunicipioRepository $municipioRepository,
        ConvocatoriaRepository $convocatoriaRepository,
        Request $request
    ): Response {
        $user = $this->getUser();
        
        // Obtener filtros de la petición
        $search = $request->query->get('search', '');
        $temaId = $request->query->get('tema', '');
        $temaId = $temaId !== '' && $temaId !== null ? (int)$temaId : null;
        $temaMunicipalId = $request->query->get('tema_municipal', '');
        $temaMunicipalId = $temaMunicipalId !== '' && $temaMunicipalId !== null ? (int)$temaMunicipalId : null;
        $municipioId = $request->query->get('municipio', '');
        $municipioId = $municipioId !== '' && $municipioId !== null ? (int)$municipioId : null;
        $convocatoriaId = $request->query->get('convocatoria', '');
        $convocatoriaId = $convocatoriaId !== '' && $convocatoriaId !== null ? (int)$convocatoriaId : null;
        
        // Parámetros de paginación
        $itemsPerPage = 12;
        $page = max(1, $request->query->getInt('page', 1));

        // Obtener sesiones con paginación y filtros
        $result = $sesionRepository->findPaginatedForAlumno(
            $search ?: null,
            $temaId,
            $temaMunicipalId,
            $municipioId,
            $convocatoriaId,
            $page,
            $itemsPerPage
        );
        $sesiones = $result['sesiones'];
        $totalItems = $result['total'];
        
        // Calcular total de páginas
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);

        // Obtener datos para los filtros - todos los selectores deben estar rellenados de antemano
        $temas = $temaRepository->findBy(['activo' => true], ['id' => 'ASC']);
        $municipios = $municipioRepository->findBy(['activo' => true], ['nombre' => 'ASC']);
        $convocatorias = [];
        
        if ($user) {
            $convocatorias = $convocatoriaRepository->findByUsuario($user);
        }
        
        // Obtener TODOS los temas municipales activos (no solo los del municipio seleccionado)
        // Esto permite que el selector esté rellenado desde el inicio
        $temasMunicipales = $temaMunicipalRepository->findBy(
            ['activo' => true],
            ['nombre' => 'ASC']
        );

        return $this->render('sesion/publico_index.html.twig', [
            'sesiones' => $sesiones,
            'search' => $search,
            'temaId' => $temaId,
            'temaMunicipalId' => $temaMunicipalId,
            'municipioId' => $municipioId,
            'convocatoriaId' => $convocatoriaId,
            'temas' => $temas,
            'temasMunicipales' => $temasMunicipales,
            'municipios' => $municipios,
            'convocatorias' => $convocatorias,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
        ]);
    }

    #[Route('/{id}', name: 'app_sesion_publico_show', methods: ['GET'])]
    public function show(Sesion $sesion): Response
    {
        return $this->render('sesion/publico_show.html.twig', [
            'sesion' => $sesion,
        ]);
    }
}
