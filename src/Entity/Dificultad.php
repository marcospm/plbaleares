<?php

namespace App\Entity;

enum Dificultad: string
{
    case FACIL = 'facil';
    case MODERADA = 'moderada';
    case DIFICIL = 'dificil';
    case INDETERMINADO = 'indeterminado';

    public function getLabel(): string
    {
        return match($this) {
            self::FACIL => 'Fácil',
            self::MODERADA => 'Moderada',
            self::DIFICIL => 'Difícil',
            self::INDETERMINADO => 'Indeterminado',
        };
    }
}

