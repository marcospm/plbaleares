<?php

namespace App\Controller;

use App\Form\ForgotPasswordType;
use App\Form\ResetPasswordType;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        PasswordResetService $passwordResetService,
        MailerInterface $mailer,
        LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')] string $mailerFrom,
    ): Response {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $user = $passwordResetService->findUserByEmail($email);

            if ($user) {
                try {
                    $token = $passwordResetService->createOrReuseToken($user);

                    $resetUrl = $request->getSchemeAndHttpHost()
                        . $this->generateUrl('app_reset_password', ['token' => $token]);

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
                } catch (\Throwable $e) {
                    $logger->error('Error al procesar recuperacion de contrasena', [
                        'user_id' => $user->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->addFlash('success', 'Si el correo existe, hemos enviado instrucciones para cambiar la contrasena.');

            return $this->redirectToRoute('app_login', ['password_reset' => 'success']);
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'], requirements: ['token' => '[a-fA-F0-9]{32}'])]
    public function resetPassword(
        string $token,
        Request $request,
        PasswordResetService $passwordResetService,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $token = strtolower(trim($token));
        $user = $passwordResetService->findUserByToken($token);

        if (!$user) {
            $this->addFlash('error', 'El enlace de recuperacion no es valido o ya fue utilizado.');

            return $this->redirectToRoute('app_forgot_password');
        }

        if ($passwordResetService->isExpired($user)) {
            $expiresAt = $user->getResetPasswordExpiresAt();
            $this->addFlash(
                'error',
                sprintf(
                    'El enlace de recuperacion ha caducado (valido hasta %s). Solicita uno nuevo.',
                    $expiresAt?->format('d/m/Y H:i') ?? 'desconocido'
                )
            );

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $entityManager->flush();

            $passwordResetService->clearToken($user);

            $this->addFlash('success', 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
            'token' => $token,
        ]);
    }
}
