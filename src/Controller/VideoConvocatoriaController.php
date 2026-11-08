<?php

namespace App\Controller;

use App\Entity\Convocatoria;
use App\Entity\User;
use App\Entity\VideoConvocatoria;
use App\Form\VideoConvocatoriaType;
use App\Repository\ConvocatoriaRepository;
use App\Repository\VideoConvocatoriaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/video-convocatoria')]
#[IsGranted('ROLE_PROFESOR')]
class VideoConvocatoriaController extends AbstractController
{
    #[Route('', name: 'app_video_convocatoria_index', methods: ['GET'])]
    public function index(
        VideoConvocatoriaRepository $videoRepository,
        ConvocatoriaRepository $convocatoriaRepository,
        Request $request
    ): Response {
        $search = trim((string) $request->query->get('search', ''));
        $convocatoriaId = $request->query->get('convocatoria', '');
        $convocatoriaId = $convocatoriaId !== '' && $convocatoriaId !== null ? (int) $convocatoriaId : null;

        $itemsPerPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $result = $videoRepository->findPaginated($search !== '' ? $search : null, $convocatoriaId, null, $page, $itemsPerPage);
        $totalItems = $result['total'];
        $totalPages = max(1, (int) ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);

        if ($page !== max(1, $request->query->getInt('page', 1)) && $totalItems > 0) {
            $result = $videoRepository->findPaginated($search !== '' ? $search : null, $convocatoriaId, null, $page, $itemsPerPage);
        }

        return $this->render('video_convocatoria/index.html.twig', [
            'videos' => $result['videos'],
            'search' => $search,
            'convocatoriaId' => $convocatoriaId,
            'convocatorias' => $convocatoriaRepository->findBy([], ['nombre' => 'ASC']),
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]);
    }

    #[Route('/new', name: 'app_video_convocatoria_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $video = new VideoConvocatoria();
        $usuario = $this->getUser();
        if ($usuario instanceof User) {
            $video->setCreadoPor($usuario);
        }

        $convocatoriaId = $request->query->getInt('convocatoria');
        if ($convocatoriaId > 0) {
            $convocatoria = $entityManager->getRepository(Convocatoria::class)->find($convocatoriaId);
            if ($convocatoria instanceof Convocatoria) {
                $video->setConvocatoria($convocatoria);
            }
        }

        $form = $this->createForm(VideoConvocatoriaType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($video);
            $entityManager->flush();

            $this->addFlash('success', 'Vídeo de convocatoria creado correctamente.');

            return $this->redirectToRoute('app_video_convocatoria_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('video_convocatoria/new.html.twig', [
            'video' => $video,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_video_convocatoria_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(VideoConvocatoria $video): Response
    {
        return $this->render('video_convocatoria/show.html.twig', [
            'video' => $video,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_video_convocatoria_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, VideoConvocatoria $video, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(VideoConvocatoriaType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Vídeo de convocatoria actualizado correctamente.');

            return $this->redirectToRoute('app_video_convocatoria_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('video_convocatoria/edit.html.twig', [
            'video' => $video,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_video_convocatoria_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, VideoConvocatoria $video, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $video->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($video);
            $entityManager->flush();
            $this->addFlash('success', 'Vídeo de convocatoria eliminado correctamente.');
        }

        return $this->redirectToRoute('app_video_convocatoria_index', [], Response::HTTP_SEE_OTHER);
    }
}
