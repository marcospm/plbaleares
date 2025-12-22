<?php

namespace App\Entity;

use App\Repository\FechasPruebasRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FechasPruebasRepository::class)]
#[ORM\Table(name: 'fechas_pruebas')]
class FechasPruebas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaTeorico = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaFisicas = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaPsicotecnico = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaActualizacion = null;

    public function __construct()
    {
        $this->fechaCreacion = new \DateTime();
        $this->fechaActualizacion = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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
