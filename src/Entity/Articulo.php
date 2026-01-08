<?php

namespace App\Entity;

use App\Repository\ArticuloRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticuloRepository::class)]
class Articulo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $numero = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sufijo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $explicacion = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $video = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tituloLey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $capitulo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seccion = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textoLegal = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    #[ORM\ManyToOne(inversedBy: 'articulos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ley $ley = null;

    #[ORM\OneToMany(targetEntity: Pregunta::class, mappedBy: 'articulo')]
    private Collection $preguntas;

    #[ORM\OneToMany(targetEntity: \App\Entity\MensajeArticulo::class, mappedBy: 'articulo', cascade: ['remove'])]
    private Collection $mensajes;

    public function __construct()
    {
        $this->preguntas = new ArrayCollection();
        $this->mensajes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getSufijo(): ?string
    {
        return $this->sufijo;
    }

    public function setSufijo(?string $sufijo): static
    {
        $this->sufijo = $sufijo;

        return $this;
    }

    /**
     * Obtiene el número completo del artículo (número + sufijo si existe)
     */
    public function getNumeroCompleto(): string
    {
        $result = (string)($this->numero ?? '');
        if ($this->sufijo && trim($this->sufijo) !== '') {
            $result .= ' ' . trim($this->sufijo);
        }
        return $result;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getExplicacion(): ?string
    {
        return $this->explicacion;
    }

    public function setExplicacion(?string $explicacion): static
    {
        $this->explicacion = $explicacion;

        return $this;
    }

    public function getVideo(): ?string
    {
        return $this->video;
    }

    public function setVideo(?string $video): static
    {
        $this->video = $video;

        return $this;
    }

    public function getTituloLey(): ?string
    {
        return $this->tituloLey;
    }

    public function setTituloLey(?string $tituloLey): static
    {
        $this->tituloLey = $tituloLey;

        return $this;
    }

    public function getCapitulo(): ?string
    {
        return $this->capitulo;
    }

    public function setCapitulo(?string $capitulo): static
    {
        $this->capitulo = $capitulo;

        return $this;
    }

    public function getSeccion(): ?string
    {
        return $this->seccion;
    }

    public function setSeccion(?string $seccion): static
    {
        $this->seccion = $seccion;

        return $this;
    }

    public function getTextoLegal(): ?string
    {
        return $this->textoLegal;
    }

    public function setTextoLegal(?string $textoLegal): static
    {
        $this->textoLegal = $textoLegal;

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

    public function getLey(): ?Ley
    {
        return $this->ley;
    }

    public function setLey(?Ley $ley): static
    {
        $this->ley = $ley;

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
            $pregunta->setArticulo($this);
        }

        return $this;
    }

    public function removePregunta(Pregunta $pregunta): static
    {
        if ($this->preguntas->removeElement($pregunta)) {
            if ($pregunta->getArticulo() === $this) {
                $pregunta->setArticulo(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, \App\Entity\MensajeArticulo>
     */
    public function getMensajes(): Collection
    {
        return $this->mensajes;
    }

    public function addMensaje(\App\Entity\MensajeArticulo $mensaje): static
    {
        if (!$this->mensajes->contains($mensaje)) {
            $this->mensajes->add($mensaje);
            $mensaje->setArticulo($this);
        }

        return $this;
    }

    public function removeMensaje(\App\Entity\MensajeArticulo $mensaje): static
    {
        if ($this->mensajes->removeElement($mensaje)) {
            if ($mensaje->getArticulo() === $this) {
                $mensaje->setArticulo(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        $result = 'Art. ' . $this->getNumeroCompleto();
        if ($this->nombre) {
            $result .= ' - ' . $this->nombre;
        }
        if ($this->ley) {
            $result .= ' (' . $this->ley->getNombre() . ')';
        }
        return $result;
    }
}

