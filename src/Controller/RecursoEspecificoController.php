<?php

namespace App\Controller;

use App\Entity\RecursoEspecifico;
use App\Entity\User;
use App\Form\RecursoEspecificoType;
use App\Repository\RecursoEspecificoRepository;
use App\Repository\UserRepository;
use App\Repository\GrupoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/recurso-especifico')]
#[IsGranted('ROLE_PROFESOR')]
class RecursoEspecificoController extends AbstractController
{
    public function __construct(
        private readonly KernelInterface $kernel
    ) {
    }

    #[Route('/', name: 'app_recurso_especifico_index', methods: ['GET'])]
    public function index(RecursoEspecificoRepository $recursoEspecificoRepository, Request $request): Response
    {
        $profesor = $this->getUser();
        $search = trim($request->query->get('search', ''));

        if (!empty($search)) {
            $recursos = $recursoEspecificoRepository->createQueryBuilder('r')
                ->where('r.profesor = :profesor')
                ->andWhere('(r.nombre LIKE :search OR r.descripcion LIKE :search)')
                ->setParameter('profesor', $profesor)
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('r.fechaCreacion', 'DESC')
                ->getQuery()
                ->getResult();
            $recursos = $this->completarLotesDeLaBusqueda($recursos, $recursoEspecificoRepository, $profesor);
        } else {
            $recursos = $recursoEspecificoRepository->findByProfesor($profesor);
        }

        return $this->render('recurso_especifico/index.html.twig', [
            'grupos' => $this->agruparParaListado($recursos),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_recurso_especifico_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, UserRepository $userRepository, GrupoRepository $grupoRepository): Response
    {
        $profesor = $this->getUser();
        $recursoEspecifico = new RecursoEspecifico();
        $recursoEspecifico->setProfesor($profesor);

        // Obtener solo los alumnos asignados al profesor
        $esAdmin = $this->isGranted('ROLE_ADMIN');
        $alumnosQueryBuilder = null;
        
        if (!$esAdmin) {
            $alumnosIds = $profesor->getAlumnos()->map(fn($alumno) => $alumno->getId())->toArray();
            if (!empty($alumnosIds)) {
                $alumnosQueryBuilder = $userRepository->createQueryBuilder('u')
                    ->where('u.id IN (:alumnosIds)')
                    ->andWhere('u.activo = :activo')
                    ->setParameter('alumnosIds', $alumnosIds)
                    ->setParameter('activo', true)
                    ->orderBy('u.nombre', 'ASC')
                    ->addOrderBy('u.username', 'ASC');
            }
        } else {
            // Si es admin, puede asignar a cualquier alumno activo
            $alumnosQueryBuilder = $userRepository->createQueryBuilder('u')
                ->where('u.activo = :activo')
                ->andWhere('u.roles NOT LIKE :roleProfesor')
                ->andWhere('u.roles NOT LIKE :roleAdmin')
                ->setParameter('activo', true)
                ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
                ->orderBy('u.nombre', 'ASC')
                ->addOrderBy('u.username', 'ASC');
        }

        // Obtener grupos disponibles para el profesor
        $gruposQueryBuilder = null;
        if (!$esAdmin) {
            // Obtener grupos que tengan alumnos asignados al profesor
            $todosGrupos = $grupoRepository->findAll();
            $gruposIds = [];
            foreach ($todosGrupos as $grupo) {
                $alumnosDelGrupo = $grupo->getAlumnos()->toArray();
                $alumnosComunes = array_intersect(
                    array_map(fn($a) => $a->getId(), $alumnosDelGrupo),
                    $alumnosIds ?? []
                );
                if (!empty($alumnosComunes)) {
                    $gruposIds[] = $grupo->getId();
                }
            }
            if (!empty($gruposIds)) {
                $gruposQueryBuilder = $grupoRepository->createQueryBuilder('g')
                    ->where('g.id IN (:gruposIds)')
                    ->setParameter('gruposIds', $gruposIds)
                    ->orderBy('g.nombre', 'ASC');
            }
        } else {
            // Admin puede ver todos los grupos
            $gruposQueryBuilder = $grupoRepository->createQueryBuilder('g')
                ->orderBy('g.nombre', 'ASC');
        }

        $form = $this->createForm(RecursoEspecificoType::class, $recursoEspecifico, [
            'alumnos_query_builder' => $alumnosQueryBuilder,
            'grupos_query_builder' => $gruposQueryBuilder,
            'multiple_archivos' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->asignarTodosLosAlumnosSiSolicitado($request, $recursoEspecifico, $alumnosQueryBuilder);
            if (!$form->isValid()) {
                $this->addFlashSiErrorSubidaArchivo($form);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Si hay grupo seleccionado, asignar automáticamente todos los alumnos del grupo
            $grupo = $recursoEspecifico->getGrupo();
            if ($grupo !== null) {
                foreach ($grupo->getAlumnos() as $alumno) {
                    if (!$recursoEspecifico->getAlumnos()->contains($alumno)) {
                        $recursoEspecifico->addAlumno($alumno);
                    }
                }
            }
            $this->asignarAlumnosDeConvocatorias($recursoEspecifico);
            
            // Validar que hay al menos un alumno asignado, un grupo o una convocatoria
            if ($recursoEspecifico->getAlumnos()->isEmpty() && $grupo === null && $recursoEspecifico->getConvocatorias()->isEmpty()) {
                $this->addFlash('error', 'Debes asignar el recurso a al menos un alumno, un grupo o una convocatoria.');
                return $this->render('recurso_especifico/new.html.twig', [
                    'recursoEspecifico' => $recursoEspecifico,
                    'form' => $form,
                ]);
            }
            
            $archivos = $this->normalizarArchivosSubidos($form->get('archivo')->getData());
            $tipoRecurso = (string) $form->get('tipoRecurso')->getData();
            $recursos = $this->crearRecursosConArchivos($recursoEspecifico, $archivos, $tipoRecurso, $slugger);

            if (is_string($recursos)) {
                $this->addFlash('error', $recursos);
                return $this->render('recurso_especifico/new.html.twig', [
                    'recursoEspecifico' => $recursoEspecifico,
                    'form' => $form,
                ]);
            }

            foreach ($recursos as $recurso) {
                $entityManager->persist($recurso);
            }
            $entityManager->flush();

            $this->addFlash('success', count($recursos) > 1
                ? 'Se han creado ' . count($recursos) . ' recursos específicos.'
                : 'Recurso específico creado correctamente.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recurso_especifico/new.html.twig', [
            'recursoEspecifico' => $recursoEspecifico,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recurso_especifico_show', methods: ['GET'])]
    public function show(RecursoEspecifico $recursoEspecifico, RecursoEspecificoRepository $recursoEspecificoRepository): Response
    {
        // Verificar que el profesor tiene acceso a este recurso
        $profesor = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $recursoEspecifico->getProfesor()->getId() !== $profesor->getId()) {
            $this->addFlash('error', 'No tienes acceso a este recurso.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        $recursosGrupo = $this->recursosDelMismoLote($recursoEspecifico, $recursoEspecificoRepository);

        return $this->render('recurso_especifico/show.html.twig', [
            'recursoEspecifico' => $recursoEspecifico,
            'recursosGrupo' => $recursosGrupo,
            'nombreGrupo' => $recursosGrupo[0]->getNombreBase(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recurso_especifico_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RecursoEspecifico $recursoEspecifico, EntityManagerInterface $entityManager, SluggerInterface $slugger, UserRepository $userRepository, GrupoRepository $grupoRepository, RecursoEspecificoRepository $recursoEspecificoRepository): Response
    {
        // Verificar que el profesor tiene acceso a este recurso
        $profesor = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $recursoEspecifico->getProfesor()->getId() !== $profesor->getId()) {
            $this->addFlash('error', 'No tienes acceso a este recurso.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        $recursosGrupo = $this->recursosDelMismoLote($recursoEspecifico, $recursoEspecificoRepository);
        $recursoEspecifico->setNombre($recursoEspecifico->getNombreBase());

        // Obtener solo los alumnos asignados al profesor
        $esAdmin = $this->isGranted('ROLE_ADMIN');
        $alumnosQueryBuilder = null;
        
        if (!$esAdmin) {
            $alumnosIds = $profesor->getAlumnos()->map(fn($alumno) => $alumno->getId())->toArray();
            if (!empty($alumnosIds)) {
                $alumnosQueryBuilder = $userRepository->createQueryBuilder('u')
                    ->where('u.id IN (:alumnosIds)')
                    ->andWhere('u.activo = :activo')
                    ->setParameter('alumnosIds', $alumnosIds)
                    ->setParameter('activo', true)
                    ->orderBy('u.nombre', 'ASC')
                    ->addOrderBy('u.username', 'ASC');
            }
        } else {
            $alumnosQueryBuilder = $userRepository->createQueryBuilder('u')
                ->where('u.activo = :activo')
                ->andWhere('u.roles NOT LIKE :roleProfesor')
                ->andWhere('u.roles NOT LIKE :roleAdmin')
                ->setParameter('activo', true)
                ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
                ->orderBy('u.nombre', 'ASC')
                ->addOrderBy('u.username', 'ASC');
        }

        // Obtener grupos disponibles para el profesor
        $gruposQueryBuilder = null;
        if (!$esAdmin) {
            $alumnosIds = $profesor->getAlumnos()->map(fn($alumno) => $alumno->getId())->toArray();
            $todosGrupos = $grupoRepository->findAll();
            $gruposIds = [];
            foreach ($todosGrupos as $grupo) {
                $alumnosDelGrupo = $grupo->getAlumnos()->toArray();
                $alumnosComunes = array_intersect(
                    array_map(fn($a) => $a->getId(), $alumnosDelGrupo),
                    $alumnosIds ?? []
                );
                if (!empty($alumnosComunes)) {
                    $gruposIds[] = $grupo->getId();
                }
            }
            if (!empty($gruposIds)) {
                $gruposQueryBuilder = $grupoRepository->createQueryBuilder('g')
                    ->where('g.id IN (:gruposIds)')
                    ->setParameter('gruposIds', $gruposIds)
                    ->orderBy('g.nombre', 'ASC');
            }
        } else {
            $gruposQueryBuilder = $grupoRepository->createQueryBuilder('g')
                ->orderBy('g.nombre', 'ASC');
        }

        $form = $this->createForm(RecursoEspecificoType::class, $recursoEspecifico, [
            'alumnos_query_builder' => $alumnosQueryBuilder,
            'grupos_query_builder' => $gruposQueryBuilder,
            'multiple_archivos' => true,
            'es_grupo' => count($recursosGrupo) > 1,
            'edicion' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->asignarTodosLosAlumnosSiSolicitado($request, $recursoEspecifico, $alumnosQueryBuilder);
            if (!$form->isValid()) {
                $this->addFlashSiErrorSubidaArchivo($form);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Si hay grupo seleccionado, asignar automáticamente todos los alumnos del grupo
            $grupo = $recursoEspecifico->getGrupo();
            if ($grupo !== null) {
                foreach ($grupo->getAlumnos() as $alumno) {
                    if (!$recursoEspecifico->getAlumnos()->contains($alumno)) {
                        $recursoEspecifico->addAlumno($alumno);
                    }
                }
            }
            $this->asignarAlumnosDeConvocatorias($recursoEspecifico);
            
            // Validar que hay al menos un alumno asignado, un grupo o una convocatoria
            if ($recursoEspecifico->getAlumnos()->isEmpty() && $grupo === null && $recursoEspecifico->getConvocatorias()->isEmpty()) {
                $this->addFlash('error', 'Debes asignar el recurso a al menos un alumno, un grupo o una convocatoria.');
                return $this->renderFormularioEdicion($form, $recursoEspecifico, $recursosGrupo);
            }

            $errorContenido = $this->guardarEdicionConArchivos(
                $request,
                $recursoEspecifico,
                $recursosGrupo,
                $form,
                $entityManager,
                $slugger
            );
            if ($errorContenido !== null) {
                $this->addFlash('error', $errorContenido);
                return $this->renderFormularioEdicion($form, $recursoEspecifico, $recursosGrupo);
            }

            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderFormularioEdicion($form, $recursoEspecifico, $recursosGrupo);
    }

    #[Route('/{id}', name: 'app_recurso_especifico_delete', methods: ['POST'])]
    public function delete(Request $request, RecursoEspecifico $recursoEspecifico, EntityManagerInterface $entityManager, RecursoEspecificoRepository $recursoEspecificoRepository): Response
    {
        // Verificar que el profesor tiene acceso a este recurso
        $profesor = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $recursoEspecifico->getProfesor()->getId() !== $profesor->getId()) {
            $this->addFlash('error', 'No tienes acceso a este recurso.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$recursoEspecifico->getId(), $request->getPayload()->getString('_token'))) {
            $recursos = $this->recursosDelMismoLote($recursoEspecifico, $recursoEspecificoRepository);
            foreach ($recursos as $recurso) {
                $this->eliminarArchivoFisico($recurso);
                $entityManager->remove($recurso);
            }
            $entityManager->flush();

            $this->addFlash('success', count($recursos) > 1
                ? 'Se ha eliminado el recurso y sus ' . count($recursos) . ' archivos.'
                : 'Recurso específico eliminado correctamente.');
        }

        return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
    }

    private function asignarAlumnosDeConvocatorias(RecursoEspecifico $recursoEspecifico): void
    {
        foreach ($recursoEspecifico->getConvocatorias() as $convocatoria) {
            foreach ($convocatoria->getUsuarios() as $alumno) {
                $recursoEspecifico->addAlumno($alumno);
            }
        }
    }

    private function asignarTodosLosAlumnosSiSolicitado(
        Request $request,
        RecursoEspecifico $recursoEspecifico,
        ?QueryBuilder $alumnosQueryBuilder,
    ): void {
        if (!$request->request->getBoolean('asignar_todos_alumnos') || null === $alumnosQueryBuilder) {
            return;
        }

        foreach ($alumnosQueryBuilder->getQuery()->getResult() as $alumno) {
            if (!$recursoEspecifico->getAlumnos()->contains($alumno)) {
                $recursoEspecifico->addAlumno($alumno);
            }
        }
    }

    /**
     * @param list<UploadedFile> $archivos
     * @return list<RecursoEspecifico>|string
     */
    private function crearRecursosConArchivos(
        RecursoEspecifico $recursoEspecifico,
        array $archivos,
        string $tipoRecurso,
        SluggerInterface $slugger,
    ): array|string {
        if ($tipoRecurso !== 'archivo' || count($archivos) <= 1) {
            $error = $this->procesarArchivoOEnlace(
                $recursoEspecifico,
                $archivos[0] ?? null,
                $tipoRecurso,
                $slugger,
                true
            );

            return $error ?? [$recursoEspecifico];
        }

        $nombreBase = $recursoEspecifico->getNombre() ?? 'Recurso';
        $recursoEspecifico->setLote(bin2hex(random_bytes(16)));
        $recursos = [];
        foreach ($archivos as $indice => $archivo) {
            $recurso = $indice === 0 ? $recursoEspecifico : $this->copiarAsignacion($recursoEspecifico);
            $recurso->setNombre($nombreBase . ' — ' . $archivo->getClientOriginalName());
            $error = $this->procesarArchivoOEnlace($recurso, $archivo, 'archivo', $slugger, true);
            if ($error !== null) {
                foreach ($recursos as $creado) {
                    $this->eliminarArchivoFisico($creado);
                }
                $this->eliminarArchivoFisico($recurso);

                return $error;
            }
            $recursos[] = $recurso;
        }

        return $recursos;
    }

    private function copiarAsignacion(RecursoEspecifico $origen): RecursoEspecifico
    {
        $copia = new RecursoEspecifico();
        $copia->setNombre($origen->getNombre() ?? 'Recurso');
        $copia->setDescripcion($origen->getDescripcion());
        $copia->setLote($origen->getLote());
        $copia->setProfesor($origen->getProfesor());
        $copia->setGrupo($origen->getGrupo());
        foreach ($origen->getConvocatorias() as $convocatoria) {
            $copia->addConvocatoria($convocatoria);
        }
        foreach ($origen->getAlumnos() as $alumno) {
            $copia->addAlumno($alumno);
        }

        return $copia;
    }

    /**
     * @return list<UploadedFile>
     */
    private function normalizarArchivosSubidos(mixed $data): array
    {
        if ($data instanceof UploadedFile) {
            return [$data];
        }
        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter(
            $data,
            static fn ($archivo) => $archivo instanceof UploadedFile && $archivo->getError() !== \UPLOAD_ERR_NO_FILE
        ));
    }

    private function addFlashSiErrorSubidaArchivo(FormInterface $form): void
    {
        if (!$form->has('archivo')) {
            return;
        }

        $archivos = $this->normalizarArchivosSubidos($form->get('archivo')->getData());
        $archivo = null;
        foreach ($archivos as $subido) {
            if (!$subido->isValid()) {
                $archivo = $subido;
                break;
            }
        }
        if (!$archivo instanceof UploadedFile) {
            return;
        }

        $mensaje = match ($archivo->getError()) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => sprintf(
                'No se pudo subir el archivo. Límites del servidor: upload_max_filesize=%s, post_max_size=%s. Si has marcado muchos alumnos, usa "Seleccionar todos" para reducir el tamaño del formulario.',
                ini_get('upload_max_filesize'),
                ini_get('post_max_size')
            ),
            \UPLOAD_ERR_NO_FILE => 'No se recibió el archivo. Si el PDF es pequeño, el formulario puede ser demasiado grande por los alumnos seleccionados. Prueba con "Seleccionar todos".',
            default => 'Error al subir el archivo (código ' . $archivo->getError() . ').',
        };

        $this->addFlash('error', $mensaje);
    }

    private function procesarArchivoOEnlace(
        RecursoEspecifico $recursoEspecifico,
        ?UploadedFile $archivo,
        string $tipoRecurso,
        SluggerInterface $slugger,
        bool $esCreacion,
    ): ?string {
        if ($tipoRecurso === 'enlace') {
            $enlace = trim((string) ($recursoEspecifico->getEnlace() ?? ''));
            if ($enlace === '') {
                return $esCreacion
                    ? 'Debes indicar un enlace o subir un archivo.'
                    : 'Debes indicar un enlace válido o subir un archivo.';
            }

            $enlaceNormalizado = $this->normalizarEnlace($enlace);
            if ($enlaceNormalizado === null) {
                return 'El enlace no es válido. Debe empezar por http:// o https://';
            }

            $this->eliminarArchivoFisico($recursoEspecifico);
            $recursoEspecifico->setEnlace($enlaceNormalizado);
            $recursoEspecifico->setRutaArchivo(null);
            $recursoEspecifico->setNombreArchivoOriginal(null);

            return null;
        }

        if ($archivo) {
            if ($archivo->getSize() > 100 * 1024 * 1024) {
                return 'El archivo es demasiado grande. Tamaño máximo: 100 MB.';
            }

            $this->eliminarArchivoFisico($recursoEspecifico);

            $originalFilename = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $archivo->getClientOriginalExtension();
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

            try {
                $directorio = $this->kernel->getProjectDir() . '/public/recursos_especificos';
                if (!is_dir($directorio)) {
                    mkdir($directorio, 0755, true);
                }

                $archivo->move($directorio, $newFilename);
                $recursoEspecifico->setRutaArchivo('/recursos_especificos/' . $newFilename);
                $recursoEspecifico->setNombreArchivoOriginal($archivo->getClientOriginalName());
                $recursoEspecifico->setEnlace(null);
            } catch (FileException $e) {
                return 'Error al subir el archivo: ' . $e->getMessage();
            }

            return null;
        }

        if ($esCreacion && !$recursoEspecifico->tieneArchivo() && !$recursoEspecifico->tieneEnlace()) {
            return 'Debes subir un archivo o indicar un enlace.';
        }

        if (!$esCreacion && !$recursoEspecifico->tieneArchivo() && !$recursoEspecifico->tieneEnlace()) {
            return 'El recurso debe tener un archivo o un enlace.';
        }

        return null;
    }

    private function normalizarEnlace(string $enlace): ?string
    {
        $enlace = trim($enlace);
        if ($enlace === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $enlace)) {
            $enlace = 'https://' . $enlace;
        }

        return filter_var($enlace, FILTER_VALIDATE_URL) ? $enlace : null;
    }

    private function eliminarArchivoFisico(RecursoEspecifico $recursoEspecifico): void
    {
        if (!$recursoEspecifico->tieneArchivo()) {
            return;
        }

        $rutaFisica = $this->kernel->getProjectDir() . '/public' . $recursoEspecifico->getRutaArchivo();
        if (file_exists($rutaFisica)) {
            unlink($rutaFisica);
        }
    }

    /**
     * @param list<RecursoEspecifico> $recursos
     * @return list<RecursoEspecifico>
     */
    private function completarLotesDeLaBusqueda(array $recursos, RecursoEspecificoRepository $repository, mixed $profesor): array
    {
        if (!$profesor instanceof User) {
            return $recursos;
        }

        $lotes = [];
        foreach ($recursos as $recurso) {
            $lote = $recurso->getLote();
            if ($lote !== null && $lote !== '') {
                $lotes[$lote] = $lote;
            }
        }
        if ($lotes === []) {
            return $recursos;
        }

        $porId = [];
        foreach ($recursos as $recurso) {
            $porId[$recurso->getId()] = $recurso;
        }
        foreach ($repository->findByLotesYProfesor(array_values($lotes), $profesor) as $recurso) {
            $porId[$recurso->getId()] ??= $recurso;
        }

        return array_values($porId);
    }

    /**
     * @param list<RecursoEspecifico> $recursos
     * @return list<array{nombre: string, descripcion: ?string, fecha: ?\DateTimeInterface, recursos: list<RecursoEspecifico>, principal: RecursoEspecifico}>
     */
    private function agruparParaListado(array $recursos): array
    {
        $grupos = [];
        $orden = [];
        foreach ($recursos as $recurso) {
            $lote = $recurso->getLote();
            $clave = ($lote !== null && $lote !== '') ? 'lote-' . $lote : 'recurso-' . $recurso->getId();
            if (!isset($grupos[$clave])) {
                $orden[] = $clave;
            }
            $grupos[$clave][] = $recurso;
        }

        $resultado = [];
        foreach ($orden as $clave) {
            $items = $grupos[$clave];
            usort($items, static fn (RecursoEspecifico $a, RecursoEspecifico $b): int => ($a->getId() ?? 0) <=> ($b->getId() ?? 0));
            $fecha = $items[0]->getFechaCreacion();
            foreach ($items as $item) {
                $candidata = $item->getFechaCreacion();
                if ($fecha === null || ($candidata !== null && $candidata > $fecha)) {
                    $fecha = $candidata;
                }
            }
            $resultado[] = [
                'nombre' => $items[0]->getNombreBase(),
                'descripcion' => $items[0]->getDescripcion(),
                'fecha' => $fecha,
                'recursos' => $items,
                'principal' => $items[0],
            ];
        }

        return $resultado;
    }

    /**
     * @return list<RecursoEspecifico>
     */
    private function recursosDelMismoLote(RecursoEspecifico $recurso, RecursoEspecificoRepository $repository): array
    {
        $lote = $recurso->getLote();
        $profesor = $recurso->getProfesor();
        if ($lote === null || $lote === '' || !$profesor instanceof User) {
            return [$recurso];
        }

        $recursos = $repository->findByLoteYProfesor($lote, $profesor);

        return $recursos !== [] ? $recursos : [$recurso];
    }

    /**
     * @param list<RecursoEspecifico> $recursosGrupo
     */
    private function renderFormularioEdicion(FormInterface $form, RecursoEspecifico $recurso, array $recursosGrupo): Response
    {
        return $this->render('recurso_especifico/edit.html.twig', [
            'recursoEspecifico' => $recurso,
            'form' => $form,
            'recursosGrupo' => $recursosGrupo,
        ]);
    }

    /**
     * @param list<RecursoEspecifico> $recursosGrupo
     */
    private function guardarEdicionConArchivos(
        Request $request,
        RecursoEspecifico $recursoEspecifico,
        array $recursosGrupo,
        FormInterface $form,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
    ): ?string {
        $nombreBase = $recursoEspecifico->getNombre() ?? 'Recurso';
        $tipoRecurso = (string) $form->get('tipoRecurso')->getData();
        $idsEliminar = $this->idsArchivosAEliminar($request);
        $archivosNuevos = $this->normalizarArchivosSubidos($form->get('archivo')->getData());

        if ($tipoRecurso === 'enlace') {
            if (count($recursosGrupo) > 1 || $idsEliminar !== []) {
                return 'Un recurso con varios archivos no puede pasar a ser un enlace. Quita archivos hasta dejar uno, o crea el enlace aparte.';
            }

            $error = $this->procesarArchivoOEnlace($recursoEspecifico, null, 'enlace', $slugger, false);
            if ($error !== null) {
                return $error;
            }

            $entityManager->flush();
            $this->addFlash('success', 'Recurso específico actualizado correctamente.');

            return null;
        }

        $porId = [];
        foreach ($recursosGrupo as $item) {
            if ($item->getId() !== null) {
                $porId[$item->getId()] = $item;
            }
        }
        $aEliminar = [];
        foreach ($idsEliminar as $id) {
            if (isset($porId[$id])) {
                $aEliminar[] = $porId[$id];
            }
        }
        $seQuedan = [];
        foreach ($recursosGrupo as $item) {
            if (!in_array($item, $aEliminar, true)) {
                $seQuedan[] = $item;
            }
        }

        $esSustitucion = count($recursosGrupo) === 1
            && $idsEliminar === []
            && count($archivosNuevos) === 1;

        if ($esSustitucion) {
            $error = $this->procesarArchivoOEnlace($recursoEspecifico, $archivosNuevos[0], 'archivo', $slugger, false);
            if ($error !== null) {
                return $error;
            }

            $recursoEspecifico->setLote(null);
            $entityManager->flush();
            $this->addFlash('success', 'Recurso específico actualizado correctamente.');

            return null;
        }

        if ($seQuedan === [] && $archivosNuevos === []) {
            return 'El recurso debe conservar al menos un archivo.';
        }

        if ((count($seQuedan) + count($archivosNuevos)) > 1 && ($recursoEspecifico->getLote() === null || $recursoEspecifico->getLote() === '')) {
            $recursoEspecifico->setLote(bin2hex(random_bytes(16)));
        }

        $creados = [];
        foreach ($archivosNuevos as $archivo) {
            $nuevo = $this->copiarAsignacion($recursoEspecifico);
            $nuevo->setNombre($nombreBase . ' — ' . $archivo->getClientOriginalName());
            $error = $this->procesarArchivoOEnlace($nuevo, $archivo, 'archivo', $slugger, true);
            if ($error !== null) {
                foreach ($creados as $creado) {
                    $this->eliminarArchivoFisico($creado);
                }
                $this->eliminarArchivoFisico($nuevo);

                return $error;
            }
            $creados[] = $nuevo;
        }

        $finales = array_merge($seQuedan, $creados);
        $esGrupo = count($finales) > 1;
        $loteFinal = $esGrupo ? ($recursoEspecifico->getLote() ?: bin2hex(random_bytes(16))) : null;

        foreach ($finales as $item) {
            $item->setLote($loteFinal);
            $archivo = $item->getNombreArchivoOriginal() ?: 'archivo';
            $item->setNombre($esGrupo ? $nombreBase . ' — ' . $archivo : $nombreBase);
            $this->copiarDatosSobre($recursoEspecifico, $item);
        }

        foreach ($aEliminar as $item) {
            $this->eliminarArchivoFisico($item);
            $entityManager->remove($item);
        }
        foreach ($creados as $creado) {
            $entityManager->persist($creado);
        }
        $entityManager->flush();

        $mensaje = 'Recurso específico actualizado correctamente.';
        if (count($creados) > 0) {
            $mensaje .= count($creados) === 1
                ? ' Se ha añadido 1 archivo.'
                : ' Se han añadido ' . count($creados) . ' archivos.';
        }
        $this->addFlash('success', $mensaje);

        return null;
    }

    /**
     * @return list<int>
     */
    private function idsArchivosAEliminar(Request $request): array
    {
        if (!$request->request->has('eliminar_archivos')) {
            return [];
        }

        $ids = [];
        foreach ($request->request->all('eliminar_archivos') as $id) {
            if (is_scalar($id) && ctype_digit((string) $id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private function copiarDatosSobre(RecursoEspecifico $origen, RecursoEspecifico $destino): void
    {
        if ($origen === $destino) {
            return;
        }

        $destino->setDescripcion($origen->getDescripcion());
        $destino->setGrupo($origen->getGrupo());

        foreach ($destino->getConvocatorias()->toArray() as $convocatoria) {
            $destino->removeConvocatoria($convocatoria);
        }
        foreach ($origen->getConvocatorias() as $convocatoria) {
            $destino->addConvocatoria($convocatoria);
        }

        foreach ($destino->getAlumnos()->toArray() as $alumno) {
            $destino->removeAlumno($alumno);
        }
        foreach ($origen->getAlumnos() as $alumno) {
            $destino->addAlumno($alumno);
        }
    }
}

