<?php

namespace App\Entity;

use App\Repository\DocumentoConvocatoriaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentoConvocatoriaRepository::class)]
class DocumentoConvocatoria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 500)]
    private ?string $rutaArchivo = null;

    #[ORM\ManyToOne(targetEntity: Convocatoria::class, inversedBy: 'documentos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Convocatoria $convocatoria = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaSubida = null;

    public function __construct()
    {
        $this->fechaSubida = new \DateTime();
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

    public function getRutaArchivo(): ?string
    {
        return $this->rutaArchivo;
    }

    public function setRutaArchivo(string $rutaArchivo): static
    {
        $this->rutaArchivo = $rutaArchivo;

        return $this;
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

    public function getFechaSubida(): ?\DateTimeInterface
    {
        return $this->fechaSubida;
    }

    public function setFechaSubida(\DateTimeInterface $fechaSubida): static
    {
        $this->fechaSubida = $fechaSubida;

        return $this;
    }
}

