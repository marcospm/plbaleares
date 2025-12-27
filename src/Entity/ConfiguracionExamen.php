<?php

namespace App\Entity;

use App\Repository\ConfiguracionExamenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfiguracionExamenRepository::class)]
class ConfiguracionExamen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tema $tema = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $porcentaje = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTema(): ?Tema
    {
        return $this->tema;
    }

    public function setTema(?Tema $tema): static
    {
        $this->tema = $tema;

        return $this;
    }

    public function getPorcentaje(): ?string
    {
        return $this->porcentaje;
    }

    public function setPorcentaje(?string $porcentaje): static
    {
        $this->porcentaje = $porcentaje;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }
}

