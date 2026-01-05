<?php

namespace App\Entity;

use App\Repository\FranjaHorariaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FranjaHorariaRepository::class)]
class FranjaHoraria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'franjasHorarias')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanificacionSemanal $planificacion = null;

    #[ORM\Column]
    private ?int $diaSemana = null; // 1=Lunes, 7=Domingo

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $horaInicio = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $horaFin = null;

    #[ORM\Column(length: 50)]
    private ?string $tipoActividad = null; // 'repaso_basico', 'estudio_tareas' o 'realizar_examenes'

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcionRepaso = null;

    #[ORM\Column]
    private ?int $orden = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanificacion(): ?PlanificacionSemanal
    {
        return $this->planificacion;
    }

    public function setPlanificacion(?PlanificacionSemanal $planificacion): static
    {
        $this->planificacion = $planificacion;

        return $this;
    }

    public function getDiaSemana(): ?int
    {
        return $this->diaSemana;
    }

    public function setDiaSemana(int $diaSemana): static
    {
        $this->diaSemana = $diaSemana;

        return $this;
    }

    public function getHoraInicio(): ?\DateTimeInterface
    {
        return $this->horaInicio;
    }

    public function setHoraInicio(\DateTimeInterface $horaInicio): static
    {
        $this->horaInicio = $horaInicio;

        return $this;
    }

    public function getHoraFin(): ?\DateTimeInterface
    {
        return $this->horaFin;
    }

    public function setHoraFin(\DateTimeInterface $horaFin): static
    {
        $this->horaFin = $horaFin;

        return $this;
    }

    public function getTipoActividad(): ?string
    {
        return $this->tipoActividad;
    }

    public function setTipoActividad(string $tipoActividad): static
    {
        $this->tipoActividad = $tipoActividad;

        return $this;
    }

    public function getDescripcionRepaso(): ?string
    {
        return $this->descripcionRepaso;
    }

    public function setDescripcionRepaso(?string $descripcionRepaso): static
    {
        $this->descripcionRepaso = $descripcionRepaso;

        return $this;
    }

    public function getOrden(): ?int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;

        return $this;
    }

    public function getNombreDia(): string
    {
        $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        return $dias[$this->diaSemana] ?? '';
    }
}

