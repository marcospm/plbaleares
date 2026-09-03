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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $fechaInicio = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $fechaFin = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creadoPor = null;

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

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
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

    public function getFechaInicio(): ?\DateTimeInterface
    {
        return $this->fechaInicio;
    }

    public function setFechaInicio(?\DateTimeInterface $fechaInicio): static
    {
        $this->fechaInicio = $fechaInicio;

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

    public function getCreadoPor(): ?User
    {
        return $this->creadoPor;
    }

    public function setCreadoPor(?User $creadoPor): static
    {
        $this->creadoPor = $creadoPor;

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

    /**
     * @param Collection<int, FranjaHorariaPersonalizada>|array $franjasHorarias
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

