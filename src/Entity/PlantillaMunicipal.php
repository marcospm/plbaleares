<?php

namespace App\Entity;

use App\Repository\PlantillaMunicipalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlantillaMunicipalRepository::class)]
class PlantillaMunicipal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\ManyToOne(inversedBy: 'plantillas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TemaMunicipal $temaMunicipal = null;

    #[ORM\Column(length: 20)]
    private ?string $dificultad = null;

    #[ORM\OneToMany(targetEntity: PreguntaMunicipal::class, mappedBy: 'plantilla')]
    private Collection $preguntas;

    public function __construct()
    {
        $this->preguntas = new ArrayCollection();
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

    public function getTemaMunicipal(): ?TemaMunicipal
    {
        return $this->temaMunicipal;
    }

    public function setTemaMunicipal(?TemaMunicipal $temaMunicipal): static
    {
        $this->temaMunicipal = $temaMunicipal;

        return $this;
    }

    public function getDificultad(): ?string
    {
        return $this->dificultad;
    }

    public function setDificultad(string $dificultad): static
    {
        $this->dificultad = $dificultad;

        return $this;
    }

    /**
     * @return Collection<int, PreguntaMunicipal>
     */
    public function getPreguntas(): Collection
    {
        return $this->preguntas;
    }

    public function addPregunta(PreguntaMunicipal $pregunta): static
    {
        if (!$this->preguntas->contains($pregunta)) {
            $this->preguntas->add($pregunta);
            $pregunta->setPlantilla($this);
        }

        return $this;
    }

    public function removePregunta(PreguntaMunicipal $pregunta): static
    {
        if ($this->preguntas->removeElement($pregunta)) {
            if ($pregunta->getPlantilla() === $this) {
                $pregunta->setPlantilla(null);
            }
        }

        return $this;
    }

    public function getNumeroPreguntas(): int
    {
        return $this->preguntas->filter(function($p) {
            return $p->isActivo();
        })->count();
    }

    public function getDificultadLabel(): string
    {
        return match($this->dificultad) {
            'facil' => 'Fácil',
            'moderada' => 'Moderada',
            'dificil' => 'Difícil',
            default => $this->dificultad ?? '',
        };
    }
}
