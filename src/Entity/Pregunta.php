<?php

namespace App\Entity;

use App\Repository\PreguntaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreguntaRepository::class)]
#[ORM\Index(columns: ['tema_id', 'activo'], name: 'idx_pregunta_tema_activo')]
#[ORM\Index(columns: ['dificultad', 'activo'], name: 'idx_pregunta_dificultad_activo')]
#[ORM\Index(columns: ['activo'], name: 'idx_pregunta_activo')]
class Pregunta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $texto = null;

    #[ORM\Column(length: 20)]
    private ?string $dificultad = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $retroalimentacion = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $opcionA = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $opcionB = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $opcionC = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $opcionD = null;

    #[ORM\Column(length: 1)]
    private ?string $respuestaCorrecta = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    #[ORM\ManyToOne(inversedBy: 'preguntas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tema $tema = null;

    #[ORM\ManyToOne(inversedBy: 'preguntas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ley $ley = null;

    #[ORM\ManyToOne(inversedBy: 'preguntas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Articulo $articulo = null;

    #[ORM\OneToMany(targetEntity: \App\Entity\MensajePregunta::class, mappedBy: 'pregunta', orphanRemoval: true)]
    private Collection $mensajes;

    #[ORM\ManyToOne(inversedBy: 'preguntas')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Plantilla $plantilla = null;

    public function __construct()
    {
        $this->mensajes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTexto(): ?string
    {
        return $this->texto;
    }

    public function setTexto(string $texto): static
    {
        $this->texto = $texto;

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

    public function getRetroalimentacion(): ?string
    {
        return $this->retroalimentacion;
    }

    public function setRetroalimentacion(?string $retroalimentacion): static
    {
        $this->retroalimentacion = $retroalimentacion;

        return $this;
    }

    public function getTema(): ?Tema
    {
        return $this->tema;
    }

    public function setTema(?Tema $tema): static
    {
        $this->tema = $tema;

        return $this;
    }

    public function getLey(): ?Ley
    {
        return $this->ley;
    }

    public function setLey(?Ley $ley): static
    {
        $this->ley = $ley;

        return $this;
    }

    public function getArticulo(): ?Articulo
    {
        return $this->articulo;
    }

    public function setArticulo(?Articulo $articulo): static
    {
        $this->articulo = $articulo;

        return $this;
    }

    public function getOpcionA(): ?string
    {
        return $this->opcionA;
    }

    public function setOpcionA(string $opcionA): static
    {
        $this->opcionA = $opcionA;

        return $this;
    }

    public function getOpcionB(): ?string
    {
        return $this->opcionB;
    }

    public function setOpcionB(string $opcionB): static
    {
        $this->opcionB = $opcionB;

        return $this;
    }

    public function getOpcionC(): ?string
    {
        return $this->opcionC;
    }

    public function setOpcionC(string $opcionC): static
    {
        $this->opcionC = $opcionC;

        return $this;
    }

    public function getOpcionD(): ?string
    {
        return $this->opcionD;
    }

    public function setOpcionD(string $opcionD): static
    {
        $this->opcionD = $opcionD;

        return $this;
    }

    public function getRespuestaCorrecta(): ?string
    {
        return $this->respuestaCorrecta;
    }

    public function setRespuestaCorrecta(string $respuestaCorrecta): static
    {
        $this->respuestaCorrecta = $respuestaCorrecta;

        return $this;
    }

    public function getDificultadLabel(): string
    {
        return match($this->dificultad) {
            'facil' => 'Fácil',
            'moderada' => 'Moderada',
            'dificil' => 'Difícil',
            'indeterminado' => 'Indeterminado',
            default => $this->dificultad ?? '',
        };
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
     * @return Collection<int, \App\Entity\MensajePregunta>
     */
    public function getMensajes(): Collection
    {
        return $this->mensajes;
    }

    public function addMensaje(\App\Entity\MensajePregunta $mensaje): static
    {
        if (!$this->mensajes->contains($mensaje)) {
            $this->mensajes->add($mensaje);
            $mensaje->setPregunta($this);
        }

        return $this;
    }

    public function removeMensaje(\App\Entity\MensajePregunta $mensaje): static
    {
        if ($this->mensajes->removeElement($mensaje)) {
            // set the owning side to null (unless already changed)
            if ($mensaje->getPregunta() === $this) {
                $mensaje->setPregunta(null);
            }
        }

        return $this;
    }

    public function getPlantilla(): ?Plantilla
    {
        return $this->plantilla;
    }

    public function setPlantilla(?Plantilla $plantilla): static
    {
        $this->plantilla = $plantilla;

        return $this;
    }
}

