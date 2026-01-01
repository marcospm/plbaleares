<?php

namespace App\Entity;

use App\Repository\ExamenPDFRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExamenPDFRepository::class)]
class ExamenPDF
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

    #[ORM\ManyToMany(targetEntity: Tema::class)]
    #[ORM\JoinTable(name: 'examen_pdf_tema')]
    private Collection $temas;

    public function __construct()
    {
        $this->fechaSubida = new \DateTime();
        $this->temas = new ArrayCollection();
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

    /**
     * @return Collection<int, Tema>
     */
    public function getTemas(): Collection
    {
        return $this->temas;
    }

    public function addTema(Tema $tema): static
    {
        if (!$this->temas->contains($tema)) {
            $this->temas->add($tema);
        }

        return $this;
    }

    public function removeTema(Tema $tema): static
    {
        $this->temas->removeElement($tema);

        return $this;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}
