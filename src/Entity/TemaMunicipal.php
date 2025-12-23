<?php

namespace App\Entity;

use App\Repository\TemaMunicipalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TemaMunicipalRepository::class)]
class TemaMunicipal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $rutaPdf = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\ManyToOne(inversedBy: 'temasMunicipales')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Municipio $municipio = null;

    #[ORM\OneToMany(targetEntity: PreguntaMunicipal::class, mappedBy: 'temaMunicipal')]
    private Collection $preguntasMunicipales;

    public function __construct()
    {
        $this->preguntasMunicipales = new ArrayCollection();
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

    public function getRutaPdf(): ?string
    {
        return $this->rutaPdf;
    }

    public function setRutaPdf(?string $rutaPdf): static
    {
        $this->rutaPdf = $rutaPdf;

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

    public function getMunicipio(): ?Municipio
    {
        return $this->municipio;
    }

    public function setMunicipio(?Municipio $municipio): static
    {
        $this->municipio = $municipio;

        return $this;
    }

    /**
     * @return Collection<int, PreguntaMunicipal>
     */
    public function getPreguntasMunicipales(): Collection
    {
        return $this->preguntasMunicipales;
    }

    public function addPreguntasMunicipale(PreguntaMunicipal $preguntasMunicipale): static
    {
        if (!$this->preguntasMunicipales->contains($preguntasMunicipale)) {
            $this->preguntasMunicipales->add($preguntasMunicipale);
            $preguntasMunicipale->setTemaMunicipal($this);
        }

        return $this;
    }

    public function removePreguntasMunicipale(PreguntaMunicipal $preguntasMunicipale): static
    {
        if ($this->preguntasMunicipales->removeElement($preguntasMunicipale)) {
            if ($preguntasMunicipale->getTemaMunicipal() === $this) {
                $preguntasMunicipale->setTemaMunicipal(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}



