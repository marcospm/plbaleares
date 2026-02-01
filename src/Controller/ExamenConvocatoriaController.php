<?php

namespace App\Controller;

use App\Entity\ExamenConvocatoria;
use App\Form\ExamenConvocatoriaType;
use App\Repository\ExamenConvocatoriaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/examen-convocatoria')]
#[IsGranted('ROLE_PROFESOR')]
class ExamenConvocatoriaController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel
    ) {
    }

    #[Route('/', name: 'app_examen_convocatoria_index', methods: ['GET'])]
    public function index(ExamenConvocatoriaRepository $examenConvocatoriaRepository): Response
    {
        $examenes = $examenConvocatoriaRepository->findAll();
        
        // Ordenar por fecha de subida descendente
        usort($examenes, function($a, $b) {
            return $b->getFechaSubida() <=> $a->getFechaSubida();
        });
        
        return $this->render('examen_convocatoria/index.html.twig', [
            'examenes' => $examenes,
        ]);
    }

    #[Route('/new', name: 'app_examen_convocatoria_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $examenConvocatoria = new ExamenConvocatoria();
        $form = $this->createForm(ExamenConvocatoriaType::class, $examenConvocatoria, ['require_file' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $archivoPDF */
            $archivoPDF = $form->get('archivoPDF')->getData();
            
            if ($archivoPDF) {
                // Validar extensión del archivo
                $extension = strtolower($archivoPDF->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo debe ser un PDF (extensión .pdf).');
                    return $this->render('examen_convocatoria/new.html.twig', [
                        'examenConvocatoria' => $examenConvocatoria,
                        'form' => $form,
                    ]);
                }
                
                $originalFilename = pathinfo($archivoPDF->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/examenes-convocatorias';
                    if (!is_dir($directorio)) {
                        if (!@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
                            throw new FileException('No se pudo crear el directorio. Verifique los permisos del sistema.');
                        }
                    }
                    
                    // Verificar que el directorio sea escribible
                    if (!is_writable($directorio)) {
                        throw new FileException('El directorio no tiene permisos de escritura. Contacte al administrador.');
                    }
                    
                    $archivoPDF->move($directorio, $newFilename);
                    $examenConvocatoria->setRutaArchivo('/examenes-convocatorias/' . $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error al subir el archivo PDF: ' . $e->getMessage());
                    return $this->render('examen_convocatoria/new.html.twig', [
                        'examenConvocatoria' => $examenConvocatoria,
                        'form' => $form,
                    ]);
                }
            } else {
                // Si estamos creando y no hay archivo, mostrar error
                $this->addFlash('error', 'Debes subir un archivo PDF.');
                return $this->render('examen_convocatoria/new.html.twig', [
                    'examenConvocatoria' => $examenConvocatoria,
                    'form' => $form,
                ]);
            }
            
            // Manejar archivo de respuestas (opcional)
            /** @var UploadedFile|null $archivoRespuestas */
            $archivoRespuestas = $form->get('archivoRespuestas')->getData();
            
            if ($archivoRespuestas) {
                $extension = strtolower($archivoRespuestas->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo de respuestas debe ser un PDF (extensión .pdf).');
                    return $this->render('examen_convocatoria/new.html.twig', [
                        'examenConvocatoria' => $examenConvocatoria,
                        'form' => $form,
                    ]);
                }
                
                $originalFilename = pathinfo($archivoRespuestas->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'respuestas-' . $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/examenes-convocatorias';
                    if (!is_dir($directorio)) {
                        if (!@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
                            throw new FileException('No se pudo crear el directorio. Verifique los permisos del sistema.');
                        }
                    }
                    
                    // Verificar que el directorio sea escribible
                    if (!is_writable($directorio)) {
                        throw new FileException('El directorio no tiene permisos de escritura. Contacte al administrador.');
                    }
                    
                    $archivoRespuestas->move($directorio, $newFilename);
                    $examenConvocatoria->setRutaArchivoRespuestas('/examenes-convocatorias/' . $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error al subir el archivo de respuestas: ' . $e->getMessage());
                    return $this->render('examen_convocatoria/new.html.twig', [
                        'examenConvocatoria' => $examenConvocatoria,
                        'form' => $form,
                    ]);
                }
            }
            
            $entityManager->persist($examenConvocatoria);
            $entityManager->flush();

            $this->addFlash('success', 'Examen de otra convocatoria creado correctamente.');
            return $this->redirectToRoute('app_examen_convocatoria_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('examen_convocatoria/new.html.twig', [
            'examenConvocatoria' => $examenConvocatoria,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_examen_convocatoria_show', methods: ['GET'])]
    public function show(ExamenConvocatoria $examenConvocatoria): Response
    {
        return $this->render('examen_convocatoria/show.html.twig', [
            'examenConvocatoria' => $examenConvocatoria,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_examen_convocatoria_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ExamenConvocatoria $examenConvocatoria, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ExamenConvocatoriaType::class, $examenConvocatoria, ['require_file' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $archivoPDF */
            $archivoPDF = $form->get('archivoPDF')->getData();
            
            if ($archivoPDF) {
                // Validar extensión del archivo
                $extension = strtolower($archivoPDF->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo debe ser un PDF.');
                    return $this->render('examen_convocatoria/edit.html.twig', [
                        'examenConvocatoria' => $examenConvocatoria,
                        'form' => $form,
                    ]);
                }
                
                // Eliminar archivo anterior si existe
                if ($examenConvocatoria->getRutaArchivo() && file_exists($this->kernel->getProjectDir() . '/public' . $examenConvocatoria->getRutaArchivo())) {
                    unlink($this->kernel->getProjectDir() . '/public' . $examenConvocatoria->getRutaArchivo());
                }
                
                $originalFilename = pathinfo($archivoPDF->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/examenes-convocatorias';
                    if (!is_dir($directorio)) {
                        if (!@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
                            throw new FileException('No se pudo crear el directorio. Verifique los permisos del sistema.');
                        }
                    }
                    
                    // Verificar que el directorio sea escribible
                    if (!is_writable($directorio)) {
                        throw new FileException('El directorio no tiene permisos de escritura. Contacte al administrador.');
                    }
                    
                    $archivoPDF->move($directorio, $newFilename);
                    $examenConvocatoria->setRutaArchivo('/examenes-convocatorias/' . $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error al subir el archivo PDF: ' . $e->getMessage());
                    return $this->render('examen_convocatoria/edit.html.twig', [
                        'examenConvocatoria' => $examenConvocatoria,
                        'form' => $form,
                    ]);
                }
            }
            
            // Manejar archivo de respuestas (opcional)
            /** @var UploadedFile|null $archivoRespuestas */
            $archivoRespuestas = $form->get('archivoRespuestas')->getData();
            
            if ($archivoRespuestas) {
                $extension = strtolower($archivoRespuestas->getClientOriginalExtension());
                if ($extension !== 'pdf') {
                    $this->addFlash('error', 'El archivo de respuestas debe ser un PDF.');
                    return $this->render('examen_convocatoria/edit.html.twig', [
                        'examenConvocatoria' => $examenConvocatoria,
                        'form' => $form,
                    ]);
                }
                
                // Eliminar archivo anterior si existe
                if ($examenConvocatoria->getRutaArchivoRespuestas() && file_exists($this->kernel->getProjectDir() . '/public' . $examenConvocatoria->getRutaArchivoRespuestas())) {
                    unlink($this->kernel->getProjectDir() . '/public' . $examenConvocatoria->getRutaArchivoRespuestas());
                }
                
                $originalFilename = pathinfo($archivoRespuestas->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'respuestas-' . $safeFilename . '-' . uniqid() . '.pdf';
                
                try {
                    $directorio = $this->kernel->getProjectDir() . '/public/examenes-convocatorias';
                    if (!is_dir($directorio)) {
                        if (!@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
                            throw new FileException('No se pudo crear el directorio. Verifique los permisos del sistema.');
                        }
                    }
                    
                    // Verificar que el directorio sea escribible
                    if (!is_writable($directorio)) {
                        throw new FileException('El directorio no tiene permisos de escritura. Contacte al administrador.');
                    }
                    
                    $archivoRespuestas->move($directorio, $newFilename);
                    $examenConvocatoria->setRutaArchivoRespuestas('/examenes-convocatorias/' . $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error al subir el archivo de respuestas: ' . $e->getMessage());
                    return $this->render('examen_convocatoria/edit.html.twig', [
                        'examenConvocatoria' => $examenConvocatoria,
                        'form' => $form,
                    ]);
                }
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Examen de otra convocatoria actualizado correctamente.');
            return $this->redirectToRoute('app_examen_convocatoria_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('examen_convocatoria/edit.html.twig', [
            'examenConvocatoria' => $examenConvocatoria,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_examen_convocatoria_delete', methods: ['POST'])]
    public function delete(Request $request, ExamenConvocatoria $examenConvocatoria, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$examenConvocatoria->getId(), $request->getPayload()->getString('_token'))) {
            // Eliminar archivo físico
            if ($examenConvocatoria->getRutaArchivo() && file_exists($this->kernel->getProjectDir() . '/public' . $examenConvocatoria->getRutaArchivo())) {
                unlink($this->kernel->getProjectDir() . '/public' . $examenConvocatoria->getRutaArchivo());
            }
            
            if ($examenConvocatoria->getRutaArchivoRespuestas() && file_exists($this->kernel->getProjectDir() . '/public' . $examenConvocatoria->getRutaArchivoRespuestas())) {
                unlink($this->kernel->getProjectDir() . '/public' . $examenConvocatoria->getRutaArchivoRespuestas());
            }
            
            $entityManager->remove($examenConvocatoria);
            $entityManager->flush();
            
            $this->addFlash('success', 'Examen de otra convocatoria eliminado correctamente.');
        }

        return $this->redirectToRoute('app_examen_convocatoria_index', [], Response::HTTP_SEE_OTHER);
    }
}
