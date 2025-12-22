<?php

namespace App\Entity;

enum Dificultad: string
{
    case FACIL = 'facil';
    case MODERADA = 'moderada';
    case DIFICIL = 'dificil';

    public function getLabel(): string
    {
        return match($this) {
            self::FACIL => 'Fácil',
            self::MODERADA => 'Moderada',
            self::DIFICIL => 'Difícil',
        };
    }
}

