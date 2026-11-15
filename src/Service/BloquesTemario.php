<?php

namespace App\Service;

/**
 * Agrupa las preguntas de un examen general en los bloques del temario
 * y calcula el porcentaje de aciertos, fallos y blancos de cada uno.
 */
final class BloquesTemario
{
    /**
     * @var list<array{nombre: string, desde: int, hasta: int}>
     */
    private const BLOQUES = [
        ['nombre' => 'Constitución', 'desde' => 1, 'hasta' => 4],
        ['nombre' => 'Estatuto de las Illes Balears', 'desde' => 5, 'hasta' => 5],
        ['nombre' => 'Ley municipal y de régimen local', 'desde' => 6, 'hasta' => 6],
        ['nombre' => 'Tráfico', 'desde' => 7, 'hasta' => 17],
        ['nombre' => 'Derecho policial', 'desde' => 18, 'hasta' => 23],
        ['nombre' => 'Código penal', 'desde' => 24, 'hasta' => 27],
        ['nombre' => 'Protección de datos personales', 'desde' => 28, 'hasta' => 28],
        ['nombre' => 'Igualdad de mujeres y hombres', 'desde' => 29, 'hasta' => 29],
        ['nombre' => 'Derechos de lesbianas, gais, trans, bisexuales e intersexuales y erradicación de la LGTBI-fobia', 'desde' => 30, 'hasta' => 30],
    ];

    /**
     * @param list<array{pregunta?: object, esCorrecta?: bool|null}> $preguntas
     * @return list<array{nombre: string, rango: string, total: int, aciertos: int, fallos: int, blanco: int, pctAciertos: string, pctFallos: string, pctBlanco: string}>
     */
    public function resumir(array $preguntas): array
    {
        $contadores = [];
        foreach (self::BLOQUES as $indice => $bloque) {
            $contadores[$indice] = $this->contadorVacio();
        }
        $otros = $this->contadorVacio();

        foreach ($preguntas as $item) {
            $numero = $this->numeroTema($this->nombreTema($item['pregunta'] ?? null));
            $indiceBloque = $this->indiceBloque($numero);
            if ($indiceBloque === null) {
                $otros = $this->sumar($otros, $item['esCorrecta'] ?? null);
                continue;
            }
            $contadores[$indiceBloque] = $this->sumar($contadores[$indiceBloque], $item['esCorrecta'] ?? null);
        }

        $resultado = [];
        foreach (self::BLOQUES as $indice => $bloque) {
            $fila = $this->fila($bloque['nombre'], $this->rango($bloque['desde'], $bloque['hasta']), $contadores[$indice]);
            if ($fila !== null) {
                $resultado[] = $fila;
            }
        }

        $filaOtros = $this->fila('Otros temas', 'Fuera de los temas 1 a 30', $otros);
        if ($filaOtros !== null) {
            $resultado[] = $filaOtros;
        }

        return $resultado;
    }

    private function indiceBloque(?int $numero): ?int
    {
        if ($numero === null) {
            return null;
        }

        foreach (self::BLOQUES as $indice => $bloque) {
            if ($numero >= $bloque['desde'] && $numero <= $bloque['hasta']) {
                return $indice;
            }
        }

        return null;
    }

    private function numeroTema(?string $nombre): ?int
    {
        if ($nombre === null || !preg_match('/tema\s+(\d+)/iu', $nombre, $coincidencias)) {
            return null;
        }

        return (int) $coincidencias[1];
    }

    private function nombreTema(mixed $pregunta): ?string
    {
        if (!is_object($pregunta) || !method_exists($pregunta, 'getTema')) {
            return null;
        }

        $tema = $pregunta->getTema();
        if (!is_object($tema) || !method_exists($tema, 'getNombre')) {
            return null;
        }

        $nombre = $tema->getNombre();

        return is_string($nombre) ? $nombre : null;
    }

    /**
     * @return array{aciertos: int, fallos: int, blanco: int}
     */
    private function contadorVacio(): array
    {
        return ['aciertos' => 0, 'fallos' => 0, 'blanco' => 0];
    }

    /**
     * @param array{aciertos: int, fallos: int, blanco: int} $contador
     * @return array{aciertos: int, fallos: int, blanco: int}
     */
    private function sumar(array $contador, mixed $esCorrecta): array
    {
        if ($esCorrecta === null) {
            $contador['blanco']++;
        } elseif ($esCorrecta === true) {
            $contador['aciertos']++;
        } else {
            $contador['fallos']++;
        }

        return $contador;
    }

    /**
     * @param array{aciertos: int, fallos: int, blanco: int} $contador
     * @return array{nombre: string, rango: string, total: int, aciertos: int, fallos: int, blanco: int, pctAciertos: string, pctFallos: string, pctBlanco: string}|null
     */
    private function fila(string $nombre, string $rango, array $contador): ?array
    {
        $total = $contador['aciertos'] + $contador['fallos'] + $contador['blanco'];
        if ($total === 0) {
            return null;
        }

        return [
            'nombre' => $nombre,
            'rango' => $rango,
            'total' => $total,
            'aciertos' => $contador['aciertos'],
            'fallos' => $contador['fallos'],
            'blanco' => $contador['blanco'],
            'pctAciertos' => $this->porcentaje($contador['aciertos'], $total),
            'pctFallos' => $this->porcentaje($contador['fallos'], $total),
            'pctBlanco' => $this->porcentaje($contador['blanco'], $total),
        ];
    }

    private function rango(int $desde, int $hasta): string
    {
        if ($desde === $hasta) {
            return 'Tema '.$desde;
        }

        return 'Temas '.$desde.' a '.$hasta;
    }

    private function porcentaje(int $parte, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }

        $redondeado = round($parte * 100 / $total, 1);
        if (abs($redondeado - round($redondeado)) < 0.001) {
            return (int) round($redondeado).'%';
        }

        return number_format($redondeado, 1, ',', '.').'%';
    }
}
