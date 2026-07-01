<?php

namespace App\Service;

use App\Entity\MensajeContacto;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class ContactoMailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $contactEmail,
        private string $mailerFrom,
    ) {
    }

    public function enviarMensajeContacto(MensajeContacto $mensaje): void
    {
        $asunto = $mensaje->getAsunto() ?: 'Consulta desde el formulario de contacto';

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($this->contactEmail)
            ->replyTo($mensaje->getEmail())
            ->subject(sprintf('[Contacto] %s', $asunto))
            ->htmlTemplate('emails/contacto.html.twig')
            ->context(['mensaje' => $mensaje]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('No se pudo enviar el correo de contacto', [
                'mensaje_id' => $mensaje->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
