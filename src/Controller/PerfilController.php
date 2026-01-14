<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PerfilType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/perfil')]
#[IsGranted('ROLE_USER')]
class PerfilController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository
    ) {
    }

    #[Route('/', name: 'app_perfil_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        // Obtener el usuario completo de la base de datos
        $user = $this->userRepository->find($user->getId());
        
        if (!$user) {
            $this->addFlash('error', 'Usuario no encontrado.');
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(PerfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Verificar si el email ya existe en otro usuario
            if ($user->getEmail()) {
                $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', 'Este email ya está registrado por otro usuario.');
                    return $this->render('perfil/index.html.twig', [
                        'form' => $form,
                        'user' => $user,
                    ]);
                }
            }

            // Si se proporcionó una nueva contraseña, hashearla
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Tu perfil se ha actualizado correctamente.');
            return $this->redirectToRoute('app_perfil_index');
        }

        return $this->render('perfil/index.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
