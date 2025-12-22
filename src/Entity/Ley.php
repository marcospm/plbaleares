<?php

namespace App\Entity;

use App\Repository\LeyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeyRepository::class)]
class Ley
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    #[ORM\ManyToMany(targetEntity: Tema::class, mappedBy: 'leyes')]
    private Collection $temas;

    #[ORM\OneToMany(targetEntity: Articulo::class, mappedBy: 'ley', orphanRemoval: true)]
    private Collection $articulos;

    #[ORM\OneToMany(targetEntity: Pregunta::class, mappedBy: 'ley')]
    private Collection $preguntas;

    public function __construct()
    {
        $this->temas = new ArrayCollection();
        $this->articulos = new ArrayCollection();
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
            $tema->addLey($this);
        }

        return $this;
    }

    public function removeTema(Tema $tema): static
    {
        if ($this->temas->removeElement($tema)) {
            $tema->removeLey($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Articulo>
     */
    public function getArticulos(): Collection
    {
        return $this->articulos;
    }

    public function addArticulo(Articulo $articulo): static
    {
        if (!$this->articulos->contains($articulo)) {
            $this->articulos->add($articulo);
            $articulo->setLey($this);
        }

        return $this;
    }

    public function removeArticulo(Articulo $articulo): static
    {
        if ($this->articulos->removeElement($articulo)) {
            if ($articulo->getLey() === $this) {
                $articulo->setLey(null);
            }
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
            $pregunta->setLey($this);
        }

        return $this;
    }

    public function removePregunta(Pregunta $pregunta): static
    {
        if ($this->preguntas->removeElement($pregunta)) {
            if ($pregunta->getLey() === $this) {
                $pregunta->setLey(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}

