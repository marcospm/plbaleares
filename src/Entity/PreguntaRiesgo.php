<?php

namespace App\Entity;

use App\Repository\PreguntaRiesgoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreguntaRiesgoRepository::class)]
#[ORM\Index(columns: ['usuario_id', 'pregunta_id'], name: 'idx_pregunta_riesgo_usuario_pregunta')]
#[ORM\Index(columns: ['usuario_id', 'pregunta_municipal_id'], name: 'idx_pregunta_riesgo_usuario_pregunta_municipal')]
#[ORM\Index(columns: ['usuario_id', 'acertada'], name: 'idx_pregunta_riesgo_usuario_acertada')]
class PreguntaRiesgo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuario = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Pregunta $pregunta = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?PreguntaMunicipal $preguntaMunicipal = null;

    #[ORM\Column(type: 'boolean')]
    private bool $acertada = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaActualizacion = null;

    public function __construct()
    {
        $this->fechaActualizacion = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): ?User
    {
        return $this->usuario;
    }

    public function setUsuario(?User $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getPregunta(): ?Pregunta
    {
        return $this->pregunta;
    }

    public function setPregunta(?Pregunta $pregunta): static
    {
        $this->pregunta = $pregunta;

        return $this;
    }

    public function getPreguntaMunicipal(): ?PreguntaMunicipal
    {
        return $this->preguntaMunicipal;
    }

    public function setPreguntaMunicipal(?PreguntaMunicipal $preguntaMunicipal): static
    {
        $this->preguntaMunicipal = $preguntaMunicipal;

        return $this;
    }

    public function isAcertada(): bool
    {
        return $this->acertada;
    }

    public function setAcertada(bool $acertada): static
    {
        $this->acertada = $acertada;

        return $this;
    }

    public function getFechaActualizacion(): ?\DateTimeInterface
    {
        return $this->fechaActualizacion;
    }

    public function setFechaActualizacion(\DateTimeInterface $fechaActualizacion): static
    {
        $this->fechaActualizacion = $fechaActualizacion;

        return $this;
    }
}
