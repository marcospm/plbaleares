<?php

namespace App\Entity;

use App\Repository\ConvocatoriaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConvocatoriaRepository::class)]
class Convocatoria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaTeorico = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaFisicas = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaPsicotecnico = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'convocatorias')]
    #[ORM\JoinTable(name: 'convocatoria_user')]
    private Collection $usuarios;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Municipio $municipio = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaActualizacion = null;

    public function __construct()
    {
        $this->usuarios = new ArrayCollection();
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

        return $this;
    }

    public function getFechaTeorico(): ?\DateTimeInterface
    {
        return $this->fechaTeorico;
    }

    public function setFechaTeorico(?\DateTimeInterface $fechaTeorico): static
    {
        $this->fechaTeorico = $fechaTeorico;

        return $this;
    }

    public function getFechaFisicas(): ?\DateTimeInterface
    {
        return $this->fechaFisicas;
    }

    public function setFechaFisicas(?\DateTimeInterface $fechaFisicas): static
    {
        $this->fechaFisicas = $fechaFisicas;

        return $this;
    }

    public function getFechaPsicotecnico(): ?\DateTimeInterface
    {
        return $this->fechaPsicotecnico;
    }

    public function setFechaPsicotecnico(?\DateTimeInterface $fechaPsicotecnico): static
    {
        $this->fechaPsicotecnico = $fechaPsicotecnico;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsuarios(): Collection
    {
        return $this->usuarios;
    }

    public function addUsuario(User $usuario): static
    {
        if (!$this->usuarios->contains($usuario)) {
            $this->usuarios->add($usuario);
        }

        return $this;
    }

    public function removeUsuario(User $usuario): static
    {
        $this->usuarios->removeElement($usuario);

        return $this;
    }

    public function getMunicipio(): ?Municipio
    {
        return $this->municipio;
    }

    public function setMunicipio(?Municipio $municipio): static
    {
        $this->municipio = $municipio;

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









