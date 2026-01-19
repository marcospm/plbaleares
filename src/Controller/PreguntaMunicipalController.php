<?php

namespace App\Controller;

use App\Entity\PreguntaMunicipal;
use App\Form\PreguntaMunicipalType;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\TemaMunicipalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pregunta-municipal')]
#[IsGranted('ROLE_PROFESOR')]
class PreguntaMunicipalController extends AbstractController
{
    #[Route('/', name: 'app_pregunta_municipal_index', methods: ['GET'])]
    public function index(
        PreguntaMunicipalRepository $preguntaMunicipalRepository,
        MunicipioRepository $municipioRepository,
        TemaMunicipalRepository $temaMunicipalRepository,
        Request $request
    ): Response {
        $search = trim($request->query->get('search', ''));
        $municipioId = $request->query->getInt('municipio');
        $temaId = $request->query->getInt('tema');
        $dificultad = $request->query->get('dificultad', '');
        $mostrarDescartadas = $request->query->getBoolean('mostrar_descartadas', false);

        // Parámetros de paginación
        $itemsPerPage = 20;
        $page = max(1, $request->query->getInt('page', 1));

        // Obtener preguntas según el filtro de activas/descartadas
        if ($mostrarDescartadas) {
            // Mostrar todas las preguntas (activas y descartadas)
            $preguntas = $preguntaMunicipalRepository->findAll();
        } else {
            // Por defecto, solo mostrar preguntas activas
            $preguntas = $preguntaMunicipalRepository->findBy(['activo' => true]);
        }
        
        // Convertir a array indexado numéricamente
        $preguntas = array_values($preguntas);

        if ($municipioId > 0) {
            $municipio = $municipioRepository->find($municipioId);
            if ($municipio) {
                $preguntas = array_values(array_filter($preguntas, function($p) use ($municipio) {
                    return $p->getMunicipio()->getId() === $municipio->getId();
                }));
            }
        }

        if ($temaId > 0) {
            $preguntas = array_values(array_filter($preguntas, function($p) use ($temaId) {
                return $p->getTemaMunicipal() && $p->getTemaMunicipal()->getId() === $temaId;
            }));
        }

        if (!empty($dificultad)) {
            $preguntas = array_values(array_filter($preguntas, function($p) use ($dificultad) {
                return $p->getDificultad() === $dificultad;
            }));
        }

        if (!empty($search)) {
            $preguntas = array_values(array_filter($preguntas, function($pregunta) use ($search) {
                $textoMatch = stripos($pregunta->getTexto() ?? '', $search) !== false;
                $retroMatch = stripos($pregunta->getRetroalimentacion() ?? '', $search) !== false;
                return $textoMatch || $retroMatch;
            }));
        }

        // Calcular paginación
        $totalItems = count($preguntas);
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);
        
        // Obtener los items de la página actual
        $offset = ($page - 1) * $itemsPerPage;
        $preguntasPaginated = array_slice($preguntas, $offset, $itemsPerPage);

        // Obtener temas municipales para el filtro (si hay municipio seleccionado, solo de ese municipio)
        $temasMunicipales = [];
        if ($municipioId > 0) {
            $municipio = $municipioRepository->find($municipioId);
            if ($municipio) {
                $temasMunicipales = $temaMunicipalRepository->findByMunicipio($municipio);
            }
        } else {
            $temasMunicipales = $temaMunicipalRepository->findAll();
        }

        return $this->render('pregunta_municipal/index.html.twig', [
            'preguntas' => $preguntasPaginated,
            'municipios' => $municipioRepository->findAll(),
            'temasMunicipales' => $temasMunicipales,
            'municipioSeleccionado' => $municipioId,
            'temaSeleccionado' => $temaId,
            'dificultadSeleccionada' => $dificultad,
            'search' => $search,
            'mostrarDescartadas' => $mostrarDescartadas,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
        ]);
    }

    #[Route('/new', name: 'app_pregunta_municipal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, MunicipioRepository $municipioRepository): Response
    {
        $preguntaMunicipal = new PreguntaMunicipal();
        $municipioId = $request->query->getInt('municipio');
        $municipio = $municipioId > 0 ? $municipioRepository->find($municipioId) : null;
        
        $form = $this->createForm(PreguntaMunicipalType::class, $preguntaMunicipal, [
            'municipio' => $municipio,
            'is_new' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($preguntaMunicipal);
            $entityManager->flush();

            $this->addFlash('success', 'Pregunta municipal creada correctamente.');
            return $this->redirectToRoute('app_pregunta_municipal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pregunta_municipal/new.html.twig', [
            'pregunta_municipal' => $preguntaMunicipal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_pregunta_municipal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PreguntaMunicipal $preguntaMunicipal, EntityManagerInterface $entityManager, MunicipioRepository $municipioRepository): Response
    {
        // Si hay un municipio en la URL, usarlo; si no, usar el municipio de la pregunta
        $municipioId = $request->query->getInt('municipio');
        $municipio = $municipioId > 0 ? $municipioRepository->find($municipioId) : $preguntaMunicipal->getMunicipio();
        
        $form = $this->createForm(PreguntaMunicipalType::class, $preguntaMunicipal, ['municipio' => $municipio]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Pregunta municipal actualizada correctamente.');
            return $this->redirectToRoute('app_pregunta_municipal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pregunta_municipal/edit.html.twig', [
            'pregunta_municipal' => $preguntaMunicipal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_pregunta_municipal_show', methods: ['GET'], requirements: ['id' => '\d+'], priority: -1)]
    public function show(PreguntaMunicipal $preguntaMunicipal, Request $request): Response
    {
        // Obtener parámetros de filtro de la query string para mantenerlos al volver
        $filtros = [];
        if ($request->query->get('search')) {
            $filtros['search'] = $request->query->get('search');
        }
        if ($request->query->getInt('municipio') > 0) {
            $filtros['municipio'] = $request->query->getInt('municipio');
        }
        if ($request->query->getInt('tema') > 0) {
            $filtros['tema'] = $request->query->getInt('tema');
        }
        if ($request->query->get('dificultad')) {
            $filtros['dificultad'] = $request->query->get('dificultad');
        }

        return $this->render('pregunta_municipal/show.html.twig', [
            'pregunta_municipal' => $preguntaMunicipal,
            'filtros' => $filtros,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_pregunta_municipal_toggle_activo', methods: ['POST'])]
    public function toggleActivo(PreguntaMunicipal $preguntaMunicipal, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$preguntaMunicipal->getId(), $request->getPayload()->getString('_token'))) {
            $preguntaMunicipal->setActivo(!$preguntaMunicipal->isActivo());
            $entityManager->flush();

            $estado = $preguntaMunicipal->isActivo() ? 'activada' : 'desactivada';
            $this->addFlash('success', "La pregunta municipal ha sido {$estado} correctamente.");
        }

        // Preservar filtros y página al redirigir (del POST)
        $params = [];
        $payload = $request->getPayload();
        if ($payload->get('search')) {
            $params['search'] = $payload->get('search');
        }
        if ($payload->getInt('municipio') > 0) {
            $params['municipio'] = $payload->getInt('municipio');
        }
        if ($payload->getInt('tema') > 0) {
            $params['tema'] = $payload->getInt('tema');
        }
        if ($payload->get('dificultad')) {
            $params['dificultad'] = $payload->get('dificultad');
        }
        if ($payload->getInt('page') > 1) {
            $params['page'] = $payload->getInt('page');
        }

        return $this->redirectToRoute('app_pregunta_municipal_index', $params, Response::HTTP_SEE_OTHER);
    }
}






