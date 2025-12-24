<?php

namespace App\Service;

use App\Entity\Pregunta;
use App\Entity\PreguntaMunicipal;
use App\Entity\Tema;
use App\Entity\Ley;
use App\Entity\Articulo;
use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use Doctrine\ORM\EntityManagerInterface;

class PreguntaService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Crea una pregunta general desde datos del formulario
     */
    public function crearPreguntaGeneral(array $datos, Tema $tema, Ley $ley, Articulo $articulo): Pregunta
    {
        $pregunta = new Pregunta();
        $pregunta->setTexto($datos['texto']);
        $pregunta->setOpcionA($datos['opcionA']);
        $pregunta->setOpcionB($datos['opcionB']);
        $pregunta->setOpcionC($datos['opcionC']);
        $pregunta->setOpcionD($datos['opcionD']);
        $pregunta->setRespuestaCorrecta($datos['respuestaCorrecta']);
        $pregunta->setDificultad($datos['dificultad']);
        $pregunta->setRetroalimentacion($datos['retroalimentacion'] ?? null);
        $pregunta->setTema($tema);
        $pregunta->setLey($ley);
        $pregunta->setArticulo($articulo);
        $pregunta->setActivo(true);

        $this->entityManager->persist($pregunta);
        $this->entityManager->flush();

        return $pregunta;
    }

    /**
     * Crea una pregunta municipal desde datos del formulario
     */
    public function crearPreguntaMunicipal(array $datos, TemaMunicipal $temaMunicipal, Municipio $municipio): PreguntaMunicipal
    {
        $pregunta = new PreguntaMunicipal();
        $pregunta->setTexto($datos['texto']);
        $pregunta->setOpcionA($datos['opcionA']);
        $pregunta->setOpcionB($datos['opcionB']);
        $pregunta->setOpcionC($datos['opcionC']);
        $pregunta->setOpcionD($datos['opcionD']);
        $pregunta->setRespuestaCorrecta($datos['respuestaCorrecta']);
        $pregunta->setDificultad($datos['dificultad']);
        $pregunta->setRetroalimentacion($datos['retroalimentacion'] ?? null);
        $pregunta->setTemaMunicipal($temaMunicipal);
        $pregunta->setMunicipio($municipio);
        $pregunta->setActivo(true);

        $this->entityManager->persist($pregunta);
        $this->entityManager->flush();

        return $pregunta;
    }

    /**
     * Valida los datos de una pregunta general
     */
    public function validarDatosPreguntaGeneral(array $datos): array
    {
        $errores = [];

        if (empty($datos['texto'])) {
            $errores[] = 'El texto de la pregunta es requerido';
        }
        if (empty($datos['opcionA'])) {
            $errores[] = 'La opción A es requerida';
        }
        if (empty($datos['opcionB'])) {
            $errores[] = 'La opción B es requerida';
        }
        if (empty($datos['opcionC'])) {
            $errores[] = 'La opción C es requerida';
        }
        if (empty($datos['opcionD'])) {
            $errores[] = 'La opción D es requerida';
        }
        if (empty($datos['respuestaCorrecta']) || !in_array($datos['respuestaCorrecta'], ['A', 'B', 'C', 'D'])) {
            $errores[] = 'La respuesta correcta debe ser A, B, C o D';
        }
        if (empty($datos['dificultad']) || !in_array($datos['dificultad'], ['facil', 'moderada', 'dificil'])) {
            $errores[] = 'La dificultad debe ser fácil, moderada o difícil';
        }

        return $errores;
    }

    /**
     * Valida los datos de una pregunta municipal
     */
    public function validarDatosPreguntaMunicipal(array $datos): array
    {
        return $this->validarDatosPreguntaGeneral($datos);
    }
}


