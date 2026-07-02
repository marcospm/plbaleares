<?php

namespace App\Controller;

use App\Form\ForgotPasswordType;
use App\Form\ResetPasswordType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')] string $mailerFrom,
    ): Response {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user && !$user->isEliminado()) {
                $token = bin2hex(random_bytes(32));
                $user->setResetPasswordToken($token);
                $user->setResetPasswordExpiresAt((new \DateTime('+1 hour')));
                $entityManager->flush();

                $resetUrl = $this->generateUrl(
                    'app_reset_password',
                    ['token' => $token],
                    \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
                );

                $emailMessage = (new TemplatedEmail())
                    ->from($mailerFrom)
                    ->to($user->getEmail() ?? '')
                    ->subject('Recuperar contrasena')
                    ->htmlTemplate('emails/reset_password.html.twig')
                    ->context([
                        'usuario' => $user,
                        'resetUrl' => $resetUrl,
                    ]);

                $mailer->send($emailMessage);
            }

            $this->addFlash('success', 'Si el correo existe, hemos enviado instrucciones para cambiar la contrasena.');

            return $this->redirectToRoute('app_login', ['password_reset' => 'success']);
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $userRepository->findOneBy(['resetPasswordToken' => $token]);

        if (
            !$user
            || !$user->getResetPasswordExpiresAt()
            || $user->getResetPasswordExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('error', 'El enlace de recuperacion no es valido o ha caducado.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setResetPasswordToken(null);
            $user->setResetPasswordExpiresAt(null);

            $entityManager->flush();

            $this->addFlash('success', 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}
