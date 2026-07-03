<?php

namespace App\Controller;

use App\Entity\RecursoEspecifico;
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
        
        // Filtrar recursos por búsqueda si existe
        if (!empty($search)) {
            $recursos = $recursoEspecificoRepository->createQueryBuilder('r')
                ->where('r.profesor = :profesor')
                ->andWhere('(r.nombre LIKE :search OR r.descripcion LIKE :search)')
                ->setParameter('profesor', $profesor)
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('r.fechaCreacion', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $recursos = $recursoEspecificoRepository->findByProfesor($profesor);
        }

        return $this->render('recurso_especifico/index.html.twig', [
            'recursos' => $recursos,
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
            
            // Validar que hay al menos un alumno asignado o un grupo
            if ($recursoEspecifico->getAlumnos()->isEmpty() && $grupo === null) {
                $this->addFlash('error', 'Debes asignar el recurso a al menos un alumno o seleccionar un grupo.');
                return $this->render('recurso_especifico/new.html.twig', [
                    'recursoEspecifico' => $recursoEspecifico,
                    'form' => $form,
                ]);
            }
            
            /** @var UploadedFile|null $archivo */
            $archivo = $form->get('archivo')->getData();
            $tipoRecurso = (string) $form->get('tipoRecurso')->getData();

            $errorContenido = $this->procesarArchivoOEnlace(
                $recursoEspecifico,
                $archivo,
                $tipoRecurso,
                $slugger,
                true
            );
            if ($errorContenido !== null) {
                $this->addFlash('error', $errorContenido);
                return $this->render('recurso_especifico/new.html.twig', [
                    'recursoEspecifico' => $recursoEspecifico,
                    'form' => $form,
                ]);
            }
            
            $entityManager->persist($recursoEspecifico);
            $entityManager->flush();

            $this->addFlash('success', 'Recurso específico creado correctamente.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recurso_especifico/new.html.twig', [
            'recursoEspecifico' => $recursoEspecifico,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recurso_especifico_show', methods: ['GET'])]
    public function show(RecursoEspecifico $recursoEspecifico): Response
    {
        // Verificar que el profesor tiene acceso a este recurso
        $profesor = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $recursoEspecifico->getProfesor()->getId() !== $profesor->getId()) {
            $this->addFlash('error', 'No tienes acceso a este recurso.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recurso_especifico/show.html.twig', [
            'recursoEspecifico' => $recursoEspecifico,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recurso_especifico_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RecursoEspecifico $recursoEspecifico, EntityManagerInterface $entityManager, SluggerInterface $slugger, UserRepository $userRepository, GrupoRepository $grupoRepository): Response
    {
        // Verificar que el profesor tiene acceso a este recurso
        $profesor = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $recursoEspecifico->getProfesor()->getId() !== $profesor->getId()) {
            $this->addFlash('error', 'No tienes acceso a este recurso.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

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
            
            // Validar que hay al menos un alumno asignado o un grupo
            if ($recursoEspecifico->getAlumnos()->isEmpty() && $grupo === null) {
                $this->addFlash('error', 'Debes asignar el recurso a al menos un alumno o seleccionar un grupo.');
                return $this->render('recurso_especifico/edit.html.twig', [
                    'recursoEspecifico' => $recursoEspecifico,
                    'form' => $form,
                ]);
            }
            
            /** @var UploadedFile|null $archivo */
            $archivo = $form->get('archivo')->getData();
            $tipoRecurso = (string) $form->get('tipoRecurso')->getData();

            $errorContenido = $this->procesarArchivoOEnlace(
                $recursoEspecifico,
                $archivo,
                $tipoRecurso,
                $slugger,
                false
            );
            if ($errorContenido !== null) {
                $this->addFlash('error', $errorContenido);
                return $this->render('recurso_especifico/edit.html.twig', [
                    'recursoEspecifico' => $recursoEspecifico,
                    'form' => $form,
                ]);
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Recurso específico actualizado correctamente.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recurso_especifico/edit.html.twig', [
            'recursoEspecifico' => $recursoEspecifico,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recurso_especifico_delete', methods: ['POST'])]
    public function delete(Request $request, RecursoEspecifico $recursoEspecifico, EntityManagerInterface $entityManager): Response
    {
        // Verificar que el profesor tiene acceso a este recurso
        $profesor = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $recursoEspecifico->getProfesor()->getId() !== $profesor->getId()) {
            $this->addFlash('error', 'No tienes acceso a este recurso.');
            return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$recursoEspecifico->getId(), $request->getPayload()->getString('_token'))) {
            // Eliminar archivo físico si existe
            if ($recursoEspecifico->tieneArchivo()) {
                $rutaFisica = $this->kernel->getProjectDir() . '/public' . $recursoEspecifico->getRutaArchivo();
                if (file_exists($rutaFisica)) {
                    unlink($rutaFisica);
                }
            }
            
            $entityManager->remove($recursoEspecifico);
            $entityManager->flush();
            
            $this->addFlash('success', 'Recurso específico eliminado correctamente.');
        }

        return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
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

    private function addFlashSiErrorSubidaArchivo(FormInterface $form): void
    {
        if (!$form->has('archivo')) {
            return;
        }

        $archivo = $form->get('archivo')->getData();
        if (!$archivo instanceof UploadedFile || $archivo->isValid()) {
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
}

