<?php

namespace App\Controller;

use App\Entity\PreguntaMunicipal;
use App\Form\PreguntaMunicipalType;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\TemaMunicipalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    #[Route('/api/temas-por-municipio/{municipioId}', name: 'app_pregunta_municipal_api_temas', methods: ['GET'], requirements: ['municipioId' => '\d+'])]
    public function getTemasPorMunicipio(int $municipioId, MunicipioRepository $municipioRepository, TemaMunicipalRepository $temaMunicipalRepository): JsonResponse
    {
        $municipio = $municipioRepository->find($municipioId);
        
        if (!$municipio) {
            return new JsonResponse(['error' => 'Municipio no encontrado'], 404);
        }

        $temas = $temaMunicipalRepository->findByMunicipio($municipio);
        
        $temasArray = array_map(function($tema) {
            return [
                'id' => $tema->getId(),
                'nombre' => $tema->getNombre(),
            ];
        }, $temas);

        return new JsonResponse(['temas' => $temasArray]);
    }

    #[Route('/descargar-por-tema', name: 'app_pregunta_municipal_descargar_por_tema', methods: ['GET'])]
    public function descargarPorTema(
        PreguntaMunicipalRepository $preguntaMunicipalRepository,
        TemaMunicipalRepository $temaMunicipalRepository,
        Request $request
    ): Response {
        $temaId = $request->query->getInt('tema', 0);
        
        if ($temaId <= 0) {
            $this->addFlash('error', 'Debes seleccionar un tema para descargar las preguntas.');
            return $this->redirectToRoute('app_pregunta_municipal_index');
        }

        $tema = $temaMunicipalRepository->find($temaId);
        if (!$tema) {
            $this->addFlash('error', 'Tema no encontrado.');
            return $this->redirectToRoute('app_pregunta_municipal_index');
        }

        // Obtener todas las preguntas del tema (sin paginación)
        $preguntas = $preguntaMunicipalRepository->findBy(
            ['temaMunicipal' => $tema],
            ['id' => 'ASC']
        );

        // Función auxiliar para escapar valores CSV
        $escapeCsv = function($value) {
            if ($value === null || $value === '') {
                return '';
            }
            $value = (string)$value;
            $needsQuotes = strpos($value, ',') !== false || strpos($value, "\n") !== false || strpos($value, "\r") !== false || strpos($value, '"') !== false;
            if ($needsQuotes) {
                $value = str_replace('"', '""', $value);
                return '"' . $value . '"';
            }
            return $value;
        };

        // Generar CSV con todas las columnas necesarias para actualización
        $contenido = "id,texto,opcion_a,opcion_b,opcion_c,opcion_d,respuesta_correcta,retroalimentacion,municipio_id,tema_municipal_id,dificultad\n";

        foreach ($preguntas as $pregunta) {
            $id = $pregunta->getId();
            $texto = strip_tags($pregunta->getTexto());
            $opcionA = strip_tags($pregunta->getOpcionA());
            $opcionB = strip_tags($pregunta->getOpcionB());
            $opcionC = strip_tags($pregunta->getOpcionC());
            $opcionD = strip_tags($pregunta->getOpcionD());
            $respuestaCorrecta = strtoupper($pregunta->getRespuestaCorrecta());
            $retroalimentacion = $pregunta->getRetroalimentacion() ? strip_tags($pregunta->getRetroalimentacion()) : '';
            $municipioId = $pregunta->getMunicipio() ? $pregunta->getMunicipio()->getId() : '';
            $temaMunicipalId = $pregunta->getTemaMunicipal() ? $pregunta->getTemaMunicipal()->getId() : '';
            $dificultad = $pregunta->getDificultad();

            $contenido .= $id . ',';
            $contenido .= $escapeCsv($texto) . ',';
            $contenido .= $escapeCsv($opcionA) . ',';
            $contenido .= $escapeCsv($opcionB) . ',';
            $contenido .= $escapeCsv($opcionC) . ',';
            $contenido .= $escapeCsv($opcionD) . ',';
            $contenido .= $escapeCsv($respuestaCorrecta) . ',';
            $contenido .= $escapeCsv($retroalimentacion) . ',';
            $contenido .= $municipioId . ',';
            $contenido .= $temaMunicipalId . ',';
            $contenido .= $escapeCsv($dificultad) . "\n";
        }

        // Crear respuesta con el archivo CSV
        $nombreArchivo = 'preguntas_municipales_tema_' . $tema->getId() . '_' . date('Y-m-d') . '.csv';
        $nombreArchivo = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreArchivo);

        $response = new Response($contenido);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"');

        return $response;
    }

    #[Route('/new-csv', name: 'app_pregunta_municipal_new_csv', methods: ['GET'])]
    public function newCsv(
        MunicipioRepository $municipioRepository,
        TemaMunicipalRepository $temaMunicipalRepository
    ): Response {
        // Optimizar consultas con ordenamiento
        $municipios = $municipioRepository->createQueryBuilder('m')
            ->orderBy('m.nombre', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('pregunta_municipal/new_csv.html.twig', [
            'municipios' => $municipios,
        ]);
    }

    #[Route('/descargar-csv-ejemplo', name: 'app_pregunta_municipal_descargar_csv_ejemplo', methods: ['GET'])]
    public function descargarCsvEjemplo(): Response
    {
        $contenido = "id,texto,opcion_a,opcion_b,opcion_c,opcion_d,respuesta_correcta,retroalimentacion,municipio_id,tema_municipal_id,dificultad\n";
        $contenido .= ",\"¿Cuál es la capital de las Islas Baleares?\",\"Palma\",\"Mahón\",\"Ibiza\",\"Ciutadella\",\"A\",\"Palma es la capital de las Islas Baleares.\",1,1,\"facil\"\n";
        $contenido .= ",\"¿Cuántos municipios tiene Mallorca?\",\"50\",\"52\",\"54\",\"56\",\"B\",\"Mallorca tiene 52 municipios.\",1,1,\"moderada\"\n";

        $response = new Response($contenido);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="ejemplo_preguntas_municipales.csv"');

        return $response;
    }

    #[Route('/procesar-csv', name: 'app_pregunta_municipal_procesar_csv', methods: ['POST'])]
    public function procesarCsv(
        Request $request,
        MunicipioRepository $municipioRepository,
        TemaMunicipalRepository $temaMunicipalRepository
    ): Response {
        $municipioId = $request->request->getInt('municipio', 0);
        $temaMunicipalId = $request->request->getInt('tema_municipal', 0);
        $dificultad = $request->request->get('dificultad', '');

        // Validar campos requeridos
        if ($municipioId <= 0 || $temaMunicipalId <= 0 || empty($dificultad)) {
            $this->addFlash('error', 'Debes completar todos los campos: Municipio, Tema Municipal y Dificultad.');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        $municipio = $municipioRepository->find($municipioId);
        $temaMunicipal = $temaMunicipalRepository->find($temaMunicipalId);

        if (!$municipio || !$temaMunicipal) {
            $this->addFlash('error', 'Uno o más campos seleccionados no son válidos.');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        /** @var UploadedFile|null $archivo */
        $archivo = $request->files->get('archivo_csv');

        if (!$archivo) {
            $this->addFlash('error', 'Debes seleccionar un archivo CSV.');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        // Validar extensión
        $extension = strtolower($archivo->getClientOriginalExtension());
        if ($extension !== 'csv') {
            $this->addFlash('error', 'El archivo debe ser un CSV (extensión .csv).');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        // Validar tamaño del archivo (máximo 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($archivo->getSize() > $maxSize) {
            $this->addFlash('error', 'El archivo CSV no puede ser mayor a 5MB.');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
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
        $lineas = array_values($lineas);
        
        if (count($lineas) < 2) {
            $this->addFlash('error', 'El archivo CSV debe tener al menos una fila de datos (además del encabezado).');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        // Limitar número máximo de preguntas (1000 por seguridad)
        $maxPreguntas = 1000;
        if (count($lineas) > $maxPreguntas + 1) {
            $this->addFlash('error', "El archivo CSV no puede tener más de {$maxPreguntas} preguntas. Por favor, divide el archivo en partes más pequeñas.");
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
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
                return $this->redirectToRoute('app_pregunta_municipal_new_csv');
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
            $numLinea = $numLinea + 2;
            
            // Saltar líneas vacías
            if (trim($linea) === '') {
                continue;
            }
            
            $datos = str_getcsv($linea);

            if (count($datos) < count($camposRequeridos)) {
                $errores[] = "Línea {$numLinea}: No tiene suficientes columnas (esperadas: " . count($camposRequeridos) . ", encontradas: " . count($datos) . ").";
                continue;
            }

            // ID es opcional
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
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        if (empty($preguntas)) {
            $this->addFlash('error', 'No se encontraron preguntas válidas en el archivo CSV.');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        // Guardar datos en sesión para la confirmación
        $request->getSession()->set('preguntas_municipal_csv', $preguntas);
        $request->getSession()->set('preguntas_municipal_csv_municipio', $municipioId);
        $request->getSession()->set('preguntas_municipal_csv_tema', $temaMunicipalId);
        $request->getSession()->set('preguntas_municipal_csv_dificultad', $dificultad);

        return $this->render('pregunta_municipal/preview_csv.html.twig', [
            'preguntas' => $preguntas,
            'municipio' => $municipio,
            'temaMunicipal' => $temaMunicipal,
            'dificultad' => $dificultad,
            'total' => count($preguntas),
        ]);
    }

    #[Route('/confirmar-crear-csv', name: 'app_pregunta_municipal_confirmar_crear_csv', methods: ['POST'])]
    public function confirmarCrearCsv(
        Request $request,
        EntityManagerInterface $entityManager,
        MunicipioRepository $municipioRepository,
        TemaMunicipalRepository $temaMunicipalRepository,
        PreguntaMunicipalRepository $preguntaMunicipalRepository
    ): Response {
        // Validar token CSRF
        if (!$this->isCsrfTokenValid('confirmar_csv_municipal', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido. Por favor, intenta de nuevo.');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        $session = $request->getSession();
        
        $preguntas = $session->get('preguntas_municipal_csv', []);
        $municipioId = $session->get('preguntas_municipal_csv_municipio', 0);
        $temaMunicipalId = $session->get('preguntas_municipal_csv_tema', 0);
        $dificultad = $session->get('preguntas_municipal_csv_dificultad', '');

        if (empty($preguntas) || $municipioId <= 0 || $temaMunicipalId <= 0 || empty($dificultad)) {
            $this->addFlash('error', 'Sesión expirada. Por favor, vuelve a cargar el CSV.');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        $municipio = $municipioRepository->find($municipioId);
        $temaMunicipal = $temaMunicipalRepository->find($temaMunicipalId);

        if (!$municipio || !$temaMunicipal) {
            $this->addFlash('error', 'Uno o más campos seleccionados no son válidos.');
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        $creadas = 0;
        $actualizadas = 0;
        $errores = [];
        $batchSize = 100;

        // Usar transacción para asegurar atomicidad
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        
        try {
            foreach ($preguntas as $index => $preguntaData) {
                try {
                    $pregunta = null;
                    $esActualizacion = false;

                    // Si hay ID, intentar buscar la pregunta existente
                    if (!empty($preguntaData['id'])) {
                        $pregunta = $preguntaMunicipalRepository->find($preguntaData['id']);
                        if ($pregunta) {
                            $esActualizacion = true;
                        }
                    }

                    // Si no existe, crear nueva
                    if (!$pregunta) {
                        $pregunta = new PreguntaMunicipal();
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
                    $pregunta->setMunicipio($municipio);
                    $pregunta->setTemaMunicipal($temaMunicipal);
                    
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

                    // Flush en lotes
                    if (($index + 1) % $batchSize === 0) {
                        $entityManager->flush();
                    }
                } catch (\Exception $e) {
                    $errores[] = "Línea {$preguntaData['linea']}: " . $e->getMessage();
                }
            }

            // Flush final
            $entityManager->flush();
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->addFlash('error', 'Error al guardar las preguntas: ' . $e->getMessage());
            return $this->redirectToRoute('app_pregunta_municipal_new_csv');
        }

        // Limpiar sesión
        $session->remove('preguntas_municipal_csv');
        $session->remove('preguntas_municipal_csv_municipio');
        $session->remove('preguntas_municipal_csv_tema');
        $session->remove('preguntas_municipal_csv_dificultad');

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

        return $this->redirectToRoute('app_pregunta_municipal_index');
    }

    #[Route('/actualizar-csv', name: 'app_pregunta_municipal_actualizar_csv', methods: ['GET'])]
    public function actualizarCsv(): Response
    {
        return $this->render('pregunta_municipal/actualizar_csv.html.twig');
    }

    #[Route('/procesar-actualizar-csv', name: 'app_pregunta_municipal_procesar_actualizar_csv', methods: ['POST'])]
    public function procesarActualizarCsv(
        Request $request,
        PreguntaMunicipalRepository $preguntaMunicipalRepository,
        MunicipioRepository $municipioRepository,
        TemaMunicipalRepository $temaMunicipalRepository
    ): Response {
        /** @var UploadedFile|null $archivo */
        $archivo = $request->files->get('archivo_csv');

        if (!$archivo) {
            $this->addFlash('error', 'Debes seleccionar un archivo CSV.');
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        // Validar extensión
        $extension = strtolower($archivo->getClientOriginalExtension());
        if ($extension !== 'csv') {
            $this->addFlash('error', 'El archivo debe ser un CSV (extensión .csv).');
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        // Validar tamaño del archivo (máximo 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($archivo->getSize() > $maxSize) {
            $this->addFlash('error', 'El archivo CSV no puede ser mayor a 5MB.');
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
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
        $lineas = array_values($lineas);
        
        if (count($lineas) < 2) {
            $this->addFlash('error', 'El archivo CSV debe tener al menos una fila de datos (además del encabezado).');
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        // Limitar número máximo de preguntas (1000 por seguridad)
        $maxPreguntas = 1000;
        if (count($lineas) > $maxPreguntas + 1) {
            $this->addFlash('error', "El archivo CSV no puede tener más de {$maxPreguntas} preguntas. Por favor, divide el archivo en partes más pequeñas.");
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        // Procesar encabezado
        $encabezado = str_getcsv(array_shift($lineas));
        $encabezado = array_map('trim', $encabezado);
        $encabezado = array_map('strtolower', $encabezado);

        $camposRequeridos = ['id', 'texto', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta_correcta', 'municipio_id', 'tema_municipal_id', 'dificultad'];
        $camposOpcionales = ['retroalimentacion'];

        // Validar encabezado
        foreach ($camposRequeridos as $campo) {
            if (!in_array($campo, $encabezado)) {
                $this->addFlash('error', "El archivo CSV debe contener la columna: {$campo}");
                return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
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
            $numLinea = $numLinea + 2;
            
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
            $municipioId = trim($datos[$indices['municipio_id']] ?? '');
            $temaMunicipalId = trim($datos[$indices['tema_municipal_id']] ?? '');
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

            if (empty($municipioId) || !is_numeric($municipioId)) {
                $errores[] = "Línea {$numLinea}: El municipio_id es requerido y debe ser numérico.";
                continue;
            }

            if (empty($temaMunicipalId) || !is_numeric($temaMunicipalId)) {
                $errores[] = "Línea {$numLinea}: El tema_municipal_id es requerido y debe ser numérico.";
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
                'municipioId' => (int)$municipioId,
                'temaMunicipalId' => (int)$temaMunicipalId,
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
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        if (empty($preguntas)) {
            $this->addFlash('error', 'No se encontraron preguntas válidas en el archivo CSV.');
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        // Validar que las preguntas existan (guardar solo IDs para evitar problemas de serialización)
        $preguntasValidadas = [];
        foreach ($preguntas as $preguntaData) {
            $pregunta = $preguntaMunicipalRepository->find($preguntaData['id']);
            if (!$pregunta) {
                $errores[] = "Línea {$preguntaData['linea']}: La pregunta con ID {$preguntaData['id']} no existe.";
                continue;
            }

            $municipio = $municipioRepository->find($preguntaData['municipioId']);
            $temaMunicipal = $temaMunicipalRepository->find($preguntaData['temaMunicipalId']);

            if (!$municipio || !$temaMunicipal) {
                $errores[] = "Línea {$preguntaData['linea']}: Uno o más IDs (municipio, tema municipal) no son válidos.";
                continue;
            }

            // Guardar solo los datos y IDs, no las entidades
            $preguntasValidadas[] = [
                'preguntaId' => $preguntaData['id'],
                'data' => $preguntaData,
                'municipioId' => $preguntaData['municipioId'],
                'temaMunicipalId' => $preguntaData['temaMunicipalId'],
            ];
        }

        if (!empty($errores)) {
            $mensajeErrores = implode("\n", array_slice($errores, 0, 10));
            if (count($errores) > 10) {
                $mensajeErrores .= "\n... y " . (count($errores) - 10) . " errores más.";
            }
            $this->addFlash('error', "Errores encontrados:\n" . $mensajeErrores);
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        // Guardar datos en sesión para la confirmación
        $request->getSession()->set('preguntas_municipal_actualizar_csv', $preguntasValidadas);

        // Cargar entidades completas solo para la previsualización
        $preguntasParaPreview = [];
        foreach ($preguntasValidadas as $item) {
            $pregunta = $preguntaMunicipalRepository->find($item['preguntaId']);
            $municipio = $municipioRepository->find($item['municipioId']);
            $temaMunicipal = $temaMunicipalRepository->find($item['temaMunicipalId']);

            $preguntasParaPreview[] = [
                'pregunta' => $pregunta,
                'data' => $item['data'],
                'municipio' => $municipio,
                'temaMunicipal' => $temaMunicipal,
            ];
        }

        return $this->render('pregunta_municipal/preview_actualizar_csv.html.twig', [
            'preguntas' => $preguntasParaPreview,
            'total' => count($preguntasParaPreview),
        ]);
    }

    #[Route('/confirmar-actualizar-csv', name: 'app_pregunta_municipal_confirmar_actualizar_csv', methods: ['POST'])]
    public function confirmarActualizarCsv(
        Request $request,
        EntityManagerInterface $entityManager,
        PreguntaMunicipalRepository $preguntaMunicipalRepository,
        MunicipioRepository $municipioRepository,
        TemaMunicipalRepository $temaMunicipalRepository
    ): Response {
        // Validar token CSRF
        if (!$this->isCsrfTokenValid('actualizar_csv_municipal', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido. Por favor, intenta de nuevo.');
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        $session = $request->getSession();
        $preguntasValidadas = $session->get('preguntas_municipal_actualizar_csv', []);

        if (empty($preguntasValidadas)) {
            $this->addFlash('error', 'Sesión expirada. Por favor, vuelve a cargar el CSV.');
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
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
                    // Re-cargar entidades desde la base de datos
                    $pregunta = $preguntaMunicipalRepository->find($item['preguntaId']);
                    if (!$pregunta) {
                        $errores[] = "Línea {$item['data']['linea']}: La pregunta con ID {$item['preguntaId']} no existe.";
                        continue;
                    }

                    $municipio = $municipioRepository->find($item['municipioId']);
                    $temaMunicipal = $temaMunicipalRepository->find($item['temaMunicipalId']);

                    if (!$municipio || !$temaMunicipal) {
                        $errores[] = "Línea {$item['data']['linea']}: Uno o más IDs (municipio, tema municipal) no son válidos.";
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
                    $pregunta->setMunicipio($municipio);
                    $pregunta->setTemaMunicipal($temaMunicipal);

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
            return $this->redirectToRoute('app_pregunta_municipal_actualizar_csv');
        }

        // Limpiar sesión
        $session->remove('preguntas_municipal_actualizar_csv');

        if (!empty($errores)) {
            $mensajeErrores = implode("\n", array_slice($errores, 0, 5));
            $this->addFlash('warning', "Se actualizaron {$actualizadas} preguntas, pero hubo algunos errores:\n" . $mensajeErrores);
        } else {
            $this->addFlash('success', "Se actualizaron correctamente {$actualizadas} preguntas.");
        }

        return $this->redirectToRoute('app_pregunta_municipal_index');
    }
}






