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

    private const CARPETA = 'archivos_psicotecnicos';

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
            'grupos' => $archivoRepository->findGruposParaAdmin($categoriaId, $search !== '' ? $search : null),
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
        $form = $this->createForm(ArchivoPsicotecnicoType::class, $archivo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $subidos = $this->normalizarArchivosSubidos($form->get('archivo')->getData());
            $error = $subidos === [] ? 'Debes subir al menos un archivo.' : $this->validarSubidos($subidos);
            if ($error === null) {
                $usuario = $this->getUser();
                if (!$usuario instanceof User) {
                    throw $this->createAccessDeniedException();
                }
                $archivo->setCreadoPor($usuario);
                if (count($subidos) > 1) {
                    $archivo->setLote(bin2hex(random_bytes(16)));
                }
                $error = $this->guardarVarios($archivo, $subidos, $slugger, $entityManager);
            }

            if ($error !== null) {
                $this->addFlash('error', $error);
            } else {
                $cantidad = count($subidos);
                $this->addFlash('success', $cantidad === 1
                    ? 'Archivo publicado. Los alumnos ya pueden verlo en Contenido.'
                    : 'Se han publicado ' . $cantidad . ' archivos juntos. Los alumnos ya pueden verlos en Contenido.');

                return $this->redirectToRoute('app_archivo_psicotecnico_index');
            }
        }

        return $this->render('psicotecnico/archivo_form.html.twig', [
            'archivo' => $archivo,
            'archivosGrupo' => [],
            'form' => $form,
            'titulo' => 'Subir archivos',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_archivo_psicotecnico_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        ArchivoPsicotecnico $archivo,
        ArchivoPsicotecnicoRepository $archivoRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
    ): Response {
        $grupo = $this->archivosDelMismoLote($archivo, $archivoRepository);
        $form = $this->createForm(ArchivoPsicotecnicoType::class, $archivo, [
            'es_grupo' => count($grupo) > 1,
            'edicion' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $error = $this->guardarEdicion($request, $archivo, $grupo, $form->get('archivo')->getData(), $slugger, $entityManager);
            if ($error !== null) {
                $this->addFlash('error', $error);
            } else {
                return $this->redirectToRoute('app_archivo_psicotecnico_index');
            }
        }

        return $this->render('psicotecnico/archivo_form.html.twig', [
            'archivo' => $archivo,
            'archivosGrupo' => $grupo,
            'form' => $form,
            'titulo' => 'Editar archivos',
        ]);
    }

    #[Route('/{id}', name: 'app_archivo_psicotecnico_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        ArchivoPsicotecnico $archivo,
        ArchivoPsicotecnicoRepository $archivoRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $archivo->getId(), $request->getPayload()->getString('_token'))) {
            $grupo = $this->archivosDelMismoLote($archivo, $archivoRepository);
            foreach ($grupo as $item) {
                $this->borrarRuta($item->getRutaArchivo());
                $entityManager->remove($item);
            }
            $entityManager->flush();
            $this->addFlash('success', count($grupo) > 1 ? 'Archivos eliminados.' : 'Archivo eliminado.');
        }

        return $this->redirectToRoute('app_archivo_psicotecnico_index');
    }

    /**
     * @return list<ArchivoPsicotecnico>
     */
    private function archivosDelMismoLote(ArchivoPsicotecnico $archivo, ArchivoPsicotecnicoRepository $repository): array
    {
        $lote = $archivo->getLote();
        if ($lote === null || $lote === '') {
            return [$archivo];
        }

        $grupo = $repository->findByLote($lote);

        return $grupo !== [] ? $grupo : [$archivo];
    }

    /**
     * @param list<ArchivoPsicotecnico> $grupo
     */
    private function guardarEdicion(
        Request $request,
        ArchivoPsicotecnico $archivo,
        array $grupo,
        mixed $subido,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
    ): ?string {
        $nuevos = $this->normalizarArchivosSubidos($subido);
        $error = $this->validarSubidos($nuevos);
        if ($error !== null) {
            return $error;
        }

        $idsEliminar = $this->idsArchivosAEliminar($request);
        $esSustitucion = count($grupo) === 1 && $idsEliminar === [] && count($nuevos) === 1;
        if ($esSustitucion) {
            $error = $this->guardarArchivo($archivo, $nuevos[0], $slugger);
            if ($error !== null) {
                return $error;
            }
            $archivo->setLote(null);
            $entityManager->flush();
            $this->addFlash('success', 'Archivo actualizado.');

            return null;
        }

        $porId = [];
        foreach ($grupo as $item) {
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
        foreach ($grupo as $item) {
            if (!in_array($item, $aEliminar, true)) {
                $seQuedan[] = $item;
            }
        }

        if ($seQuedan === [] && $nuevos === []) {
            return 'Tiene que quedar al menos un archivo.';
        }

        $creados = [];
        foreach ($nuevos as $subidoNuevo) {
            $copia = $this->clonar($archivo);
            $error = $this->guardarArchivo($copia, $subidoNuevo, $slugger);
            if ($error !== null) {
                foreach ($creados as $creado) {
                    $this->borrarRuta($creado->getRutaArchivo());
                }
                $this->borrarRuta($copia->getRutaArchivo());

                return $error;
            }
            $creados[] = $copia;
        }

        $finales = array_merge($seQuedan, $creados);
        $loteFinal = count($finales) > 1
            ? ($archivo->getLote() ?: bin2hex(random_bytes(16)))
            : null;

        foreach ($finales as $item) {
            $item->setLote($loteFinal);
            $this->sincronizar($archivo, $item);
        }

        foreach ($aEliminar as $item) {
            $this->borrarRuta($item->getRutaArchivo());
            $entityManager->remove($item);
        }
        foreach ($creados as $creado) {
            $entityManager->persist($creado);
        }
        $entityManager->flush();

        $mensaje = 'Archivo actualizado.';
        if (count($creados) === 1) {
            $mensaje .= ' Se ha añadido 1 archivo.';
        } elseif (count($creados) > 1) {
            $mensaje .= ' Se han añadido ' . count($creados) . ' archivos.';
        }
        if (count($aEliminar) === 1) {
            $mensaje .= ' Se ha quitado 1 archivo.';
        } elseif (count($aEliminar) > 1) {
            $mensaje .= ' Se han quitado ' . count($aEliminar) . ' archivos.';
        }
        $this->addFlash('success', $mensaje);

        return null;
    }

    /**
     * @param list<UploadedFile> $subidos
     */
    private function guardarVarios(
        ArchivoPsicotecnico $archivo,
        array $subidos,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
    ): ?string {
        $preparados = [];
        foreach ($subidos as $indice => $subido) {
            $destino = $indice === 0 ? $archivo : $this->clonar($archivo);
            $error = $this->guardarArchivo($destino, $subido, $slugger);
            if ($error !== null) {
                foreach ($preparados as $preparado) {
                    $this->borrarRuta($preparado->getRutaArchivo());
                }
                $this->borrarRuta($destino->getRutaArchivo());
                $archivo->setRutaArchivo(null);
                $archivo->setNombreArchivoOriginal(null);
                $archivo->setLote(null);

                return $error;
            }
            $preparados[] = $destino;
        }

        foreach ($preparados as $preparado) {
            $entityManager->persist($preparado);
        }
        $entityManager->flush();

        return null;
    }

    private function clonar(ArchivoPsicotecnico $origen): ArchivoPsicotecnico
    {
        $copia = new ArchivoPsicotecnico();
        $copia->setNombre($origen->getNombre() ?? '');
        $copia->setDescripcion($origen->getDescripcion());
        $copia->setLote($origen->getLote());
        $copia->setCreadoPor($origen->getCreadoPor());
        foreach ($origen->getCategorias() as $categoria) {
            $copia->addCategoria($categoria);
        }

        return $copia;
    }

    private function sincronizar(ArchivoPsicotecnico $origen, ArchivoPsicotecnico $destino): void
    {
        if ($origen === $destino) {
            return;
        }

        $destino->setNombre($origen->getNombre() ?? '');
        $destino->setDescripcion($origen->getDescripcion());
        foreach ($destino->getCategorias()->toArray() as $categoria) {
            $destino->removeCategoria($categoria);
        }
        foreach ($origen->getCategorias() as $categoria) {
            $destino->addCategoria($categoria);
        }
    }

    /**
     * @return list<UploadedFile>
     */
    private function normalizarArchivosSubidos(mixed $subido): array
    {
        if ($subido instanceof UploadedFile) {
            $subido = [$subido];
        }
        if (!is_array($subido)) {
            return [];
        }

        $archivos = [];
        foreach ($subido as $archivo) {
            if ($archivo instanceof UploadedFile && $archivo->getError() !== \UPLOAD_ERR_NO_FILE) {
                $archivos[] = $archivo;
            }
        }

        return $archivos;
    }

    /**
     * @param list<UploadedFile> $subidos
     */
    private function validarSubidos(array $subidos): ?string
    {
        foreach ($subidos as $subido) {
            $error = $this->validarSubido($subido);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private function validarSubido(UploadedFile $subido): ?string
    {
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

        return null;
    }

    private function guardarArchivo(ArchivoPsicotecnico $archivo, UploadedFile $subido, SluggerInterface $slugger): ?string
    {
        $error = $this->validarSubido($subido);
        if ($error !== null) {
            return $error;
        }

        $extension = strtolower($subido->getClientOriginalExtension());
        $anterior = $archivo->getRutaArchivo();
        $original = pathinfo($subido->getClientOriginalName(), PATHINFO_FILENAME);
        $nombre = $slugger->slug($original)->lower() . '-' . uniqid() . '.' . $extension;
        $directorio = $this->directorioArchivos();
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

        $archivo->setRutaArchivo('/' . self::CARPETA . '/' . $nombre);
        $archivo->setNombreArchivoOriginal($subido->getClientOriginalName());

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

    private function borrarRuta(?string $ruta): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }

        $nombre = basename($ruta);
        $path = $this->directorioArchivos() . '/' . $nombre;
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function directorioArchivos(): string
    {
        return $this->kernel->getProjectDir() . '/public/' . self::CARPETA;
    }
}
