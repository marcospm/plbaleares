<?php

namespace App\Entity;

use App\Repository\GrupoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GrupoRepository::class)]
class Grupo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'grupos')]
    #[ORM\JoinTable(name: 'grupo_user')]
    private Collection $alumnos;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaActualizacion = null;

    public function __construct()
    {
        $this->alumnos = new ArrayCollection();
        $this->fechaCreacion = new \DateTime();
        $this->fechaActualizacion = new \DateTime();
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
        $this->fechaActualizacion = new \DateTime();
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAlumnos(): Collection
    {
        return $this->alumnos;
    }

    public function addAlumno(User $alumno): static
    {
        if (!$this->alumnos->contains($alumno)) {
            $this->alumnos->add($alumno);
            $this->fechaActualizacion = new \DateTime();
        }

        return $this;
    }

    public function removeAlumno(User $alumno): static
    {
        $this->alumnos->removeElement($alumno);
        $this->fechaActualizacion = new \DateTime();
        return $this;
    }

    public function getFechaCreacion(): ?\DateTimeInterface
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(\DateTimeInterface $fechaCreacion): static
    {
        $this->fechaCreacion = $fechaCreacion;
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

