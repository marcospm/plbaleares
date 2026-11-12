<?php

namespace App\Entity;

use App\Repository\ConfiguracionDificultadExamenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfiguracionDificultadExamenRepository::class)]
class ConfiguracionDificultadExamen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $facil = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $moderada = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $dificil = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualizadoEn = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isFacil(): bool
    {
        return $this->facil;
    }

    public function setFacil(bool $facil): static
    {
        $this->facil = $facil;

        return $this;
    }

    public function isModerada(): bool
    {
        return $this->moderada;
    }

    public function setModerada(bool $moderada): static
    {
        $this->moderada = $moderada;

        return $this;
    }

    public function isDificil(): bool
    {
        return $this->dificil;
    }

    public function setDificil(bool $dificil): static
    {
        $this->dificil = $dificil;

        return $this;
    }

    public function getActualizadoEn(): ?\DateTimeInterface
    {
        return $this->actualizadoEn;
    }

    public function marcarActualizado(): static
    {
        $this->actualizadoEn = new \DateTime();

        return $this;
    }
}
