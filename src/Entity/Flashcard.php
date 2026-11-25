<?php

namespace App\Entity;

use App\Repository\FlashcardRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlashcardRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_flashcard_usuario_pregunta', columns: ['usuario_id', 'pregunta_id'])]
#[ORM\UniqueConstraint(name: 'uniq_flashcard_usuario_pregunta_municipal', columns: ['usuario_id', 'pregunta_municipal_id'])]
class Flashcard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $usuario = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tema $tema = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TemaMunicipal $temaMunicipal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Pregunta $pregunta = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PreguntaMunicipal $preguntaMunicipal = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $anverso = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $reverso = '';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creadoEn = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $actualizadoEn = null;

    public function __construct()
    {
        $ahora = new \DateTime();
        $this->creadoEn = $ahora;
        $this->actualizadoEn = $ahora;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): ?User
    {
        return $this->usuario;
    }

    public function setUsuario(?User $usuario): static
    {
        $this->usuario = $usuario;

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

    public function getTemaMunicipal(): ?TemaMunicipal
    {
        return $this->temaMunicipal;
    }

    public function setTemaMunicipal(?TemaMunicipal $temaMunicipal): static
    {
        $this->temaMunicipal = $temaMunicipal;

        return $this;
    }

    public function getPregunta(): ?Pregunta
    {
        return $this->pregunta;
    }

    public function setPregunta(?Pregunta $pregunta): static
    {
        $this->pregunta = $pregunta;

        return $this;
    }

    public function getPreguntaMunicipal(): ?PreguntaMunicipal
    {
        return $this->preguntaMunicipal;
    }

    public function setPreguntaMunicipal(?PreguntaMunicipal $preguntaMunicipal): static
    {
        $this->preguntaMunicipal = $preguntaMunicipal;

        return $this;
    }

    public function getAnverso(): string
    {
        return $this->anverso;
    }

    public function setAnverso(string $anverso): static
    {
        $this->anverso = $anverso;

        return $this;
    }

    public function getReverso(): string
    {
        return $this->reverso;
    }

    public function setReverso(string $reverso): static
    {
        $this->reverso = $reverso;

        return $this;
    }

    public function getCreadoEn(): ?\DateTimeInterface
    {
        return $this->creadoEn;
    }

    public function setCreadoEn(\DateTimeInterface $creadoEn): static
    {
        $this->creadoEn = $creadoEn;

        return $this;
    }

    public function getActualizadoEn(): ?\DateTimeInterface
    {
        return $this->actualizadoEn;
    }

    public function setActualizadoEn(\DateTimeInterface $actualizadoEn): static
    {
        $this->actualizadoEn = $actualizadoEn;

        return $this;
    }

    public function getClaveTema(): string
    {
        if ($this->temaMunicipal?->getId() !== null) {
            return 'municipal-'.$this->temaMunicipal->getId();
        }

        if ($this->tema?->getId() !== null) {
            return 'general-'.$this->tema->getId();
        }

        return 'sin-tema';
    }

    public function getNombreTema(): string
    {
        if ($this->temaMunicipal !== null) {
            $nombre = $this->temaMunicipal->getNombre() ?: 'Sin tema';
            $municipio = $this->temaMunicipal->getMunicipio()?->getNombre();
            if (is_string($municipio) && trim($municipio) !== '') {
                return trim($municipio).' · '.$nombre;
            }

            return $nombre;
        }

        $nombre = $this->tema?->getNombre();

        return is_string($nombre) && trim($nombre) !== '' ? trim($nombre) : 'Sin tema';
    }
}
