<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/prueba-email')]
#[IsGranted('ROLE_ADMIN')]
class EmailPruebaController extends AbstractController
{
    #[Route('', name: 'app_email_prueba', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MailerInterface $mailer,
        LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')] string $mailerFrom,
        #[Autowire('%env(MAILER_DSN)%')] string $mailerDsn,
    ): Response {
        $emailDestino = '';
        $mailerHostInfo = $this->describeMailerDsn($mailerDsn);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('email_prueba', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de seguridad inválido.');

                return $this->redirectToRoute('app_email_prueba');
            }

            $emailDestino = trim((string) $request->request->get('email', ''));

            if ($emailDestino === '' || !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Introduce un correo electrónico válido.');

                return $this->render('email_prueba/index.html.twig', [
                    'email' => $emailDestino,
                    'mailerFrom' => $mailerFrom,
                    'mailerHostInfo' => $mailerHostInfo,
                ]);
            }

            try {
                $enviadoEn = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
                    ->format('d/m/Y H:i:s');

                $mensaje = (new Email())
                    ->from($mailerFrom)
                    ->to($emailDestino)
                    ->subject('Prueba de correo - BISPOL Aula Virtual')
                    ->text(sprintf(
                        "Este es un correo de prueba enviado desde la administración.\n\nDestinatario: %s\nRemitente: %s\nFecha: %s\n\nSi has recibido este mensaje, la configuración del mailer funciona correctamente.",
                        $emailDestino,
                        $mailerFrom,
                        $enviadoEn
                    ))
                    ->html(sprintf(
                        '<p>Este es un correo de <strong>prueba</strong> enviado desde la administración.</p>
                        <ul>
                            <li><strong>Destinatario:</strong> %s</li>
                            <li><strong>Remitente:</strong> %s</li>
                            <li><strong>Fecha:</strong> %s</li>
                        </ul>
                        <p>Si has recibido este mensaje, la configuración del mailer funciona correctamente.</p>',
                        htmlspecialchars($emailDestino, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        htmlspecialchars($mailerFrom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        htmlspecialchars($enviadoEn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    ));

                $mailer->send($mensaje);

                $this->addFlash(
                    'success',
                    sprintf('Correo de prueba enviado correctamente a %s (desde %s).', $emailDestino, $mailerFrom)
                );

                return $this->redirectToRoute('app_email_prueba');
            } catch (\Throwable $e) {
                $logger->error('Error al enviar correo de prueba', [
                    'to' => $emailDestino,
                    'from' => $mailerFrom,
                    'mailer' => $mailerHostInfo,
                    'error' => $e->getMessage(),
                ]);

                $this->addFlash(
                    'error',
                    'No se pudo enviar el correo: ' . $e->getMessage()
                );
            }
        }

        return $this->render('email_prueba/index.html.twig', [
            'email' => $emailDestino,
            'mailerFrom' => $mailerFrom,
            'mailerHostInfo' => $mailerHostInfo,
        ]);
    }

    /**
     * Describe el DSN sin exponer credenciales (útil para depurar en admin).
     */
    private function describeMailerDsn(string $dsn): string
    {
        $parts = parse_url($dsn);
        if ($parts === false) {
            return '(DSN no válido)';
        }

        $scheme = $parts['scheme'] ?? '?';
        $host = $parts['host'] ?? '?';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return sprintf('%s://%s%s', $scheme, $host, $port);
    }
}
