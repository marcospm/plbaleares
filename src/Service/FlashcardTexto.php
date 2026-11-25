<?php

namespace App\Service;

use App\Entity\Pregunta;
use App\Entity\PreguntaMunicipal;

class FlashcardTexto
{
    public function anverso(Pregunta|PreguntaMunicipal $pregunta): string
    {
        return $this->plano($pregunta->getTexto());
    }

    public function reverso(Pregunta|PreguntaMunicipal $pregunta): string
    {
        $letra = strtoupper(trim((string) $pregunta->getRespuestaCorrecta()));
        $opcion = $this->plano($this->opcion($pregunta, $letra));
        $lineas = [];

        if ($letra !== '' && $opcion !== '') {
            $lineas[] = $letra.') '.$opcion;
        } elseif ($opcion !== '') {
            $lineas[] = $opcion;
        }

        $retroalimentacion = $this->plano($pregunta->getRetroalimentacion());
        if ($retroalimentacion !== '') {
            $lineas[] = $retroalimentacion;
        }

        $referencia = $this->referencia($pregunta);
        if ($referencia !== '') {
            $lineas[] = $referencia;
        }

        return implode("\n\n", $lineas);
    }

    private function opcion(Pregunta|PreguntaMunicipal $pregunta, string $letra): ?string
    {
        return match ($letra) {
            'A' => $pregunta->getOpcionA(),
            'B' => $pregunta->getOpcionB(),
            'C' => $pregunta->getOpcionC(),
            'D' => $pregunta->getOpcionD(),
            default => null,
        };
    }

    private function referencia(Pregunta|PreguntaMunicipal $pregunta): string
    {
        if (!$pregunta instanceof Pregunta) {
            return '';
        }

        $partes = [];
        $ley = trim((string) $pregunta->getLey()?->getNombre());
        if ($ley !== '') {
            $partes[] = $ley;
        }

        $articulo = $pregunta->getArticulo();
        if ($articulo !== null && (int) $articulo->getNumero() !== 0) {
            $texto = 'Art. '.$articulo->getNumeroCompleto();
            $nombre = trim((string) $articulo->getNombre());
            if ($nombre !== '') {
                $texto .= ' - '.$nombre;
            }
            $partes[] = $texto;
        }

        return implode(' · ', $partes);
    }

    private function plano(?string $texto): string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return '';
        }

        $texto = preg_replace("/\r\n?/", "\n", $texto) ?? $texto;
        $texto = preg_replace('/\[([^\]]+)\]\((?:[^)]+)\)/', '$1', $texto) ?? $texto;
        $texto = preg_replace('/(\*\*|__)(.+?)\1/s', '$2', $texto) ?? $texto;
        $texto = preg_replace('/^#{1,6}\s+/m', '', $texto) ?? $texto;
        $texto = strip_tags($texto);
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto) ?? $texto;

        return trim($texto);
    }
}
