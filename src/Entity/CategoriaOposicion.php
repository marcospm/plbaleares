<?php

namespace App\Entity;

enum CategoriaOposicion: string
{
    case POLICIA = 'policia';
    case OFICIAL = 'oficial';
    case SUBINSPECTOR = 'subinspector';
    case INSPECTOR = 'inspector';
    case MAYOR = 'mayor';
    case COMISARIO = 'comisario';
    case INTENDENTE = 'intendente';

    public function getLabel(): string
    {
        return match ($this) {
            self::POLICIA => 'Policía',
            self::OFICIAL => 'Oficial',
            self::SUBINSPECTOR => 'Subinspector',
            self::INSPECTOR => 'Inspector',
            self::MAYOR => 'Mayor',
            self::COMISARIO => 'Comisario',
            self::INTENDENTE => 'Intendente',
        };
    }
}
