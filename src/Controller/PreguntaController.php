<?php

namespace App\Controller;

use App\Entity\Pregunta;
use App\Form\PreguntaType;
use App\Repository\PreguntaRepository;
use App\Repository\MensajePreguntaRepository;
use App\Repository\TemaRepository;
use App\Repository\LeyRepository;
use App\Repository\ArticuloRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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
            return $this->redirectToRoute('app_pregunta_show', ['id' => $pregunta->getId()], Response::HTTP_SEE_OTHER);
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

    #[Route('/api/leyes-por-tema/{temaId}', name: 'app_pregunta_api_leyes_por_tema', methods: ['GET'], requirements: ['temaId' => '\d+'])]
    public function getLeyesPorTema(int $temaId, LeyRepository $leyRepository): JsonResponse
    {
        $tema = $leyRepository->getEntityManager()->getRepository(\App\Entity\Tema::class)->find($temaId);
        
        if (!$tema) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        // Obtener todas las leyes relacionadas con este tema
        $leyes = $tema->getLeyes()->toArray();
        
        $leyesData = array_map(function($ley) {
            return [
                'id' => $ley->getId(),
                'nombre' => $ley->getNombre(),
            ];
        }, $leyes);

        return new JsonResponse($leyesData);
    }

    #[Route('/api/articulos-por-ley/{leyId}', name: 'app_pregunta_api_articulos_por_ley', methods: ['GET'], requirements: ['leyId' => '\d+'])]
    public function getArticulosPorLey(int $leyId, ArticuloRepository $articuloRepository): JsonResponse
    {
        $ley = $articuloRepository->getEntityManager()->getRepository(\App\Entity\Ley::class)->find($leyId);
        
        if (!$ley) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        // Obtener todos los artículos de esta ley, ordenados por número
        $articulos = $articuloRepository->findBy(
            ['ley' => $ley],
            ['numero' => 'ASC', 'sufijo' => 'ASC']
        );
        
        $articulosData = array_map(function($articulo) {
            $label = 'Art. ' . $articulo->getNumeroCompleto();
            if ($articulo->getNombre()) {
                $label .= ' - ' . $articulo->getNombre();
            }
            return [
                'id' => $articulo->getId(),
                'numero' => $articulo->getNumero(),
                'numeroCompleto' => $articulo->getNumeroCompleto(),
                'nombre' => $articulo->getNombre(),
                'label' => $label,
            ];
        }, $articulos);

        return new JsonResponse($articulosData);
    }

    #[Route('/new-csv', name: 'app_pregunta_new_csv', methods: ['GET'])]
    public function newCsv(
        TemaRepository $temaRepository,
        LeyRepository $leyRepository,
        ArticuloRepository $articuloRepository
    ): Response {
        // Optimizar consultas con ordenamiento
        $temas = $temaRepository->createQueryBuilder('t')
            ->orderBy('t.nombre', 'ASC')
            ->getQuery()
            ->getResult();
        
        $leyes = $leyRepository->createQueryBuilder('l')
            ->orderBy('l.nombre', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('pregunta/new_csv.html.twig', [
            'temas' => $temas,
            'leyes' => $leyes,
        ]);
    }

    #[Route('/descargar-csv-ejemplo', name: 'app_pregunta_descargar_csv_ejemplo', methods: ['GET'])]
    public function descargarCsvEjemplo(): Response
    {
        $contenido = "id,texto,opcion_a,opcion_b,opcion_c,opcion_d,respuesta_correcta,retroalimentacion,tema_id,ley_id,articulo_id,dificultad\n";
        $contenido .= ",\"¿Cuál es la capital de España?\",\"Madrid\",\"Barcelona\",\"Valencia\",\"Sevilla\",\"A\",\"Madrid es la capital de España desde 1561.\",1,1,1,\"facil\"\n";
        $contenido .= ",\"¿Cuántas comunidades autónomas tiene España?\",\"15\",\"17\",\"19\",\"21\",\"B\",\"España tiene 17 comunidades autónomas y 2 ciudades autónomas.\",1,1,1,\"moderada\"\n";

        $response = new Response($contenido);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="ejemplo_preguntas.csv"');

        return $response;
    }

    #[Route('/procesar-csv', name: 'app_pregunta_procesar_csv', methods: ['POST'])]
    public function procesarCsv(
        Request $request,
        TemaRepository $temaRepository,
        LeyRepository $leyRepository,
        ArticuloRepository $articuloRepository
    ): Response {
        $temaId = $request->request->getInt('tema', 0);
        $leyId = $request->request->getInt('ley', 0);
        $articuloId = $request->request->getInt('articulo', 0);
        $dificultad = $request->request->get('dificultad', '');

        // Validar campos requeridos
        if ($temaId <= 0 || $leyId <= 0 || $articuloId <= 0 || empty($dificultad)) {
            $this->addFlash('error', 'Debes completar todos los campos: Tema, Ley, Artículo y Dificultad.');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        $tema = $temaRepository->find($temaId);
        $ley = $leyRepository->find($leyId);
        $articulo = $articuloRepository->find($articuloId);

        if (!$tema || !$ley || !$articulo) {
            $this->addFlash('error', 'Uno o más campos seleccionados no son válidos.');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        /** @var UploadedFile|null $archivo */
        $archivo = $request->files->get('archivo_csv');

        if (!$archivo) {
            $this->addFlash('error', 'Debes seleccionar un archivo CSV.');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        // Validar extensión
        $extension = strtolower($archivo->getClientOriginalExtension());
        if ($extension !== 'csv') {
            $this->addFlash('error', 'El archivo debe ser un CSV (extensión .csv).');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        // Validar tamaño del archivo (máximo 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($archivo->getSize() > $maxSize) {
            $this->addFlash('error', 'El archivo CSV no puede ser mayor a 5MB.');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        // Leer y parsear CSV de forma eficiente
        $contenido = file_get_contents($archivo->getPathname());
        
        // Detectar encoding y convertir a UTF-8 si es necesario
        $encoding = mb_detect_encoding($contenido, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', $encoding);
        }
        
        $lineas = str_getcsv($contenido, "\n");
        
        // Filtrar líneas vacías
        $lineas = array_filter($lineas, function($linea) {
            return trim($linea) !== '';
        });
        $lineas = array_values($lineas); // Reindexar
        
        if (count($lineas) < 2) {
            $this->addFlash('error', 'El archivo CSV debe tener al menos una fila de datos (además del encabezado).');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        // Limitar número máximo de preguntas (1000 por seguridad)
        $maxPreguntas = 1000;
        if (count($lineas) > $maxPreguntas + 1) {
            $this->addFlash('error', "El archivo CSV no puede tener más de {$maxPreguntas} preguntas. Por favor, divide el archivo en partes más pequeñas.");
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        // Procesar encabezado
        $encabezado = str_getcsv(array_shift($lineas));
        $encabezado = array_map('trim', $encabezado);
        $encabezado = array_map('strtolower', $encabezado);

        $camposRequeridos = ['texto', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta_correcta'];
        $camposOpcionales = ['id', 'retroalimentacion'];

        // Validar encabezado
        foreach ($camposRequeridos as $campo) {
            if (!in_array($campo, $encabezado)) {
                $this->addFlash('error', "El archivo CSV debe contener la columna: {$campo}");
                return $this->redirectToRoute('app_pregunta_new_csv');
            }
        }

        // Crear mapeo de índices
        $indices = [];
        foreach ($encabezado as $idx => $col) {
            $indices[$col] = $idx;
        }

        // Procesar preguntas
        $preguntas = [];
        $errores = [];

        foreach ($lineas as $numLinea => $linea) {
            $numLinea = $numLinea + 2; // +2 porque empezamos desde la línea 2 (después del encabezado)
            
            // Saltar líneas vacías
            if (trim($linea) === '') {
                continue;
            }
            
            $datos = str_getcsv($linea);

            if (count($datos) < count($camposRequeridos)) {
                $errores[] = "Línea {$numLinea}: No tiene suficientes columnas (esperadas: " . count($camposRequeridos) . ", encontradas: " . count($datos) . ").";
                continue;
            }

            // ID es opcional - si está vacío o no existe, será null
            $idPregunta = null;
            if (isset($indices['id'])) {
                $idValue = trim($datos[$indices['id']] ?? '');
                if (!empty($idValue) && is_numeric($idValue)) {
                    $idPregunta = (int)$idValue;
                }
            }

            $texto = trim($datos[$indices['texto']] ?? '');
            $opcionA = trim($datos[$indices['opcion_a']] ?? '');
            $opcionB = trim($datos[$indices['opcion_b']] ?? '');
            $opcionC = trim($datos[$indices['opcion_c']] ?? '');
            $opcionD = trim($datos[$indices['opcion_d']] ?? '');
            $respuestaCorrecta = strtoupper(trim($datos[$indices['respuesta_correcta']] ?? ''));
            $retroalimentacion = trim($datos[$indices['retroalimentacion']] ?? '');

            // Validar campos requeridos
            if (empty($texto)) {
                $errores[] = "Línea {$numLinea}: El texto de la pregunta es requerido.";
                continue;
            }

            if (empty($opcionA) || empty($opcionB) || empty($opcionC) || empty($opcionD)) {
                $errores[] = "Línea {$numLinea}: Todas las opciones (A, B, C, D) son requeridas.";
                continue;
            }

            if (!in_array($respuestaCorrecta, ['A', 'B', 'C', 'D'])) {
                $errores[] = "Línea {$numLinea}: La respuesta correcta debe ser A, B, C o D.";
                continue;
            }

            $preguntas[] = [
                'id' => $idPregunta,
                'texto' => $texto,
                'opcionA' => $opcionA,
                'opcionB' => $opcionB,
                'opcionC' => $opcionC,
                'opcionD' => $opcionD,
                'respuestaCorrecta' => $respuestaCorrecta,
                'retroalimentacion' => $retroalimentacion,
                'linea' => $numLinea,
            ];
        }

        if (!empty($errores)) {
            $mensajeErrores = implode("\n", array_slice($errores, 0, 10));
            if (count($errores) > 10) {
                $mensajeErrores .= "\n... y " . (count($errores) - 10) . " errores más.";
            }
            $this->addFlash('error', "Errores encontrados en el CSV:\n" . $mensajeErrores);
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        if (empty($preguntas)) {
            $this->addFlash('error', 'No se encontraron preguntas válidas en el archivo CSV.');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        // Guardar datos en sesión para la confirmación
        $request->getSession()->set('preguntas_csv', $preguntas);
        $request->getSession()->set('preguntas_csv_tema', $temaId);
        $request->getSession()->set('preguntas_csv_ley', $leyId);
        $request->getSession()->set('preguntas_csv_articulo', $articuloId);
        $request->getSession()->set('preguntas_csv_dificultad', $dificultad);

        return $this->render('pregunta/preview_csv.html.twig', [
            'preguntas' => $preguntas,
            'tema' => $tema,
            'ley' => $ley,
            'articulo' => $articulo,
            'dificultad' => $dificultad,
            'total' => count($preguntas),
        ]);
    }

    #[Route('/confirmar-crear-csv', name: 'app_pregunta_confirmar_crear_csv', methods: ['POST'])]
    public function confirmarCrearCsv(
        Request $request,
        EntityManagerInterface $entityManager,
        TemaRepository $temaRepository,
        LeyRepository $leyRepository,
        ArticuloRepository $articuloRepository
    ): Response {
        // Validar token CSRF
        if (!$this->isCsrfTokenValid('confirmar_csv', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido. Por favor, intenta de nuevo.');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        $session = $request->getSession();
        
        $preguntas = $session->get('preguntas_csv', []);
        $temaId = $session->get('preguntas_csv_tema', 0);
        $leyId = $session->get('preguntas_csv_ley', 0);
        $articuloId = $session->get('preguntas_csv_articulo', 0);
        $dificultad = $session->get('preguntas_csv_dificultad', '');

        if (empty($preguntas) || $temaId <= 0 || $leyId <= 0 || $articuloId <= 0 || empty($dificultad)) {
            $this->addFlash('error', 'Sesión expirada. Por favor, vuelve a cargar el CSV.');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        $tema = $temaRepository->find($temaId);
        $ley = $leyRepository->find($leyId);
        $articulo = $articuloRepository->find($articuloId);

        if (!$tema || !$ley || !$articulo) {
            $this->addFlash('error', 'Uno o más campos seleccionados no son válidos.');
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        $creadas = 0;
        $actualizadas = 0;
        $errores = [];
        $batchSize = 100; // Procesar en lotes de 100 para mejor performance

        // Usar transacción para asegurar atomicidad
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        
        // Obtener repositorio de preguntas
        $preguntaRepository = $entityManager->getRepository(Pregunta::class);
        
        try {
            foreach ($preguntas as $index => $preguntaData) {
                try {
                    $pregunta = null;
                    $esActualizacion = false;

                    // Si hay ID, intentar buscar la pregunta existente
                    if (!empty($preguntaData['id'])) {
                        $pregunta = $preguntaRepository->find($preguntaData['id']);
                        if ($pregunta) {
                            $esActualizacion = true;
                        }
                    }

                    // Si no existe, crear nueva
                    if (!$pregunta) {
                        $pregunta = new Pregunta();
                    }

                    // Actualizar o establecer valores
                    $pregunta->setTexto($preguntaData['texto']);
                    $pregunta->setOpcionA($preguntaData['opcionA']);
                    $pregunta->setOpcionB($preguntaData['opcionB']);
                    $pregunta->setOpcionC($preguntaData['opcionC']);
                    $pregunta->setOpcionD($preguntaData['opcionD']);
                    $pregunta->setRespuestaCorrecta($preguntaData['respuestaCorrecta']);
                    $pregunta->setRetroalimentacion($preguntaData['retroalimentacion'] ?: null);
                    $pregunta->setDificultad($dificultad);
                    $pregunta->setTema($tema);
                    $pregunta->setLey($ley);
                    $pregunta->setArticulo($articulo);
                    
                    // Solo establecer activo si es nueva pregunta
                    if (!$esActualizacion) {
                        $pregunta->setActivo(true);
                    }

                    $entityManager->persist($pregunta);
                    
                    if ($esActualizacion) {
                        $actualizadas++;
                    } else {
                        $creadas++;
                    }

                    // Flush en lotes para mejor performance
                    if (($index + 1) % $batchSize === 0) {
                        $entityManager->flush();
                    }
                } catch (\Exception $e) {
                    $errores[] = "Línea {$preguntaData['linea']}: " . $e->getMessage();
                }
            }

            // Flush final para las preguntas restantes
            $entityManager->flush();
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->addFlash('error', 'Error al guardar las preguntas: ' . $e->getMessage());
            return $this->redirectToRoute('app_pregunta_new_csv');
        }

        // Limpiar sesión
        $session->remove('preguntas_csv');
        $session->remove('preguntas_csv_tema');
        $session->remove('preguntas_csv_ley');
        $session->remove('preguntas_csv_articulo');
        $session->remove('preguntas_csv_dificultad');

        if (!empty($errores)) {
            $mensajeErrores = implode("\n", array_slice($errores, 0, 5));
            $mensaje = "Se procesaron {$creadas} preguntas nuevas";
            if ($actualizadas > 0) {
                $mensaje .= " y se actualizaron {$actualizadas} preguntas existentes";
            }
            $mensaje .= ", pero hubo algunos errores:\n" . $mensajeErrores;
            $this->addFlash('warning', $mensaje);
        } else {
            $mensaje = "Se crearon correctamente {$creadas} preguntas nuevas";
            if ($actualizadas > 0) {
                $mensaje .= " y se actualizaron {$actualizadas} preguntas existentes";
            }
            $mensaje .= ".";
            $this->addFlash('success', $mensaje);
        }

        return $this->redirectToRoute('app_pregunta_index');
    }

    #[Route('/descargar-por-tema', name: 'app_pregunta_descargar_por_tema', methods: ['GET'])]
    public function descargarPorTema(
        PreguntaRepository $preguntaRepository,
        TemaRepository $temaRepository,
        Request $request
    ): Response {
        $temaId = $request->query->getInt('tema', 0);
        
        if ($temaId <= 0) {
            $this->addFlash('error', 'Debes seleccionar un tema para descargar las preguntas.');
            return $this->redirectToRoute('app_pregunta_index');
        }

        $tema = $temaRepository->find($temaId);
        if (!$tema) {
            $this->addFlash('error', 'Tema no encontrado.');
            return $this->redirectToRoute('app_pregunta_index');
        }

        // Obtener todas las preguntas del tema (sin paginación)
        $preguntas = $preguntaRepository->findBy(
            ['tema' => $tema],
            ['id' => 'ASC']
        );

        // Función auxiliar para escapar valores CSV
        $escapeCsv = function($value) {
            if ($value === null || $value === '') {
                return '';
            }
            // Convertir a string por si acaso
            $value = (string)$value;
            // Verificar si necesita ser envuelto en comillas (contiene comas, saltos de línea o comillas)
            $needsQuotes = strpos($value, ',') !== false || strpos($value, "\n") !== false || strpos($value, "\r") !== false || strpos($value, '"') !== false;
            if ($needsQuotes) {
                // Escapar comillas dobles y envolver en comillas
                $value = str_replace('"', '""', $value);
                return '"' . $value . '"';
            }
            return $value;
        };

        // Generar CSV con todas las columnas necesarias para actualización
        $contenido = "id,texto,opcion_a,opcion_b,opcion_c,opcion_d,respuesta_correcta,retroalimentacion,tema_id,ley_id,articulo_id,dificultad\n";

        foreach ($preguntas as $pregunta) {
            $id = $pregunta->getId();
            $texto = strip_tags($pregunta->getTexto());
            $opcionA = strip_tags($pregunta->getOpcionA());
            $opcionB = strip_tags($pregunta->getOpcionB());
            $opcionC = strip_tags($pregunta->getOpcionC());
            $opcionD = strip_tags($pregunta->getOpcionD());
            $respuestaCorrecta = strtoupper($pregunta->getRespuestaCorrecta());
            $retroalimentacion = $pregunta->getRetroalimentacion() ? strip_tags($pregunta->getRetroalimentacion()) : '';
            $temaId = $pregunta->getTema() ? $pregunta->getTema()->getId() : '';
            $leyId = $pregunta->getLey() ? $pregunta->getLey()->getId() : '';
            $articuloId = $pregunta->getArticulo() ? $pregunta->getArticulo()->getId() : '';
            $dificultad = $pregunta->getDificultad();

            $contenido .= $id . ',';
            $contenido .= $escapeCsv($texto) . ',';
            $contenido .= $escapeCsv($opcionA) . ',';
            $contenido .= $escapeCsv($opcionB) . ',';
            $contenido .= $escapeCsv($opcionC) . ',';
            $contenido .= $escapeCsv($opcionD) . ',';
            $contenido .= $escapeCsv($respuestaCorrecta) . ',';
            $contenido .= $escapeCsv($retroalimentacion) . ',';
            $contenido .= $temaId . ',';
            $contenido .= $leyId . ',';
            $contenido .= $articuloId . ',';
            $contenido .= $escapeCsv($dificultad) . "\n";
        }

        // Crear respuesta con el archivo CSV
        $nombreArchivo = 'preguntas_tema_' . $tema->getId() . '_' . date('Y-m-d') . '.csv';
        $nombreArchivo = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreArchivo);

        $response = new Response($contenido);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"');

        return $response;
    }

    #[Route('/actualizar-csv', name: 'app_pregunta_actualizar_csv', methods: ['GET'])]
    public function actualizarCsv(): Response
    {
        return $this->render('pregunta/actualizar_csv.html.twig');
    }

    #[Route('/procesar-actualizar-csv', name: 'app_pregunta_procesar_actualizar_csv', methods: ['POST'])]
    public function procesarActualizarCsv(
        Request $request,
        PreguntaRepository $preguntaRepository,
        TemaRepository $temaRepository,
        LeyRepository $leyRepository,
        ArticuloRepository $articuloRepository
    ): Response {
        /** @var UploadedFile|null $archivo */
        $archivo = $request->files->get('archivo_csv');

        if (!$archivo) {
            $this->addFlash('error', 'Debes seleccionar un archivo CSV.');
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        // Validar extensión
        $extension = strtolower($archivo->getClientOriginalExtension());
        if ($extension !== 'csv') {
            $this->addFlash('error', 'El archivo debe ser un CSV (extensión .csv).');
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        // Validar tamaño del archivo (máximo 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($archivo->getSize() > $maxSize) {
            $this->addFlash('error', 'El archivo CSV no puede ser mayor a 5MB.');
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        // Leer y parsear CSV de forma eficiente
        $contenido = file_get_contents($archivo->getPathname());
        
        // Detectar encoding y convertir a UTF-8 si es necesario
        $encoding = mb_detect_encoding($contenido, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', $encoding);
        }
        
        $lineas = str_getcsv($contenido, "\n");
        
        // Filtrar líneas vacías
        $lineas = array_filter($lineas, function($linea) {
            return trim($linea) !== '';
        });
        $lineas = array_values($lineas); // Reindexar
        
        if (count($lineas) < 2) {
            $this->addFlash('error', 'El archivo CSV debe tener al menos una fila de datos (además del encabezado).');
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        // Limitar número máximo de preguntas (1000 por seguridad)
        $maxPreguntas = 1000;
        if (count($lineas) > $maxPreguntas + 1) {
            $this->addFlash('error', "El archivo CSV no puede tener más de {$maxPreguntas} preguntas. Por favor, divide el archivo en partes más pequeñas.");
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        // Procesar encabezado
        $encabezado = str_getcsv(array_shift($lineas));
        $encabezado = array_map('trim', $encabezado);
        $encabezado = array_map('strtolower', $encabezado);

        $camposRequeridos = ['id', 'texto', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta_correcta', 'tema_id', 'ley_id', 'articulo_id', 'dificultad'];
        $camposOpcionales = ['retroalimentacion'];

        // Validar encabezado
        foreach ($camposRequeridos as $campo) {
            if (!in_array($campo, $encabezado)) {
                $this->addFlash('error', "El archivo CSV debe contener la columna: {$campo}");
                return $this->redirectToRoute('app_pregunta_actualizar_csv');
            }
        }

        // Crear mapeo de índices
        $indices = [];
        foreach ($encabezado as $idx => $col) {
            $indices[$col] = $idx;
        }

        // Procesar preguntas
        $preguntas = [];
        $errores = [];

        foreach ($lineas as $numLinea => $linea) {
            $numLinea = $numLinea + 2; // +2 porque empezamos desde la línea 2 (después del encabezado)
            
            // Saltar líneas vacías
            if (trim($linea) === '') {
                continue;
            }
            
            $datos = str_getcsv($linea);

            if (count($datos) < count($camposRequeridos)) {
                $errores[] = "Línea {$numLinea}: No tiene suficientes columnas (esperadas: " . count($camposRequeridos) . ", encontradas: " . count($datos) . ").";
                continue;
            }

            // ID es requerido para actualización
            $idValue = trim($datos[$indices['id']] ?? '');
            if (empty($idValue) || !is_numeric($idValue)) {
                $errores[] = "Línea {$numLinea}: El ID es requerido y debe ser numérico.";
                continue;
            }
            $idPregunta = (int)$idValue;

            $texto = trim($datos[$indices['texto']] ?? '');
            $opcionA = trim($datos[$indices['opcion_a']] ?? '');
            $opcionB = trim($datos[$indices['opcion_b']] ?? '');
            $opcionC = trim($datos[$indices['opcion_c']] ?? '');
            $opcionD = trim($datos[$indices['opcion_d']] ?? '');
            $respuestaCorrecta = strtoupper(trim($datos[$indices['respuesta_correcta']] ?? ''));
            $retroalimentacion = trim($datos[$indices['retroalimentacion']] ?? '');
            $temaId = trim($datos[$indices['tema_id']] ?? '');
            $leyId = trim($datos[$indices['ley_id']] ?? '');
            $articuloId = trim($datos[$indices['articulo_id']] ?? '');
            $dificultad = trim($datos[$indices['dificultad']] ?? '');

            // Validar campos requeridos
            if (empty($texto)) {
                $errores[] = "Línea {$numLinea}: El texto de la pregunta es requerido.";
                continue;
            }

            if (empty($opcionA) || empty($opcionB) || empty($opcionC) || empty($opcionD)) {
                $errores[] = "Línea {$numLinea}: Todas las opciones (A, B, C, D) son requeridas.";
                continue;
            }

            if (!in_array($respuestaCorrecta, ['A', 'B', 'C', 'D'])) {
                $errores[] = "Línea {$numLinea}: La respuesta correcta debe ser A, B, C o D.";
                continue;
            }

            if (empty($temaId) || !is_numeric($temaId)) {
                $errores[] = "Línea {$numLinea}: El tema_id es requerido y debe ser numérico.";
                continue;
            }

            if (empty($leyId) || !is_numeric($leyId)) {
                $errores[] = "Línea {$numLinea}: El ley_id es requerido y debe ser numérico.";
                continue;
            }

            if (empty($articuloId) || !is_numeric($articuloId)) {
                $errores[] = "Línea {$numLinea}: El articulo_id es requerido y debe ser numérico.";
                continue;
            }

            if (empty($dificultad)) {
                $errores[] = "Línea {$numLinea}: La dificultad es requerida.";
                continue;
            }

            $preguntas[] = [
                'id' => $idPregunta,
                'texto' => $texto,
                'opcionA' => $opcionA,
                'opcionB' => $opcionB,
                'opcionC' => $opcionC,
                'opcionD' => $opcionD,
                'respuestaCorrecta' => $respuestaCorrecta,
                'retroalimentacion' => $retroalimentacion,
                'temaId' => (int)$temaId,
                'leyId' => (int)$leyId,
                'articuloId' => (int)$articuloId,
                'dificultad' => $dificultad,
                'linea' => $numLinea,
            ];
        }

        if (!empty($errores)) {
            $mensajeErrores = implode("\n", array_slice($errores, 0, 10));
            if (count($errores) > 10) {
                $mensajeErrores .= "\n... y " . (count($errores) - 10) . " errores más.";
            }
            $this->addFlash('error', "Errores encontrados en el CSV:\n" . $mensajeErrores);
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        if (empty($preguntas)) {
            $this->addFlash('error', 'No se encontraron preguntas válidas en el archivo CSV.');
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        // Validar que las preguntas existan (guardar solo IDs para evitar problemas de serialización)
        $preguntasValidadas = [];
        foreach ($preguntas as $preguntaData) {
            $pregunta = $preguntaRepository->find($preguntaData['id']);
            if (!$pregunta) {
                $errores[] = "Línea {$preguntaData['linea']}: La pregunta con ID {$preguntaData['id']} no existe.";
                continue;
            }

            $tema = $temaRepository->find($preguntaData['temaId']);
            $ley = $leyRepository->find($preguntaData['leyId']);
            $articulo = $articuloRepository->find($preguntaData['articuloId']);

            if (!$tema || !$ley || !$articulo) {
                $errores[] = "Línea {$preguntaData['linea']}: Uno o más IDs (tema, ley, artículo) no son válidos.";
                continue;
            }

            // Guardar solo los datos y IDs, no las entidades (para evitar problemas de serialización)
            $preguntasValidadas[] = [
                'preguntaId' => $preguntaData['id'],
                'data' => $preguntaData,
                'temaId' => $preguntaData['temaId'],
                'leyId' => $preguntaData['leyId'],
                'articuloId' => $preguntaData['articuloId'],
            ];
        }

        if (!empty($errores)) {
            $mensajeErrores = implode("\n", array_slice($errores, 0, 10));
            if (count($errores) > 10) {
                $mensajeErrores .= "\n... y " . (count($errores) - 10) . " errores más.";
            }
            $this->addFlash('error', "Errores encontrados:\n" . $mensajeErrores);
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        // Guardar datos en sesión para la confirmación (solo IDs y datos, no entidades)
        $request->getSession()->set('preguntas_actualizar_csv', $preguntasValidadas);

        // Cargar entidades completas solo para la previsualización
        $preguntasParaPreview = [];
        foreach ($preguntasValidadas as $item) {
            $pregunta = $preguntaRepository->find($item['preguntaId']);
            $tema = $temaRepository->find($item['temaId']);
            $ley = $leyRepository->find($item['leyId']);
            $articulo = $articuloRepository->find($item['articuloId']);

            $preguntasParaPreview[] = [
                'pregunta' => $pregunta,
                'data' => $item['data'],
                'tema' => $tema,
                'ley' => $ley,
                'articulo' => $articulo,
            ];
        }

        return $this->render('pregunta/preview_actualizar_csv.html.twig', [
            'preguntas' => $preguntasParaPreview,
            'total' => count($preguntasParaPreview),
        ]);
    }

    #[Route('/confirmar-actualizar-csv', name: 'app_pregunta_confirmar_actualizar_csv', methods: ['POST'])]
    public function confirmarActualizarCsv(
        Request $request,
        EntityManagerInterface $entityManager,
        PreguntaRepository $preguntaRepository,
        TemaRepository $temaRepository,
        LeyRepository $leyRepository,
        ArticuloRepository $articuloRepository
    ): Response {
        // Validar token CSRF
        if (!$this->isCsrfTokenValid('actualizar_csv', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido. Por favor, intenta de nuevo.');
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        $session = $request->getSession();
        $preguntasValidadas = $session->get('preguntas_actualizar_csv', []);

        if (empty($preguntasValidadas)) {
            $this->addFlash('error', 'Sesión expirada. Por favor, vuelve a cargar el CSV.');
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        $actualizadas = 0;
        $errores = [];
        $batchSize = 100;

        // Usar transacción para asegurar atomicidad
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        
        try {
            foreach ($preguntasValidadas as $index => $item) {
                try {
                    // Re-cargar entidades desde la base de datos para evitar problemas de serialización
                    $pregunta = $preguntaRepository->find($item['preguntaId']);
                    if (!$pregunta) {
                        $errores[] = "Línea {$item['data']['linea']}: La pregunta con ID {$item['preguntaId']} no existe.";
                        continue;
                    }

                    $tema = $temaRepository->find($item['temaId']);
                    $ley = $leyRepository->find($item['leyId']);
                    $articulo = $articuloRepository->find($item['articuloId']);

                    if (!$tema || !$ley || !$articulo) {
                        $errores[] = "Línea {$item['data']['linea']}: Uno o más IDs (tema, ley, artículo) no son válidos.";
                        continue;
                    }

                    $data = $item['data'];

                    // Actualizar valores
                    $pregunta->setTexto($data['texto']);
                    $pregunta->setOpcionA($data['opcionA']);
                    $pregunta->setOpcionB($data['opcionB']);
                    $pregunta->setOpcionC($data['opcionC']);
                    $pregunta->setOpcionD($data['opcionD']);
                    $pregunta->setRespuestaCorrecta($data['respuestaCorrecta']);
                    $pregunta->setRetroalimentacion($data['retroalimentacion'] ?: null);
                    $pregunta->setDificultad($data['dificultad']);
                    $pregunta->setTema($tema);
                    $pregunta->setLey($ley);
                    $pregunta->setArticulo($articulo);

                    $entityManager->persist($pregunta);
                    $actualizadas++;

                    // Flush en lotes
                    if (($index + 1) % $batchSize === 0) {
                        $entityManager->flush();
                    }
                } catch (\Exception $e) {
                    $errores[] = "Línea {$item['data']['linea']}: " . $e->getMessage();
                }
            }

            // Flush final
            $entityManager->flush();
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->addFlash('error', 'Error al actualizar las preguntas: ' . $e->getMessage());
            return $this->redirectToRoute('app_pregunta_actualizar_csv');
        }

        // Limpiar sesión
        $session->remove('preguntas_actualizar_csv');

        if (!empty($errores)) {
            $mensajeErrores = implode("\n", array_slice($errores, 0, 5));
            $this->addFlash('warning', "Se actualizaron {$actualizadas} preguntas, pero hubo algunos errores:\n" . $mensajeErrores);
        } else {
            $this->addFlash('success', "Se actualizaron correctamente {$actualizadas} preguntas.");
        }

        return $this->redirectToRoute('app_pregunta_index');
    }
}

