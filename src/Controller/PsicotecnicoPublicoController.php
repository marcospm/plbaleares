<?php

namespace App\Controller;

use App\Entity\AperturaArchivoPsicotecnico;
use App\Entity\ArchivoPsicotecnico;
use App\Entity\User;
use App\Repository\ArchivoPsicotecnicoRepository;
use App\Repository\CategoriaPsicotecnicoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/psicotecnicos')]
class PsicotecnicoPublicoController extends AbstractController
{
    #[Route('/', name: 'app_psicotecnico_publico_index', methods: ['GET'])]
    public function index(
        Request $request,
        ArchivoPsicotecnicoRepository $archivoRepository,
        CategoriaPsicotecnicoRepository $categoriaRepository,
    ): Response {
        $search = trim((string) $request->query->get('search', ''));
        $categoriaId = $request->query->getInt('categoria');
        $categoriaId = $categoriaId > 0 ? $categoriaId : null;

        return $this->render('psicotecnico/publico_index.html.twig', [
            'grupos' => $archivoRepository->findGruposVisibles($categoriaId, $search !== '' ? $search : null),
            'categorias' => $categoriaRepository->findActivas(),
            'search' => $search,
            'categoriaId' => $categoriaId,
        ]);
    }

    #[Route('/archivo/{id}/abrir', name: 'app_psicotecnico_publico_abrir', methods: ['GET'])]
    public function abrir(ArchivoPsicotecnico $archivo, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_PROFESOR') && !$this->visibleParaAlumno($archivo)) {
            throw $this->createNotFoundException();
        }

        $ruta = $archivo->getRutaArchivo();
        if (!$this->rutaAbrir($ruta)) {
            throw $this->createNotFoundException();
        }

        $usuario = $this->getUser();
        if ($usuario instanceof User && !$this->isGranted('ROLE_PROFESOR')) {
            $apertura = new AperturaArchivoPsicotecnico();
            $apertura->setArchivo($archivo);
            $apertura->setUsuario($usuario);
            $entityManager->persist($apertura);
            $entityManager->flush();
        }

        return $this->redirect($ruta);
    }

    private function visibleParaAlumno(ArchivoPsicotecnico $archivo): bool
    {
        foreach ($archivo->getCategorias() as $categoria) {
            if ($categoria->isActivo()) {
                return true;
            }
        }

        return false;
    }

    private function rutaAbrir(?string $ruta): bool
    {
        if ($ruta === null || !str_starts_with($ruta, '/archivos_psicotecnicos/')) {
            return false;
        }

        $nombre = substr($ruta, strlen('/archivos_psicotecnicos/'));

        return $nombre !== ''
            && !str_contains($nombre, '/')
            && !str_contains($nombre, '\\')
            && !str_contains($nombre, '..');
    }
}
