<?php

namespace App\Entity;

use App\Repository\ConvocatoriaBaseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConvocatoriaBaseRepository::class)]
#[ORM\Table(name: 'convocatoria_base')]
class ConvocatoriaBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bases')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Convocatoria $convocatoria = null;

    #[ORM\Column(length: 255)]
    private ?string $nombreOriginal = null;

    #[ORM\Column(length: 500)]
    private ?string $rutaArchivo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    public function __construct()
    {
        $this->fechaCreacion = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConvocatoria(): ?Convocatoria
    {
        return $this->convocatoria;
    }

    public function setConvocatoria(?Convocatoria $convocatoria): static
    {
        $this->convocatoria = $convocatoria;

        return $this;
    }

    public function getNombreOriginal(): ?string
    {
        return $this->nombreOriginal;
    }

    public function setNombreOriginal(string $nombreOriginal): static
    {
        $this->nombreOriginal = $nombreOriginal;

        return $this;
    }

    public function getRutaArchivo(): ?string
    {
        return $this->rutaArchivo;
    }

    public function setRutaArchivo(string $rutaArchivo): static
    {
        $this->rutaArchivo = $rutaArchivo;

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
}
