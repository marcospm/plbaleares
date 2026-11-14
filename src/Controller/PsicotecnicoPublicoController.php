<?php

namespace App\Controller;

use App\Repository\ArchivoPsicotecnicoRepository;
use App\Repository\CategoriaPsicotecnicoRepository;
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
}
