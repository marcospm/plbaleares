<?php

namespace App\Controller;

use App\Entity\Pregunta;
use App\Form\PreguntaType;
use App\Repository\PreguntaRepository;
use App\Repository\MensajePreguntaRepository;
use App\Repository\TemaRepository;
use App\Repository\LeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pregunta')]
#[IsGranted('ROLE_PROFESOR')]
class PreguntaController extends AbstractController
{
    #[Route('/', name: 'app_pregunta_index', methods: ['GET'])]
    public function index(
        PreguntaRepository $preguntaRepository,
        TemaRepository $temaRepository,
        LeyRepository $leyRepository,
        Request $request
    ): Response {
        // Obtener parámetros de la query string
        $search = trim($request->query->get('search', ''));
        $temaId = $request->query->getInt('tema', 0);
        $leyId = $request->query->getInt('ley', 0);
        $dificultad = trim($request->query->get('dificultad', ''));
        $numeroArticulo = $request->query->getInt('articulo', 0);
        $mostrarDescartadas = $request->query->getBoolean('mostrar_descartadas', false);
        
        // Parámetros de paginación
        $itemsPerPage = 20; // Número de preguntas por página
        $page = max(1, $request->query->getInt('page', 1));

        // Obtener preguntas según el filtro de activas/descartadas
        if ($mostrarDescartadas) {
            // Mostrar todas las preguntas (activas y descartadas)
            $preguntas = $preguntaRepository->findAll();
        } else {
            // Por defecto, solo mostrar preguntas activas
            $preguntas = $preguntaRepository->findBy(['activo' => true]);
        }
        
        // Convertir a array indexado numéricamente
        $preguntas = array_values($preguntas);

        // Aplicar filtros secuencialmente
        if ($temaId > 0) {
            $preguntas = array_values(array_filter($preguntas, function($pregunta) use ($temaId) {
                $tema = $pregunta->getTema();
                return $tema !== null && (int)$tema->getId() === (int)$temaId;
            }));
        }

        if ($leyId > 0) {
            $preguntas = array_values(array_filter($preguntas, function($pregunta) use ($leyId) {
                $ley = $pregunta->getLey();
                return $ley !== null && (int)$ley->getId() === (int)$leyId;
            }));
        }

        if (!empty($dificultad)) {
            $preguntas = array_values(array_filter($preguntas, function($pregunta) use ($dificultad) {
                return $pregunta->getDificultad() === $dificultad;
            }));
        }

        if ($numeroArticulo > 0) {
            $preguntas = array_values(array_filter($preguntas, function($pregunta) use ($numeroArticulo) {
                return $pregunta->getArticulo() !== null && $pregunta->getArticulo()->getNumero() === $numeroArticulo;
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
        $page = min($page, $totalPages); // Asegurar que la página no exceda el total
        
        // Obtener los items de la página actual
        $offset = ($page - 1) * $itemsPerPage;
        $preguntasPaginated = array_slice($preguntas, $offset, $itemsPerPage);

        // Obtener listas para los filtros
        $temas = $temaRepository->findAll();
        $leyes = $leyRepository->findAll();

        return $this->render('pregunta/index.html.twig', [
            'preguntas' => $preguntasPaginated,
            'temas' => $temas,
            'leyes' => $leyes,
            'search' => $search,
            'temaSeleccionado' => $temaId,
            'leySeleccionada' => $leyId,
            'dificultadSeleccionada' => $dificultad,
            'numeroArticuloSeleccionado' => $numeroArticulo,
            'mostrarDescartadas' => $mostrarDescartadas,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
        ]);
    }

    #[Route('/new', name: 'app_pregunta_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $pregunta = new Pregunta();
        $form = $this->createForm(PreguntaType::class, $pregunta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($pregunta);
            $entityManager->flush();

            $this->addFlash('success', 'Pregunta creada correctamente.');
            return $this->redirectToRoute('app_pregunta_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pregunta/new.html.twig', [
            'pregunta' => $pregunta,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_pregunta_show', methods: ['GET'], requirements: ['id' => '\d+'], priority: -1)]
    public function show(
        Pregunta $pregunta, 
        Request $request,
        MensajePreguntaRepository $mensajePreguntaRepository
    ): Response {
        // Obtener parámetros de filtro de la query string
        $filtros = [];
        if ($request->query->get('search')) {
            $filtros['search'] = $request->query->get('search');
        }
        if ($request->query->getInt('tema') > 0) {
            $filtros['tema'] = $request->query->getInt('tema');
        }
        if ($request->query->getInt('ley') > 0) {
            $filtros['ley'] = $request->query->getInt('ley');
        }
        if ($request->query->get('dificultad')) {
            $filtros['dificultad'] = $request->query->get('dificultad');
        }
        if ($request->query->getInt('articulo') > 0) {
            $filtros['articulo'] = $request->query->getInt('articulo');
        }
        // Mantener la página actual
        $page = $request->query->getInt('page', 1);
        if ($page > 1) {
            $filtros['page'] = $page;
        }

        // Obtener mensajes de la pregunta
        $mensajes = $mensajePreguntaRepository->findMensajesPrincipales($pregunta);
        $totalMensajes = $mensajePreguntaRepository->countMensajesPrincipales($pregunta);

        return $this->render('pregunta/show.html.twig', [
            'pregunta' => $pregunta,
            'filtros' => $filtros,
            'mensajes' => $mensajes,
            'totalMensajes' => $totalMensajes,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_pregunta_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Pregunta $pregunta, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PreguntaType::class, $pregunta);
        $form->handleRequest($request);

        // Obtener parámetros de filtro de la query string o del request anterior
        $filtros = [];
        $search = $request->query->get('search') ?? $request->request->get('filtro_search');
        $tema = $request->query->getInt('tema') ?: $request->request->getInt('filtro_tema', 0);
        $ley = $request->query->getInt('ley') ?: $request->request->getInt('filtro_ley', 0);
        $dificultad = $request->query->get('dificultad') ?? $request->request->get('filtro_dificultad');
        $articulo = $request->query->getInt('articulo') ?: $request->request->getInt('filtro_articulo', 0);
        
        if ($search) {
            $filtros['search'] = $search;
        }
        if ($tema > 0) {
            $filtros['tema'] = $tema;
        }
        if ($ley > 0) {
            $filtros['ley'] = $ley;
        }
        if ($dificultad) {
            $filtros['dificultad'] = $dificultad;
        }
        if ($articulo > 0) {
            $filtros['articulo'] = $articulo;
        }
        // Mantener la página actual
        $page = $request->query->getInt('page') ?: $request->request->getInt('filtro_page', 1);
        if ($page > 1) {
            $filtros['page'] = $page;
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Pregunta actualizada correctamente.');
            return $this->redirectToRoute('app_pregunta_index', $filtros, Response::HTTP_SEE_OTHER);
        }

        return $this->render('pregunta/edit.html.twig', [
            'pregunta' => $pregunta,
            'form' => $form,
            'filtros' => $filtros,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_pregunta_toggle_activo', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleActivo(Pregunta $pregunta, EntityManagerInterface $entityManager, Request $request): Response
    {
        // Obtener parámetros de filtro de la query string
        $filtros = [];
        if ($request->query->get('search')) {
            $filtros['search'] = $request->query->get('search');
        }
        if ($request->query->getInt('tema') > 0) {
            $filtros['tema'] = $request->query->getInt('tema');
        }
        if ($request->query->getInt('ley') > 0) {
            $filtros['ley'] = $request->query->getInt('ley');
        }
        if ($request->query->get('dificultad')) {
            $filtros['dificultad'] = $request->query->get('dificultad');
        }
        if ($request->query->getInt('articulo') > 0) {
            $filtros['articulo'] = $request->query->getInt('articulo');
        }
        // Mantener la página actual
        $page = $request->query->getInt('page', 1);
        if ($page > 1) {
            $filtros['page'] = $page;
        }

        if ($this->isCsrfTokenValid('toggle'.$pregunta->getId(), $request->getPayload()->getString('_token'))) {
            $pregunta->setActivo(!$pregunta->isActivo());
            $entityManager->flush();

            $estado = $pregunta->isActivo() ? 'activada' : 'desactivada';
            $this->addFlash('success', "La pregunta ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_pregunta_index', $filtros, Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_pregunta_delete', methods: ['POST'], requirements: ['id' => '\d+'], priority: -1)]
    public function delete(Request $request, Pregunta $pregunta, EntityManagerInterface $entityManager): Response
    {
        // Obtener parámetros de filtro de la query string
        $filtros = [];
        if ($request->query->get('search')) {
            $filtros['search'] = $request->query->get('search');
        }
        if ($request->query->getInt('tema') > 0) {
            $filtros['tema'] = $request->query->getInt('tema');
        }
        if ($request->query->getInt('ley') > 0) {
            $filtros['ley'] = $request->query->getInt('ley');
        }
        if ($request->query->get('dificultad')) {
            $filtros['dificultad'] = $request->query->get('dificultad');
        }
        if ($request->query->getInt('articulo') > 0) {
            $filtros['articulo'] = $request->query->getInt('articulo');
        }
        // Mantener la página actual
        $page = $request->query->getInt('page', 1);
        if ($page > 1) {
            $filtros['page'] = $page;
        }

        if ($this->isCsrfTokenValid('delete'.$pregunta->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($pregunta);
            $entityManager->flush();
            $this->addFlash('success', 'Pregunta eliminada correctamente.');
        }

        return $this->redirectToRoute('app_pregunta_index', $filtros, Response::HTTP_SEE_OTHER);
    }
}

