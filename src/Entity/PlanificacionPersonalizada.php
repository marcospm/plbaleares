<?php

namespace App\Entity;

use App\Repository\PlanificacionPersonalizadaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanificacionPersonalizadaRepository::class)]
class PlanificacionPersonalizada
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuario = null;

    #[ORM\ManyToOne(inversedBy: 'planificacionesPersonalizadas')]
    private ?PlanificacionSemanal $planificacionBase = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fechaCreacion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fechaModificacion = null;

    #[ORM\OneToMany(targetEntity: FranjaHorariaPersonalizada::class, mappedBy: 'planificacion', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $franjasHorarias;

    public function __construct()
    {
        $this->franjasHorarias = new ArrayCollection();
        $this->fechaCreacion = new \DateTimeImmutable();
        $this->fechaModificacion = new \DateTimeImmutable();
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

    public function getPlanificacionBase(): ?PlanificacionSemanal
    {
        return $this->planificacionBase;
    }

    public function setPlanificacionBase(?PlanificacionSemanal $planificacionBase): static
    {
        $this->planificacionBase = $planificacionBase;

        return $this;
    }

    public function getFechaCreacion(): ?\DateTimeImmutable
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(\DateTimeImmutable $fechaCreacion): static
    {
        $this->fechaCreacion = $fechaCreacion;

        return $this;
    }

    public function getFechaModificacion(): ?\DateTimeImmutable
    {
        return $this->fechaModificacion;
    }

    public function setFechaModificacion(\DateTimeImmutable $fechaModificacion): static
    {
        $this->fechaModificacion = $fechaModificacion;

        return $this;
    }

    /**
     * @return Collection<int, FranjaHorariaPersonalizada>
     */
    public function getFranjasHorarias(): Collection
    {
        return $this->franjasHorarias;
    }

    public function addFranjaHoraria(FranjaHorariaPersonalizada $franjaHoraria): static
    {
        if (!$this->franjasHorarias->contains($franjaHoraria)) {
            $this->franjasHorarias->add($franjaHoraria);
            $franjaHoraria->setPlanificacion($this);
        }

        return $this;
    }

    public function removeFranjaHoraria(FranjaHorariaPersonalizada $franjaHoraria): static
    {
        if ($this->franjasHorarias->removeElement($franjaHoraria)) {
            if ($franjaHoraria->getPlanificacion() === $this) {
                $franjaHoraria->setPlanificacion(null);
            }
        }

        return $this;
    }
}

