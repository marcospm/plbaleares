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
    private const RESET_TOKEN_TTL = '+1 hour';

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
            // Evitar caché de usuarios: necesitamos una entidad managed fresca para persistir el token
            $user = $userRepository->findActiveByEmail($email);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expiresAt = new \DateTimeImmutable(self::RESET_TOKEN_TTL);
                // Doctrine datetime solo acepta DateTime mutable al persistir
                $user->setResetPasswordToken($token);
                $user->setResetPasswordExpiresAt(\DateTime::createFromImmutable($expiresAt));
                $entityManager->flush();

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
            }

            $this->addFlash('success', 'Si el correo existe, hemos enviado instrucciones para cambiar la contrasena.');

            return $this->redirectToRoute('app_login', ['password_reset' => 'success']);
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'], requirements: ['token' => '[a-fA-F0-9]+'])]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $token = strtolower(trim($token));
        $user = (strlen($token) === 64)
            ? $userRepository->findActiveByResetPasswordToken($token)
            : null;
        $expiresAt = $user?->getResetPasswordExpiresAt();

        // Comparar por timestamp Unix: evita falsos "caducado" por DateTime vs DateTimeImmutable / TZ
        if (!$user || !$expiresAt || $expiresAt->getTimestamp() < time()) {
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
