<?php

namespace App\Controller;

use App\Entity\ArchivoPsicotecnico;
use App\Entity\User;
use App\Form\ArchivoPsicotecnicoType;
use App\Repository\ArchivoPsicotecnicoRepository;
use App\Repository\CategoriaPsicotecnicoRepository;
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

#[Route('/psicotecnico/archivo')]
#[IsGranted('ROLE_ADMIN')]
class ArchivoPsicotecnicoController extends AbstractController
{
    private const EXTENSIONES = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'odt', 'ods', 'odp'];

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    #[Route('/', name: 'app_archivo_psicotecnico_index', methods: ['GET'])]
    public function index(
        Request $request,
        ArchivoPsicotecnicoRepository $archivoRepository,
        CategoriaPsicotecnicoRepository $categoriaRepository,
    ): Response {
        $search = trim((string) $request->query->get('search', ''));
        $categoriaId = $request->query->getInt('categoria');
        $categoriaId = $categoriaId > 0 ? $categoriaId : null;

        return $this->render('psicotecnico/archivo_index.html.twig', [
            'archivos' => $archivoRepository->findParaAdmin($categoriaId, $search !== '' ? $search : null),
            'categorias' => $categoriaRepository->findOrdenadas(),
            'search' => $search,
            'categoriaId' => $categoriaId,
        ]);
    }

    #[Route('/new', name: 'app_archivo_psicotecnico_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
    ): Response {
        $archivo = new ArchivoPsicotecnico();
        $form = $this->createForm(ArchivoPsicotecnicoType::class, $archivo, ['requerir_archivo' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $error = $this->guardarArchivo($archivo, $form->get('archivo')->getData(), $slugger, true);
            if ($error !== null) {
                $this->addFlash('error', $error);
            } else {
                $usuario = $this->getUser();
                if (!$usuario instanceof User) {
                    throw $this->createAccessDeniedException();
                }
                $archivo->setCreadoPor($usuario);
                $entityManager->persist($archivo);
                $entityManager->flush();
                $this->addFlash('success', 'Archivo publicado. Los alumnos ya pueden verlo en Contenido.');

                return $this->redirectToRoute('app_archivo_psicotecnico_index');
            }
        }

        return $this->render('psicotecnico/archivo_form.html.twig', [
            'archivo' => $archivo,
            'form' => $form,
            'titulo' => 'Nuevo archivo',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_archivo_psicotecnico_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        ArchivoPsicotecnico $archivo,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
    ): Response {
        $form = $this->createForm(ArchivoPsicotecnicoType::class, $archivo, ['requerir_archivo' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $error = $this->guardarArchivo($archivo, $form->get('archivo')->getData(), $slugger, false);
            if ($error !== null) {
                $this->addFlash('error', $error);
            } else {
                $entityManager->flush();
                $this->addFlash('success', 'Archivo actualizado.');

                return $this->redirectToRoute('app_archivo_psicotecnico_index');
            }
        }

        return $this->render('psicotecnico/archivo_form.html.twig', [
            'archivo' => $archivo,
            'form' => $form,
            'titulo' => 'Editar archivo',
        ]);
    }

    #[Route('/{id}', name: 'app_archivo_psicotecnico_delete', methods: ['POST'])]
    public function delete(Request $request, ArchivoPsicotecnico $archivo, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $archivo->getId(), $request->getPayload()->getString('_token'))) {
            $this->borrarRuta($archivo->getRutaArchivo());
            $entityManager->remove($archivo);
            $entityManager->flush();
            $this->addFlash('success', 'Archivo eliminado.');
        }

        return $this->redirectToRoute('app_archivo_psicotecnico_index');
    }

    private function guardarArchivo(ArchivoPsicotecnico $archivo, mixed $subido, SluggerInterface $slugger, bool $obligatorio): ?string
    {
        if (!$subido instanceof UploadedFile || $subido->getError() === \UPLOAD_ERR_NO_FILE) {
            if ($obligatorio || !$archivo->tieneArchivo()) {
                return 'Debes subir un archivo.';
            }

            return null;
        }

        if (!$subido->isValid()) {
            return 'No se pudo subir el archivo.';
        }

        $extension = strtolower($subido->getClientOriginalExtension());
        if (!in_array($extension, self::EXTENSIONES, true)) {
            return 'Formato no permitido. Usa PDF, Office, imagen, ZIP u ODT.';
        }

        if ($subido->getSize() > 100 * 1024 * 1024) {
            return 'El archivo es demasiado grande. Tamaño máximo: 100 MB.';
        }

        $anterior = $archivo->getRutaArchivo();
        $original = pathinfo($subido->getClientOriginalName(), PATHINFO_FILENAME);
        $nombre = $slugger->slug($original)->lower() . '-' . uniqid() . '.' . $extension;
        $directorio = $this->kernel->getProjectDir() . '/public/psicotecnicos';
        if (!is_dir($directorio) && !@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            return 'No se pudo crear la carpeta de archivos.';
        }

        try {
            $subido->move($directorio, $nombre);
        } catch (FileException) {
            return 'Error al guardar el archivo.';
        }

        if ($anterior) {
            $this->borrarRuta($anterior);
        }

        $archivo->setRutaArchivo('/psicotecnicos/' . $nombre);
        $archivo->setNombreArchivoOriginal($subido->getClientOriginalName());

        return null;
    }

    private function borrarRuta(?string $ruta): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }

        $nombre = basename($ruta);
        $path = $this->kernel->getProjectDir() . '/public/psicotecnicos/' . $nombre;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
