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
    /** Token corto (32 hex) para que clientes de correo no truncen la URL */
    private const TOKEN_BYTES = 16;

    private const RESET_TOKEN_TTL = '+2 hours';

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
            $user = $userRepository->findActiveByEmail($email);

            if ($user) {
                $token = bin2hex(random_bytes(self::TOKEN_BYTES));
                $expiresAt = new \DateTimeImmutable(self::RESET_TOKEN_TTL);

                $user->setResetPasswordToken($token);
                $user->setResetPasswordExpiresAt(\DateTime::createFromImmutable($expiresAt));
                $entityManager->flush();

                // /reset-password?token=... — más resistente a truncado en emails
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

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    #[Route('/reset-password/{token}', name: 'app_reset_password_path', methods: ['GET', 'POST'], requirements: ['token' => '[a-fA-F0-9]+'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ?string $token = null,
    ): Response {
        $token = strtolower(trim((string) (
            $token
            ?: $request->query->get('token')
            ?: $request->request->get('token')
            ?: ''
        )));

        if ($token === '' || !preg_match('/^[a-f0-9]{32}$|^[a-f0-9]{64}$/', $token)) {
            $this->addFlash('error', 'El enlace de recuperacion no es valido.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $userRepository->findActiveByResetPasswordToken($token);

        if (!$user) {
            $this->addFlash('error', 'El enlace de recuperacion no es valido o ya fue utilizado.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $expiresAt = $user->getResetPasswordExpiresAt();
        if (!$expiresAt || $expiresAt->getTimestamp() < time()) {
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
            $user->setResetPasswordToken(null);
            $user->setResetPasswordExpiresAt(null);

            $entityManager->flush();

            $this->addFlash('success', 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
            'token' => $token,
        ]);
    }
}
