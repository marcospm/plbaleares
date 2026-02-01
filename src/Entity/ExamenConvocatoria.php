<?php

namespace App\Entity;

use App\Repository\ExamenConvocatoriaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExamenConvocatoriaRepository::class)]
class ExamenConvocatoria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 500)]
    private ?string $rutaArchivo = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $rutaArchivoRespuestas = null;

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

    public function setRutaArchivo(string $rutaArchivo): static
    {
        $this->rutaArchivo = $rutaArchivo;

        return $this;
    }

    public function getRutaArchivoRespuestas(): ?string
    {
        return $this->rutaArchivoRespuestas;
    }

    public function setRutaArchivoRespuestas(?string $rutaArchivoRespuestas): static
    {
        $this->rutaArchivoRespuestas = $rutaArchivoRespuestas;

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

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}
