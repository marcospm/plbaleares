<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        $mostrarTodos = $request->query->getBoolean('mostrar_todos', false);

        // Parámetros de paginación
        $itemsPerPage = 20; // Número de usuarios por página
        $page = max(1, $request->query->getInt('page', 1));

        // Si no se marca la casilla "mostrar todos", solo mostrar usuarios activos
        // Si se marca, mostrar todos (activos e inactivos)
        $activo = $mostrarTodos ? null : '1';

        // Obtener usuarios con paginación y filtros a nivel de base de datos
        $result = $userRepository->findPaginated($search, $activo, $page, $itemsPerPage);
        $users = $result['users'];
        $totalItems = $result['total'];
        
        // Calcular total de páginas
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages); // Asegurar que la página no exceda el total

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'mostrarTodos' => $mostrarTodos,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Verificar si el nombre de usuario ya existe
            $existingUser = $entityManager->getRepository(User::class)
                ->findOneBy(['username' => $user->getUsername()]);

            if ($existingUser) {
                $this->addFlash('error', 'Este nombre de usuario ya está en uso. Por favor, elige otro.');
                return $this->render('user/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            // Verificar si el email ya existe (si se proporcionó)
            if ($user->getEmail()) {
                $existingUserByEmail = $entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $user->getEmail()]);

                if ($existingUserByEmail) {
                    $this->addFlash('error', 'Este email ya está registrado. Por favor, usa otro email.');
                    return $this->render('user/new.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
            }

            // Encriptar la contraseña si se proporcionó
            if ($form->get('plainPassword')->getData()) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                );
                $user->setPassword($hashedPassword);
            }

            // Asegurar que el usuario tenga al menos ROLE_USER
            $roles = $user->getRoles();
            if (empty($roles) || !in_array('ROLE_USER', $roles)) {
                $roles[] = 'ROLE_USER';
                $user->setRoles(array_unique($roles));
            }

            // El usuario se crea inactivo por defecto
            $user->setActivo(false);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Usuario creado correctamente.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
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
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Actualizar contraseña si se proporcionó una nueva
            if ($form->get('plainPassword')->getData()) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                );
                $user->setPassword($hashedPassword);
            }

            // Asegurar que el usuario tenga al menos ROLE_USER
            // El formulario ya establece los roles, pero verificamos que tenga ROLE_USER
            // Usamos ReflectionProperty para acceder a la propiedad privada roles
            $reflection = new \ReflectionClass($user);
            $rolesProperty = $reflection->getProperty('roles');
            $rolesProperty->setAccessible(true);
            $currentRoles = $rolesProperty->getValue($user);
            
            if (empty($currentRoles) || !in_array('ROLE_USER', $currentRoles)) {
                $currentRoles[] = 'ROLE_USER';
                $user->setRoles(array_unique($currentRoles));
            }
            
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

