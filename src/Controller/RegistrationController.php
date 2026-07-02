<?php

namespace App\Controller;

use App\Dto\SolicitudCuentaDto;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\SolicitudCuentaFormType;
use App\Repository\ConvocatoriaRepository;
use App\Service\NotificacionService;
use App\Service\SolicitudCuentaMailService;
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
        SolicitudCuentaMailService $solicitudCuentaMailService,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $solicitud = new SolicitudCuentaDto();
        $form = $this->createForm(SolicitudCuentaFormType::class, $solicitud);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $solicitudCuentaMailService->enviarSolicitud($solicitud);
            } catch (\Throwable) {
                $this->addFlash('error', 'No se pudo enviar la solicitud. Inténtalo de nuevo más tarde o escríbenos a info@bispol.es.');

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            $this->addFlash('success', 'Hemos recibido tu solicitud. Nos pondremos en contacto contigo lo antes posible.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Registro clásico con creación de usuario inactivo (no usado actualmente).
     * Se mantiene por si se quiere reactivar en el futuro.
     */
    private function crearUsuarioInactivo(
        User $user,
        string $plainPassword,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        NotificacionService $notificacionService,
        ConvocatoriaRepository $convocatoriaRepository,
    ): void {
        $existingUser = $entityManager->getRepository(User::class)
            ->findOneByIncludingDeleted(['username' => $user->getUsername()]);

        if ($existingUser) {
            throw new \RuntimeException('Este nombre de usuario ya está en uso.');
        }

        if ($user->getEmail()) {
            $existingUserByEmail = $entityManager->getRepository(User::class)
                ->findOneByIncludingDeleted(['email' => $user->getEmail()]);

            if ($existingUserByEmail) {
                throw new \RuntimeException('Este email ya está registrado.');
            }
        }

        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $user->setActivo(false);
        $user->setRoles(['ROLE_USER']);

        foreach ($convocatoriaRepository->findActivas() as $convocatoria) {
            $user->addConvocatoria($convocatoria);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $notificacionService->crearNotificacionRegistroUsuario($user);
    }
}
