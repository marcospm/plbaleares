<?php

namespace App\Entity;

use App\Repository\ArchivoPsicotecnicoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArchivoPsicotecnicoRepository::class)]
#[ORM\Table(name: 'archivo_psicotecnico')]
#[ORM\Index(name: 'idx_archivo_psicotecnico_lote', columns: ['lote'])]
class ArchivoPsicotecnico
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 500)]
    private ?string $rutaArchivo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombreArchivoOriginal = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $lote = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $creadoPor = null;

    #[ORM\ManyToMany(targetEntity: CategoriaPsicotecnico::class, inversedBy: 'archivos')]
    #[ORM\JoinTable(name: 'archivo_psicotecnico_categoria')]
    #[ORM\OrderBy(['orden' => 'ASC', 'nombre' => 'ASC'])]
    #[Assert\Count(min: 1, minMessage: 'Elige al menos una categoría.')]
    private Collection $categorias;

    public function __construct()
    {
        $this->categorias = new ArrayCollection();
        $this->fechaCreacion = new \DateTime();
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

    public function getRutaArchivo(): ?string
    {
        return $this->rutaArchivo;
    }

    public function setRutaArchivo(?string $rutaArchivo): static
    {
        $this->rutaArchivo = $rutaArchivo;

        return $this;
    }

    public function getNombreArchivoOriginal(): ?string
    {
        return $this->nombreArchivoOriginal;
    }

    public function setNombreArchivoOriginal(?string $nombreArchivoOriginal): static
    {
        $this->nombreArchivoOriginal = $nombreArchivoOriginal;

        return $this;
    }

    public function tieneArchivo(): bool
    {
        return $this->rutaArchivo !== null && $this->rutaArchivo !== '';
    }

    public function getLote(): ?string
    {
        return $this->lote;
    }

    public function setLote(?string $lote): static
    {
        $this->lote = $lote;

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

    public function getCreadoPor(): ?User
    {
        return $this->creadoPor;
    }

    public function setCreadoPor(?User $creadoPor): static
    {
        $this->creadoPor = $creadoPor;

        return $this;
    }

    /**
     * @return Collection<int, CategoriaPsicotecnico>
     */
    public function getCategorias(): Collection
    {
        return $this->categorias;
    }

    public function addCategoria(CategoriaPsicotecnico $categoria): static
    {
        if (!$this->categorias->contains($categoria)) {
            $this->categorias->add($categoria);
        }

        return $this;
    }

    public function removeCategoria(CategoriaPsicotecnico $categoria): static
    {
        $this->categorias->removeElement($categoria);

        return $this;
    }
}
