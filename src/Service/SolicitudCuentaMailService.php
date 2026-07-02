<?php

namespace App\Service;

use App\Dto\SolicitudCuentaDto;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class SolicitudCuentaMailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $contactEmail,
        private string $mailerFrom,
    ) {
    }

    public function enviarSolicitud(SolicitudCuentaDto $solicitud): void
    {
        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($this->contactEmail)
            ->replyTo($solicitud->email ?? '')
            ->subject(sprintf('[Solicitud de cuenta] %s', $solicitud->nombre))
            ->htmlTemplate('emails/solicitud_cuenta.html.twig')
            ->context([
                'solicitud' => $solicitud,
                'fecha' => new \DateTime(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('No se pudo enviar la solicitud de cuenta', [
                'email' => $solicitud->email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
