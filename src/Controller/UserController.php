<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
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

    #[Route('/{id}/asignar-alumnos', name: 'app_user_asignar_alumnos', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function asignarAlumnos(Request $request, User $profesor, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        // Verificar que el usuario es un profesor
        if (!in_array('ROLE_PROFESOR', $profesor->getRoles()) && !in_array('ROLE_ADMIN', $profesor->getRoles())) {
            $this->addFlash('error', 'Solo se pueden asignar alumnos a profesores.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isMethod('POST')) {
            $alumnosIds = $request->request->all('alumnos') ?? [];
            
            // Obtener todos los alumnos activos
            $todosAlumnos = $userRepository->createQueryBuilder('u')
                ->where('u.activo = :activo')
                ->andWhere('u.roles NOT LIKE :roleProfesor')
                ->andWhere('u.roles NOT LIKE :roleAdmin')
                ->setParameter('activo', true)
                ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
                ->getQuery()
                ->getResult();

            // Limpiar asignaciones actuales
            foreach ($profesor->getAlumnos() as $alumno) {
                $profesor->removeAlumno($alumno);
            }

            // Asignar nuevos alumnos
            foreach ($todosAlumnos as $alumno) {
                if (in_array($alumno->getId(), $alumnosIds)) {
                    $profesor->addAlumno($alumno);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Alumnos asignados correctamente al profesor.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        // Obtener todos los alumnos activos
        $alumnos = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('user/asignar_alumnos.html.twig', [
            'profesor' => $profesor,
            'alumnos' => $alumnos,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Usuario actualizado correctamente.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}

