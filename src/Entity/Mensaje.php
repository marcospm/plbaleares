<?php

namespace App\Entity;

use App\Repository\MensajeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MensajeRepository::class)]
#[ORM\Index(columns: ['remitente_id'], name: 'idx_mensaje_remitente')]
#[ORM\Index(columns: ['destinatario_id'], name: 'idx_mensaje_destinatario')]
#[ORM\Index(columns: ['leido'], name: 'idx_mensaje_leido')]
#[ORM\Index(columns: ['fecha_envio'], name: 'idx_mensaje_fecha_envio')]
class Mensaje
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $remitente = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $destinatario = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenido = null;

    /**
     * Contenido desencriptado en memoria (no se persiste)
     * Se establece automáticamente por MensajeEncriptacionSubscriber
     */
    private ?string $contenidoDesencriptado = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaEnvio = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $leido = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaLectura = null;

    public function __construct()
    {
        $this->fechaEnvio = new \DateTime();
        $this->leido = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRemitente(): ?User
    {
        return $this->remitente;
    }

    public function setRemitente(?User $remitente): static
    {
        $this->remitente = $remitente;

        return $this;
    }

    public function getDestinatario(): ?User
    {
        return $this->destinatario;
    }

    public function setDestinatario(?User $destinatario): static
    {
        $this->destinatario = $destinatario;

        return $this;
    }

    public function getContenido(): ?string
    {
        // Si tenemos el contenido desencriptado en memoria, devolverlo
        // (establecido por MensajeEncriptacionSubscriber en postLoad)
        if ($this->contenidoDesencriptado !== null) {
            return $this->contenidoDesencriptado;
        }

        // Si no, devolver el contenido tal cual (será desencriptado por el subscriber)
        return $this->contenido;
    }

    public function setContenido(string $contenido): static
    {
        // Guardar el contenido sin encriptar en memoria
        // Se encriptará automáticamente antes de persistir por MensajeEncriptacionSubscriber
        $this->contenidoDesencriptado = $contenido;
        $this->contenido = $contenido; // Temporal, se sobrescribirá al encriptar

        return $this;
    }

    public function getFechaEnvio(): ?\DateTimeInterface
    {
        return $this->fechaEnvio;
    }

    public function setFechaEnvio(\DateTimeInterface $fechaEnvio): static
    {
        $this->fechaEnvio = $fechaEnvio;

        return $this;
    }

    public function isLeido(): bool
    {
        return $this->leido;
    }

    public function setLeido(bool $leido): static
    {
        $this->leido = $leido;
        if ($leido && $this->fechaLectura === null) {
            $this->fechaLectura = new \DateTime();
        }

        return $this;
    }

    public function getFechaLectura(): ?\DateTimeInterface
    {
        return $this->fechaLectura;
    }

    public function setFechaLectura(?\DateTimeInterface $fechaLectura): static
    {
        $this->fechaLectura = $fechaLectura;

        return $this;
    }
}
