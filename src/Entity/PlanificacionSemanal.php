<?php

namespace App\Entity;

use App\Repository\PlanificacionSemanalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanificacionSemanalRepository::class)]
class PlanificacionSemanal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fechaCreacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creadoPor = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activa = true;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaFin = null;

    #[ORM\OneToMany(targetEntity: FranjaHoraria::class, mappedBy: 'planificacion', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $franjasHorarias;

    public function __construct()
    {
        $this->franjasHorarias = new ArrayCollection();
        $this->fechaCreacion = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

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

    public function getCreadoPor(): ?User
    {
        return $this->creadoPor;
    }

    public function setCreadoPor(?User $creadoPor): static
    {
        $this->creadoPor = $creadoPor;

        return $this;
    }

    public function isActiva(): bool
    {
        return $this->activa;
    }

    public function setActiva(bool $activa): static
    {
        $this->activa = $activa;

        return $this;
    }

    public function getFechaFin(): ?\DateTimeInterface
    {
        return $this->fechaFin;
    }

    public function setFechaFin(?\DateTimeInterface $fechaFin): static
    {
        $this->fechaFin = $fechaFin;

        return $this;
    }

    /**
     * @return Collection<int, FranjaHoraria>
     */
    public function getFranjasHorarias(): Collection
    {
        return $this->franjasHorarias;
    }

    /**
     * @param Collection<int, FranjaHoraria>|array $franjasHorarias
     */
    public function setFranjasHorarias(Collection|array $franjasHorarias): static
    {
        if (is_array($franjasHorarias)) {
            $this->franjasHorarias = new ArrayCollection($franjasHorarias);
        } else {
            $this->franjasHorarias = $franjasHorarias;
        }
        
        // Asegurar que todas las franjas tienen esta planificación como referencia
        foreach ($this->franjasHorarias as $franja) {
            if ($franja->getPlanificacion() !== $this) {
                $franja->setPlanificacion($this);
            }
        }
        
        return $this;
    }

    public function addFranjaHoraria(FranjaHoraria $franjaHoraria): static
    {
        if (!$this->franjasHorarias->contains($franjaHoraria)) {
            $this->franjasHorarias->add($franjaHoraria);
            $franjaHoraria->setPlanificacion($this);
        }

        return $this;
    }

    public function removeFranjaHoraria(FranjaHoraria $franjaHoraria): static
    {
        if ($this->franjasHorarias->removeElement($franjaHoraria)) {
            if ($franjaHoraria->getPlanificacion() === $this) {
                $franjaHoraria->setPlanificacion(null);
            }
        }

        return $this;
    }


    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}

