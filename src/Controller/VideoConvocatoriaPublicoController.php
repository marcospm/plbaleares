<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\VideoConvocatoria;
use App\Repository\ConvocatoriaRepository;
use App\Repository\VideoConvocatoriaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/videos-convocatoria')]
#[IsGranted('ROLE_USER')]
class VideoConvocatoriaPublicoController extends AbstractController
{
    #[Route('', name: 'app_video_convocatoria_publico_index', methods: ['GET'])]
    public function index(
        VideoConvocatoriaRepository $videoRepository,
        ConvocatoriaRepository $convocatoriaRepository,
        Request $request
    ): Response {
        $usuario = $this->getUser();
        if (!$usuario instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $search = trim((string) $request->query->get('search', ''));
        $convocatoriaId = $request->query->get('convocatoria', '');
        $convocatoriaId = $convocatoriaId !== '' && $convocatoriaId !== null ? (int) $convocatoriaId : null;

        $alumno = $this->isGranted('ROLE_PROFESOR') ? null : $usuario;

        $itemsPerPage = 12;
        $page = max(1, $request->query->getInt('page', 1));
        $result = $videoRepository->findPaginated($search !== '' ? $search : null, $convocatoriaId, $alumno, $page, $itemsPerPage);
        $totalItems = $result['total'];
        $totalPages = max(1, (int) ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);

        if ($page !== max(1, $request->query->getInt('page', 1)) && $totalItems > 0) {
            $result = $videoRepository->findPaginated($search !== '' ? $search : null, $convocatoriaId, $alumno, $page, $itemsPerPage);
        }

        $convocatorias = $this->isGranted('ROLE_PROFESOR')
            ? $convocatoriaRepository->findBy([], ['nombre' => 'ASC'])
            : $convocatoriaRepository->findByUsuario($usuario);

        return $this->render('video_convocatoria/publico_index.html.twig', [
            'videos' => $result['videos'],
            'search' => $search,
            'convocatoriaId' => $convocatoriaId,
            'convocatorias' => $convocatorias,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]);
    }

    #[Route('/{id}', name: 'app_video_convocatoria_publico_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(VideoConvocatoria $video): Response
    {
        $this->comprobarAcceso($video);

        return $this->render('video_convocatoria/publico_show.html.twig', [
            'video' => $video,
        ]);
    }

    private function comprobarAcceso(VideoConvocatoria $video): void
    {
        if ($this->isGranted('ROLE_PROFESOR')) {
            return;
        }

        $usuario = $this->getUser();
        if (!$usuario instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $convocatoria = $video->getConvocatoria();
        if ($convocatoria === null || !$convocatoria->isActivo()) {
            throw $this->createAccessDeniedException('No tienes acceso a los vídeos de esta convocatoria.');
        }

        foreach ($convocatoria->getUsuarios() as $asignado) {
            if ($asignado->getId() === $usuario->getId()) {
                return;
            }
        }

        throw $this->createAccessDeniedException('No tienes acceso a los vídeos de esta convocatoria.');
    }
}
