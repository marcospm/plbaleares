<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('ROLE_PROFESOR')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $activo = $request->query->get('activo', '');

        $users = $userRepository->findAll();

        // Filtrar por búsqueda
        if (!empty($search)) {
            $users = array_filter($users, function($user) use ($search) {
                return stripos($user->getUsername(), $search) !== false;
            });
        }

        // Filtrar por estado activo
        if ($activo !== '') {
            $activoBool = $activo === '1';
            $users = array_filter($users, function($user) use ($activoBool) {
                return $user->isActivo() === $activoBool;
            });
        }

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'activoFiltro' => $activo,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_user_toggle_activo', methods: ['POST'])]
    public function toggleActivo(User $user, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $user->setActivo(!$user->isActivo());
            $entityManager->flush();

            $estado = $user->isActivo() ? 'activada' : 'desactivada';
            $this->addFlash('success', "La cuenta del usuario '{$user->getUsername()}' ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}

