<?php

namespace App\Controller;

use App\Entity\Convocatoria;
use App\Entity\ConvocatoriaBase;
use App\Form\ConvocatoriaType;
use App\Repository\ConvocatoriaRepository;
use App\Repository\MunicipioRepository;
use App\Repository\UserRepository;
use App\Service\AsignacionConvocatoriaService;
use App\Service\PdfTitleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/convocatoria')]
#[IsGranted('ROLE_PROFESOR')]
class ConvocatoriaController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel,
        private PdfTitleService $pdfTitleService,
    ) {
    }

    #[Route('/', name: 'app_convocatoria_index', methods: ['GET'])]
    public function index(ConvocatoriaRepository $convocatoriaRepository, MunicipioRepository $municipioRepository, Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $estado = (string) $request->query->get('estado', 'activas');
        if (!in_array($estado, ['activas', 'inactivas', 'todas'], true)) {
            $estado = 'activas';
        }
        $municipioRaw = $request->query->get('municipio');
        $municipioId = is_numeric($municipioRaw) ? (int) $municipioRaw : 0;

        $qb = $convocatoriaRepository->createQueryBuilder('c')
            ->orderBy('c.fechaCreacion', 'DESC');

        if ($search !== '') {
            $qb->andWhere('c.nombre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($estado === 'activas') {
            $qb->andWhere('c.activo = :activo')->setParameter('activo', true);
        } elseif ($estado === 'inactivas') {
            $qb->andWhere('c.activo = :activo')->setParameter('activo', false);
        }

        if ($municipioId > 0) {
            $qb->innerJoin('c.municipios', 'm')
                ->andWhere('m.id = :municipioId')
                ->setParameter('municipioId', $municipioId);
        }

        $convocatorias = $qb->getQuery()->getResult();
        $municipios = $municipioRepository->createQueryBuilder('mun')
            ->orderBy('mun.nombre', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('convocatoria/index.html.twig', [
            'convocatorias' => $convocatorias,
            'search' => $search,
            'estado' => $estado,
            'municipioId' => $municipioId,
            'municipios' => $municipios,
        ]);
    }

    #[Route('/new', name: 'app_convocatoria_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, AsignacionConvocatoriaService $asignacionConvocatoriaService, SluggerInterface $slugger): Response
    {
        $convocatoria = new Convocatoria();
        $form = $this->createForm(ConvocatoriaType::class, $convocatoria);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errorBases = $this->guardarBasesPdf($request, $form, $convocatoria, $slugger, $entityManager);
            if ($errorBases !== null) {
                $this->addFlash('error', $errorBases);

                return $this->render('convocatoria/new.html.twig', [
                    'convocatoria' => $convocatoria,
                    'form' => $form,
                ]);
            }

            $convocatoria->setFechaActualizacion(new \DateTime());
            $entityManager->persist($convocatoria);
            $asignacionConvocatoriaService->asignarAUsuariosSinAsignar($convocatoria);
            $entityManager->flush();

            $this->addFlash('success', 'Convocatoria creada correctamente.');
            return $this->redirectToRoute('app_convocatoria_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('convocatoria/new.html.twig', [
            'convocatoria' => $convocatoria,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_convocatoria_show', methods: ['GET'])]
    public function show(Convocatoria $convocatoria): Response
    {
        return $this->render('convocatoria/show.html.twig', [
            'convocatoria' => $convocatoria,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_convocatoria_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Convocatoria $convocatoria, EntityManagerInterface $entityManager, SluggerInterface $slugger, ConvocatoriaRepository $convocatoriaRepository): Response
    {
        $estabaActiva = $convocatoria->isActivo();
        $form = $this->createForm(ConvocatoriaType::class, $convocatoria);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errorBases = $this->guardarBasesPdf($request, $form, $convocatoria, $slugger, $entityManager);
            if ($errorBases !== null) {
                $this->addFlash('error', $errorBases);

                return $this->render('convocatoria/edit.html.twig', [
                    'convocatoria' => $convocatoria,
                    'form' => $form,
                ]);
            }

            $municipiosCambiados = $this->sincronizarMunicipiosConEstado($convocatoria, $estabaActiva, $convocatoriaRepository);
            $convocatoria->setFechaActualizacion(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', $estabaActiva === $convocatoria->isActivo()
                ? 'Convocatoria actualizada correctamente.'
                : 'Convocatoria actualizada. ' . $this->mensajeCambioEstado($convocatoria, $municipiosCambiados));
            return $this->redirectToRoute('app_convocatoria_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('convocatoria/edit.html.twig', [
            'convocatoria' => $convocatoria,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_convocatoria_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Request $request, Convocatoria $convocatoria, EntityManagerInterface $entityManager, ConvocatoriaRepository $convocatoriaRepository): Response
    {
        $estabaActiva = $convocatoria->isActivo();
        $convocatoria->setActivo(!$estabaActiva);
        $municipiosCambiados = $this->sincronizarMunicipiosConEstado($convocatoria, $estabaActiva, $convocatoriaRepository);
        $convocatoria->setFechaActualizacion(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', $this->mensajeCambioEstado($convocatoria, $municipiosCambiados));

        $filtros = [];
        $search = trim((string) $request->request->get('search', ''));
        $estado = (string) $request->request->get('estado', '');
        $municipio = (string) $request->request->get('municipio', '');
        if ($search !== '') {
            $filtros['search'] = $search;
        }
        if (in_array($estado, ['activas', 'inactivas', 'todas'], true)) {
            $filtros['estado'] = $estado;
        }
        if ($municipio !== '' && $municipio !== '0') {
            $filtros['municipio'] = $municipio;
        }

        return $this->redirectToRoute('app_convocatoria_index', $filtros);
    }

    #[Route('/{id}/asignar-alumnos', name: 'app_convocatoria_asignar_alumnos', methods: ['GET', 'POST'])]
    public function asignarAlumnos(
        Request $request,
        Convocatoria $convocatoria,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            $usuarioIds = $request->request->all()['usuarios'] ?? [];
            
            // Limpiar usuarios actuales
            foreach ($convocatoria->getUsuarios() as $usuario) {
                $convocatoria->removeUsuario($usuario);
            }
            
            // Añadir usuarios seleccionados
            if (!empty($usuarioIds)) {
                $usuarios = $userRepository->findBy(['id' => $usuarioIds]);
                foreach ($usuarios as $usuario) {
                    $convocatoria->addUsuario($usuario);
                }
            }
            
            $convocatoria->setFechaActualizacion(new \DateTime());
            $entityManager->flush();
            
            $this->addFlash('success', 'Alumnos asignados correctamente.');
            return $this->redirectToRoute('app_convocatoria_index');
        }

        // Obtener todos los usuarios activos que no sean profesores
        $todosUsuarios = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
        
        $usuarios = array_filter($todosUsuarios, function($usuario) {
            $roles = $usuario->getRoles();
            return !in_array('ROLE_PROFESOR', $roles) && !in_array('ROLE_ADMIN', $roles);
        });

        return $this->render('convocatoria/asignar_alumnos.html.twig', [
            'convocatoria' => $convocatoria,
            'usuarios' => $usuarios,
        ]);
    }

    #[Route('/{id}', name: 'app_convocatoria_delete', methods: ['POST'])]
    public function delete(Request $request, Convocatoria $convocatoria, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$convocatoria->getId(), $request->request->getString('_token'))) {
            $this->eliminarArchivosBases($convocatoria);
            $entityManager->remove($convocatoria);
            $entityManager->flush();
            $this->addFlash('success', 'Convocatoria eliminada correctamente.');
        }

        return $this->redirectToRoute('app_convocatoria_index', [], Response::HTTP_SEE_OTHER);
    }

    private function guardarBasesPdf(Request $request, FormInterface $form, Convocatoria $convocatoria, SluggerInterface $slugger, EntityManagerInterface $entityManager): ?string
    {
        $archivos = $this->archivosBases($form);
        foreach ($archivos as $archivo) {
            if ($archivo->getError() !== UPLOAD_ERR_OK) {
                return 'No se pudo subir uno de los PDF. Comprueba que no supere el tamaño máximo.';
            }

            $extension = strtolower($archivo->getClientOriginalExtension());
            if ($extension !== 'pdf') {
                return 'Los documentos deben ser archivos PDF.';
            }

            if ($archivo->getSize() > 30 * 1024 * 1024) {
                return 'Cada PDF puede tener como máximo 30 MB.';
            }
        }

        $directorio = $this->kernel->getProjectDir() . '/public/pdfs/bases';
        if ($archivos !== [] && !is_dir($directorio) && !@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            return 'No se pudo crear la carpeta para guardar los documentos.';
        }

        $creadas = [];
        foreach ($archivos as $archivo) {
            $nombreOriginal = $archivo->getClientOriginalName();
            $nombreOriginal = $nombreOriginal !== '' ? $nombreOriginal : 'bases.pdf';
            if (mb_strlen($nombreOriginal) > 255) {
                $nombreOriginal = mb_substr($nombreOriginal, 0, 255);
            }

            $originalFilename = pathinfo($nombreOriginal, PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename)->toString();
            if ($safeFilename === '') {
                $safeFilename = 'bases';
            }
            $newFilename = 'bases-' . $safeFilename . '-' . uniqid() . '.pdf';

            try {
                $archivo->move($directorio, $newFilename);
            } catch (FileException $e) {
                $this->descartarBasesNuevas($convocatoria, $creadas);

                return 'Error al subir un documento: ' . $e->getMessage();
            }

            $base = new ConvocatoriaBase();
            $base->setNombreOriginal($nombreOriginal);
            $base->setRutaArchivo('/pdfs/bases/' . $newFilename);
            $convocatoria->addBase($base);
            $creadas[] = $base;

            $titulo = $originalFilename;
            if (mb_strlen($titulo) > 200) {
                $titulo = mb_substr($titulo, 0, 200);
            }
            $this->aplicarTituloPdf($directorio . '/' . $newFilename, $titulo);
        }

        $this->eliminarBasesMarcadas($request, $convocatoria, $entityManager);

        return null;
    }

    /**
     * @return list<UploadedFile>
     */
    private function archivosBases(FormInterface $form): array
    {
        $data = $form->get('basesPdf')->getData();
        if ($data instanceof UploadedFile) {
            $data = [$data];
        }
        if (!is_array($data)) {
            return [];
        }

        $archivos = [];
        foreach ($data as $archivo) {
            if ($archivo instanceof UploadedFile && $archivo->getError() !== UPLOAD_ERR_NO_FILE) {
                $archivos[] = $archivo;
            }
        }

        return $archivos;
    }

    /**
     * @param list<ConvocatoriaBase> $creadas
     */
    private function descartarBasesNuevas(Convocatoria $convocatoria, array $creadas): void
    {
        foreach ($creadas as $base) {
            $this->eliminarArchivoBase($base);
            $convocatoria->removeBase($base);
        }
    }

    private function eliminarBasesMarcadas(Request $request, Convocatoria $convocatoria, EntityManagerInterface $entityManager): void
    {
        if (!$request->request->has('eliminar_bases')) {
            return;
        }

        $ids = $request->request->all('eliminar_bases');
        if (!is_array($ids)) {
            return;
        }

        $ids = array_map('intval', $ids);
        foreach ($convocatoria->getBases()->toArray() as $base) {
            if (!in_array((int) $base->getId(), $ids, true)) {
                continue;
            }

            $this->eliminarArchivoBase($base);
            $convocatoria->removeBase($base);
            $entityManager->remove($base);
        }
    }

    private function eliminarArchivosBases(Convocatoria $convocatoria): void
    {
        foreach ($convocatoria->getBases()->toArray() as $base) {
            $this->eliminarArchivoBase($base);
        }
    }

    private function eliminarArchivoBase(ConvocatoriaBase $base): void
    {
        $ruta = $base->getRutaArchivo();
        if (!$ruta || !str_starts_with($ruta, '/pdfs/bases/') || str_contains($ruta, '..')) {
            return;
        }

        $rutaAbsoluta = $this->kernel->getProjectDir() . '/public' . $ruta;
        if (is_file($rutaAbsoluta)) {
            @unlink($rutaAbsoluta);
        }
    }

    private function aplicarTituloPdf(string $rutaAbsoluta, string $titulo): void
    {
        if (!is_file($rutaAbsoluta)) {
            return;
        }

        try {
            $this->pdfTitleService->applyTitleToFile($rutaAbsoluta, $titulo);
        } catch (\Throwable) {
            // El PDF sigue siendo válido si no se puede escribir el título.
        }
    }

    private function sincronizarMunicipiosConEstado(
        Convocatoria $convocatoria,
        bool $estabaActiva,
        ConvocatoriaRepository $convocatoriaRepository,
    ): int {
        if ($estabaActiva === $convocatoria->isActivo()) {
            return 0;
        }

        $cambiados = 0;
        foreach ($convocatoria->getMunicipios() as $municipio) {
            if ($convocatoria->isActivo()) {
                if (!$municipio->isActivo()) {
                    $municipio->setActivo(true);
                    $cambiados++;
                }
                continue;
            }

            if (!$municipio->isActivo()) {
                continue;
            }

            if ($convocatoriaRepository->contarOtrasActivasConMunicipio($municipio, $convocatoria) > 0) {
                continue;
            }

            $municipio->setActivo(false);
            $cambiados++;
        }

        return $cambiados;
    }

    private function mensajeCambioEstado(Convocatoria $convocatoria, int $municipiosCambiados): string
    {
        if ($convocatoria->isActivo()) {
            $mensaje = 'Convocatoria activada.';
            if ($municipiosCambiados === 1) {
                $mensaje .= ' Se ha activado 1 municipio.';
            } elseif ($municipiosCambiados > 1) {
                $mensaje .= ' Se han activado ' . $municipiosCambiados . ' municipios.';
            }

            return $mensaje;
        }

        $mensaje = 'Convocatoria desactivada.';
        if ($municipiosCambiados === 1) {
            $mensaje .= ' Se ha desactivado 1 municipio.';
        } elseif ($municipiosCambiados > 1) {
            $mensaje .= ' Se han desactivado ' . $municipiosCambiados . ' municipios.';
        }

        return $mensaje;
    }
}












