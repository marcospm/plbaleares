<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\NotificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        NotificacionService $notificacionService
    ): Response {
        // Si el usuario ya está autenticado, redirigir al dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Verificar si el nombre de usuario ya existe (incluyendo eliminados)
            $existingUser = $entityManager->getRepository(User::class)
                ->findOneByIncludingDeleted(['username' => $user->getUsername()]);

            if ($existingUser) {
                $this->addFlash('error', 'Este nombre de usuario ya está en uso. Por favor, elige otro.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            // Verificar si el email ya existe (incluyendo eliminados)
            if ($user->getEmail()) {
                $existingUserByEmail = $entityManager->getRepository(User::class)
                    ->findOneByIncludingDeleted(['email' => $user->getEmail()]);

                if ($existingUserByEmail) {
                    $this->addFlash('error', 'Este email ya está registrado. Por favor, usa otro email o inicia sesión.');
                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form,
                    ]);
                }
            }

            // Encriptar la contraseña
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // El usuario se crea inactivo por defecto
            $user->setActivo(false);
            $user->setRoles(['ROLE_USER']);

            $entityManager->persist($user);
            $entityManager->flush();

            // Notificar a todos los administradores sobre el nuevo registro
            $notificacionService->crearNotificacionRegistroUsuario($user);

            $this->addFlash('success', 'Tu cuenta ha sido creada. Un administrador la activará pronto. Recibirás una notificación cuando puedas iniciar sesión.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}

