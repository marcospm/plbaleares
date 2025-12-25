<?php

namespace App\Entity;

use App\Repository\MensajePreguntaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MensajePreguntaRepository::class)]
class MensajePregunta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pregunta $pregunta = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $autor = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $mensaje = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'respuestas')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $mensajePadre = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'mensajePadre', cascade: ['remove'])]
    private \Doctrine\Common\Collections\Collection $respuestas;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $esRespuesta = false;

    public function __construct()
    {
        $this->respuestas = new \Doctrine\Common\Collections\ArrayCollection();
        $this->fechaCreacion = new \DateTime();
        $this->esRespuesta = false;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAutor(): ?User
    {
        return $this->autor;
    }

    public function setAutor(?User $autor): static
    {
        $this->autor = $autor;

        return $this;
    }

    public function getMensaje(): ?string
    {
        return $this->mensaje;
    }

    public function setMensaje(string $mensaje): static
    {
        $this->mensaje = $mensaje;

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

    public function getMensajePadre(): ?self
    {
        return $this->mensajePadre;
    }

    public function setMensajePadre(?self $mensajePadre): static
    {
        $this->mensajePadre = $mensajePadre;

        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, self>
     */
    public function getRespuestas(): \Doctrine\Common\Collections\Collection
    {
        return $this->respuestas;
    }

    public function addRespuesta(self $respuesta): static
    {
        if (!$this->respuestas->contains($respuesta)) {
            $this->respuestas->add($respuesta);
            $respuesta->setMensajePadre($this);
        }

        return $this;
    }

    public function removeRespuesta(self $respuesta): static
    {
        if ($this->respuestas->removeElement($respuesta)) {
            // set the owning side to null (unless already changed)
            if ($respuesta->getMensajePadre() === $this) {
                $respuesta->setMensajePadre(null);
            }
        }

        return $this;
    }

    public function isEsRespuesta(): bool
    {
        return $this->esRespuesta;
    }

    public function setEsRespuesta(bool $esRespuesta): static
    {
        $this->esRespuesta = $esRespuesta;

        return $this;
    }

    public function esDeProfesor(): bool
    {
        return $this->autor && (
            in_array('ROLE_PROFESOR', $this->autor->getRoles()) || 
            in_array('ROLE_ADMIN', $this->autor->getRoles())
        );
    }
}

