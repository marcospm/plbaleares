<?php

namespace App\Controller;

use App\Entity\RecursoEspecifico;
use App\Form\RecursoEspecificoType;
use App\Repository\RecursoEspecificoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
    public function index(RecursoEspecificoRepository $recursoEspecificoRepository): Response
    {
        $profesor = $this->getUser();
        $recursos = $recursoEspecificoRepository->findByProfesor($profesor);

        return $this->render('recurso_especifico/index.html.twig', [
            'recursos' => $recursos,
        ]);
    }

    #[Route('/new', name: 'app_recurso_especifico_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, UserRepository $userRepository): Response
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
                    ->orderBy('u.username', 'ASC');
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
                ->orderBy('u.username', 'ASC');
        }

        $form = $this->createForm(RecursoEspecificoType::class, $recursoEspecifico, [
            'require_file' => true,
            'alumnos_query_builder' => $alumnosQueryBuilder,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $archivo */
            $archivo = $form->get('archivo')->getData();
            
            if ($archivo) {
                // Validar tamaño máximo (50MB)
                if ($archivo->getSize() > 50 * 1024 * 1024) {
                    $this->addFlash('error', 'El archivo es demasiado grande. Tamaño máximo: 50MB.');
                    return $this->render('recurso_especifico/new.html.twig', [
                        'recursoEspecifico' => $recursoEspecifico,
                        'form' => $form,
                    ]);
                }
                
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
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el archivo: ' . $e->getMessage());
                    return $this->render('recurso_especifico/new.html.twig', [
                        'recursoEspecifico' => $recursoEspecifico,
                        'form' => $form,
                    ]);
                }
            } else {
                $this->addFlash('error', 'Debes seleccionar un archivo.');
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
    public function edit(Request $request, RecursoEspecifico $recursoEspecifico, EntityManagerInterface $entityManager, SluggerInterface $slugger, UserRepository $userRepository): Response
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
                    ->orderBy('u.username', 'ASC');
            }
        } else {
            $alumnosQueryBuilder = $userRepository->createQueryBuilder('u')
                ->where('u.activo = :activo')
                ->andWhere('u.roles NOT LIKE :roleProfesor')
                ->andWhere('u.roles NOT LIKE :roleAdmin')
                ->setParameter('activo', true)
                ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
                ->orderBy('u.username', 'ASC');
        }

        $form = $this->createForm(RecursoEspecificoType::class, $recursoEspecifico, [
            'require_file' => false,
            'alumnos_query_builder' => $alumnosQueryBuilder,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $archivo */
            $archivo = $form->get('archivo')->getData();
            
            if ($archivo) {
                // Validar tamaño máximo (50MB)
                if ($archivo->getSize() > 50 * 1024 * 1024) {
                    $this->addFlash('error', 'El archivo es demasiado grande. Tamaño máximo: 50MB.');
                    return $this->render('recurso_especifico/edit.html.twig', [
                        'recursoEspecifico' => $recursoEspecifico,
                        'form' => $form,
                    ]);
                }
                
                // Eliminar archivo anterior si existe
                if ($recursoEspecifico->getRutaArchivo() && file_exists($this->kernel->getProjectDir() . '/public' . $recursoEspecifico->getRutaArchivo())) {
                    unlink($this->kernel->getProjectDir() . '/public' . $recursoEspecifico->getRutaArchivo());
                }
                
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
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el archivo: ' . $e->getMessage());
                    return $this->render('recurso_especifico/edit.html.twig', [
                        'recursoEspecifico' => $recursoEspecifico,
                        'form' => $form,
                    ]);
                }
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
            if ($recursoEspecifico->getRutaArchivo() && file_exists($this->kernel->getProjectDir() . '/public' . $recursoEspecifico->getRutaArchivo())) {
                unlink($this->kernel->getProjectDir() . '/public' . $recursoEspecifico->getRutaArchivo());
            }
            
            $entityManager->remove($recursoEspecifico);
            $entityManager->flush();
            
            $this->addFlash('success', 'Recurso específico eliminado correctamente.');
        }

        return $this->redirectToRoute('app_recurso_especifico_index', [], Response::HTTP_SEE_OTHER);
    }
}

