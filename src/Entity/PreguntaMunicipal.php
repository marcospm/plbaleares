<?php

namespace App\Entity;

use App\Repository\PreguntaMunicipalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreguntaMunicipalRepository::class)]
class PreguntaMunicipal
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

    #[ORM\ManyToOne(inversedBy: 'preguntasMunicipales')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TemaMunicipal $temaMunicipal = null;

    #[ORM\ManyToOne(inversedBy: 'preguntasMunicipales')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Municipio $municipio = null;

    #[ORM\ManyToOne(inversedBy: 'preguntas')]
    #[ORM\JoinColumn(nullable: true)]
    private ?PlantillaMunicipal $plantilla = null;

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

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

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

    public function getMunicipio(): ?Municipio
    {
        return $this->municipio;
    }

    public function setMunicipio(?Municipio $municipio): static
    {
        $this->municipio = $municipio;

        return $this;
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

    public function getPlantilla(): ?PlantillaMunicipal
    {
        return $this->plantilla;
    }

    public function setPlantilla(?PlantillaMunicipal $plantilla): static
    {
        $this->plantilla = $plantilla;

        return $this;
    }
}












