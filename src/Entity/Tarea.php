<?php

namespace App\Entity;

use App\Repository\TareaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TareaRepository::class)]
class Tarea
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $semanaAsignacion = null; // Lunes de la semana

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fechaCreacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creadoPor = null;

    #[ORM\ManyToOne]
    private ?Tema $tema = null;

    #[ORM\ManyToOne]
    private ?Ley $ley = null;

    #[ORM\ManyToOne]
    private ?Articulo $articulo = null;

    #[ORM\OneToMany(targetEntity: TareaAsignada::class, mappedBy: 'tarea', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $asignaciones;

    public function __construct()
    {
        $this->asignaciones = new ArrayCollection();
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

    public function setDescripcion(string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getSemanaAsignacion(): ?\DateTimeInterface
    {
        return $this->semanaAsignacion;
    }

    public function setSemanaAsignacion(\DateTimeInterface $semanaAsignacion): static
    {
        $this->semanaAsignacion = $semanaAsignacion;

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

    public function getTema(): ?Tema
    {
        return $this->tema;
    }

    public function setTema(?Tema $tema): static
    {
        $this->tema = $tema;

        return $this;
    }

    public function getLey(): ?Ley
    {
        return $this->ley;
    }

    public function setLey(?Ley $ley): static
    {
        $this->ley = $ley;

        return $this;
    }

    public function getArticulo(): ?Articulo
    {
        return $this->articulo;
    }

    public function setArticulo(?Articulo $articulo): static
    {
        $this->articulo = $articulo;

        return $this;
    }

    /**
     * @return Collection<int, TareaAsignada>
     */
    public function getAsignaciones(): Collection
    {
        return $this->asignaciones;
    }

    public function addAsignacion(TareaAsignada $asignacion): static
    {
        if (!$this->asignaciones->contains($asignacion)) {
            $this->asignaciones->add($asignacion);
            $asignacion->setTarea($this);
        }

        return $this;
    }

    public function removeAsignacion(TareaAsignada $asignacion): static
    {
        if ($this->asignaciones->removeElement($asignacion)) {
            if ($asignacion->getTarea() === $this) {
                $asignacion->setTarea(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}

