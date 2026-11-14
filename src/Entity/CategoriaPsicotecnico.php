<?php

namespace App\Entity;

use App\Repository\CategoriaPsicotecnicoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoriaPsicotecnicoRepository::class)]
#[ORM\Table(name: 'categoria_psicotecnico')]
class CategoriaPsicotecnico
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $orden = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $activo = true;

    #[ORM\ManyToMany(targetEntity: ArchivoPsicotecnico::class, mappedBy: 'categorias')]
    private Collection $archivos;

    public function __construct()
    {
        $this->archivos = new ArrayCollection();
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

    public function getOrden(): int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function getActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    /**
     * @return Collection<int, ArchivoPsicotecnico>
     */
    public function getArchivos(): Collection
    {
        return $this->archivos;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}
