<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\ParameterBag;

final class Consulta
{
    /**
     * Lee un entero de la petición. Si falta o no es un número, devuelve el valor por defecto
     * en lugar de responder 400.
     */
    public static function entero(ParameterBag $parametros, string $clave, int $porDefecto = 0): int
    {
        if (!$parametros->has($clave)) {
            return $porDefecto;
        }

        $valor = $parametros->all()[$clave] ?? null;
        if (is_int($valor)) {
            return $valor;
        }
        if (is_string($valor) && preg_match('/^-?\d+$/', $valor) === 1) {
            return (int) $valor;
        }

        return $porDefecto;
    }
}
