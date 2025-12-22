<?php

namespace App\Entity;

use App\Repository\TareaAsignadaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TareaAsignadaRepository::class)]
class TareaAsignada
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'asignaciones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tarea $tarea = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuario = null;

    #[ORM\ManyToOne(inversedBy: 'tareasAsignadas')]
    private ?FranjaHorariaPersonalizada $franjaHoraria = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $completada = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaCompletada = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fechaAsignacion = null;

    public function __construct()
    {
        $this->fechaAsignacion = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTarea(): ?Tarea
    {
        return $this->tarea;
    }

    public function setTarea(?Tarea $tarea): static
    {
        $this->tarea = $tarea;

        return $this;
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

    public function getFranjaHoraria(): ?FranjaHorariaPersonalizada
    {
        return $this->franjaHoraria;
    }

    public function setFranjaHoraria(?FranjaHorariaPersonalizada $franjaHoraria): static
    {
        $this->franjaHoraria = $franjaHoraria;

        return $this;
    }

    public function isCompletada(): bool
    {
        return $this->completada;
    }

    public function setCompletada(bool $completada): static
    {
        $this->completada = $completada;
        if ($completada && !$this->fechaCompletada) {
            $this->fechaCompletada = new \DateTime();
        } elseif (!$completada) {
            $this->fechaCompletada = null;
        }

        return $this;
    }

    public function getFechaCompletada(): ?\DateTimeInterface
    {
        return $this->fechaCompletada;
    }

    public function setFechaCompletada(?\DateTimeInterface $fechaCompletada): static
    {
        $this->fechaCompletada = $fechaCompletada;

        return $this;
    }

    public function getFechaAsignacion(): ?\DateTimeImmutable
    {
        return $this->fechaAsignacion;
    }

    public function setFechaAsignacion(\DateTimeImmutable $fechaAsignacion): static
    {
        $this->fechaAsignacion = $fechaAsignacion;

        return $this;
    }
}

