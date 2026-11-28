<?php

namespace App\Entity;

use App\Repository\AperturaArchivoPsicotecnicoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AperturaArchivoPsicotecnicoRepository::class)]
#[ORM\Table(name: 'apertura_archivo_psicotecnico')]
#[ORM\Index(name: 'idx_apertura_psico_fecha', columns: ['fecha'])]
class AperturaArchivoPsicotecnico
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ArchivoPsicotecnico $archivo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $usuario = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fecha = null;

    public function __construct()
    {
        $this->fecha = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArchivo(): ?ArchivoPsicotecnico
    {
        return $this->archivo;
    }

    public function setArchivo(ArchivoPsicotecnico $archivo): static
    {
        $this->archivo = $archivo;

        return $this;
    }

    public function getUsuario(): ?User
    {
        return $this->usuario;
    }

    public function setUsuario(User $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeInterface $fecha): static
    {
        $this->fecha = $fecha;

        return $this;
    }
}
