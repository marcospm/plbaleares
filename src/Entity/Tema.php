<?php

namespace App\Entity;

use App\Repository\TemaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TemaRepository::class)]
class Tema
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $rutaPdf = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    #[ORM\ManyToMany(targetEntity: Ley::class, inversedBy: 'temas')]
    #[ORM\JoinTable(name: 'tema_ley')]
    private Collection $leyes;

    #[ORM\OneToMany(targetEntity: Pregunta::class, mappedBy: 'tema')]
    private Collection $preguntas;

    public function __construct()
    {
        $this->leyes = new ArrayCollection();
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

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

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

    /**
     * @return Collection<int, Ley>
     */
    public function getLeyes(): Collection
    {
        return $this->leyes;
    }

    public function addLey(Ley $ley): static
    {
        if (!$this->leyes->contains($ley)) {
            $this->leyes->add($ley);
            $ley->addTema($this);
        }

        return $this;
    }

    public function removeLey(Ley $ley): static
    {
        if ($this->leyes->removeElement($ley)) {
            $ley->removeTema($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Pregunta>
     */
    public function getPreguntas(): Collection
    {
        return $this->preguntas;
    }

    public function addPregunta(Pregunta $pregunta): static
    {
        if (!$this->preguntas->contains($pregunta)) {
            $this->preguntas->add($pregunta);
            $pregunta->setTema($this);
        }

        return $this;
    }

    public function removePregunta(Pregunta $pregunta): static
    {
        if ($this->preguntas->removeElement($pregunta)) {
            if ($pregunta->getTema() === $this) {
                $pregunta->setTema(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}

