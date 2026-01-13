<?php

namespace App\Entity;

use App\Repository\SesionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SesionRepository::class)]
class Sesion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\ManyToMany(targetEntity: Tema::class)]
    #[ORM\JoinTable(name: 'sesion_tema')]
    private Collection $temas;

    #[ORM\ManyToMany(targetEntity: TemaMunicipal::class)]
    #[ORM\JoinTable(name: 'sesion_tema_municipal')]
    private Collection $temasMunicipales;

    #[ORM\ManyToOne(targetEntity: Municipio::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Municipio $municipio = null;

    #[ORM\ManyToOne(targetEntity: Convocatoria::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Convocatoria $convocatoria = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $enlaceVideo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creadoPor = null;

    public function __construct()
    {
        $this->fechaCreacion = new \DateTime();
        $this->temas = new ArrayCollection();
        $this->temasMunicipales = new ArrayCollection();
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

    /**
     * @return Collection<int, TemaMunicipal>
     */
    public function getTemasMunicipales(): Collection
    {
        return $this->temasMunicipales;
    }

    public function addTemaMunicipal(TemaMunicipal $temaMunicipal): static
    {
        if (!$this->temasMunicipales->contains($temaMunicipal)) {
            $this->temasMunicipales->add($temaMunicipal);
        }

        return $this;
    }

    public function removeTemaMunicipal(TemaMunicipal $temaMunicipal): static
    {
        $this->temasMunicipales->removeElement($temaMunicipal);

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

    public function getConvocatoria(): ?Convocatoria
    {
        return $this->convocatoria;
    }

    public function setConvocatoria(?Convocatoria $convocatoria): static
    {
        $this->convocatoria = $convocatoria;

        return $this;
    }

    public function getEnlaceVideo(): ?string
    {
        return $this->enlaceVideo;
    }

    public function setEnlaceVideo(string $enlaceVideo): static
    {
        $this->enlaceVideo = $enlaceVideo;

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

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }

    /**
     * Obtiene el tipo de tema relacionado
     * @return string 'general'|'municipal'|null
     */
    public function getTipoTema(): ?string
    {
        if ($this->temas->count() > 0) {
            return 'general';
        }
        if ($this->temasMunicipales->count() > 0) {
            return 'municipal';
        }
        return null;
    }

    /**
     * Obtiene los nombres de los temas relacionados
     */
    public function getNombresTemas(): array
    {
        $nombres = [];
        foreach ($this->temas as $tema) {
            $nombres[] = $tema->getNombre();
        }
        foreach ($this->temasMunicipales as $temaMunicipal) {
            $nombres[] = $temaMunicipal->getNombre();
        }
        return $nombres;
    }
}
