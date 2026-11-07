<?php

namespace App\Service;

class BloquePreguntasParser
{
    private const MAX_PREGUNTAS = 20;

    /**
     * @return array{preguntas: list<array{id: null, texto: string, opcionA: string, opcionB: string, opcionC: string, opcionD: string, respuestaCorrecta: string, retroalimentacion: string, articulo: string, linea: int}>, errores: list<string>}
     */
    public function parsear(string $texto): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", trim($texto));
        if ($texto === '') {
            return ['preguntas' => [], 'errores' => ['Pega el bloque de preguntas.']];
        }

        $trozos = preg_split('/^\s*---\s*$/m', $texto) ?: [];
        $preguntas = [];
        $errores = [];
        $numero = 0;

        foreach ($trozos as $trozo) {
            $trozo = trim($trozo);
            if ($trozo === '' || preg_match('/^```[\w-]*$/', $trozo)) {
                continue;
            }

            $campos = $this->leerCampos($trozo);
            if ($campos === null) {
                continue;
            }

            ++$numero;
            if (($campos['__duplicada'] ?? '') === '1') {
                $errores[] = 'Bloque ' . $numero . ': hay más de una pregunta. Separa cada pregunta con una línea ---.';
                continue;
            }
            $faltan = [];
            foreach (['ARTICULO', 'PREGUNTA', 'A', 'B', 'C', 'D', 'CORRECTA'] as $requerido) {
                if (($campos[$requerido] ?? '') === '') {
                    $faltan[] = $requerido;
                }
            }

            if ($faltan !== []) {
                $errores[] = 'Bloque ' . $numero . ': faltan ' . implode(', ', $faltan) . '.';
                continue;
            }

            $correcta = $this->normalizarCorrecta($campos['CORRECTA']);
            if (!in_array($correcta, ['A', 'B', 'C', 'D'], true)) {
                $errores[] = 'Bloque ' . $numero . ': la respuesta correcta debe ser A, B, C o D.';
                continue;
            }

            $preguntas[] = [
                'id' => null,
                'texto' => $campos['PREGUNTA'],
                'opcionA' => $campos['A'],
                'opcionB' => $campos['B'],
                'opcionC' => $campos['C'],
                'opcionD' => $campos['D'],
                'respuestaCorrecta' => $correcta,
                'retroalimentacion' => $campos['EXPLICACION'] ?? '',
                'articulo' => $campos['ARTICULO'],
                'tema' => $campos['TEMA'] ?? '',
                'ley' => $campos['LEY'] ?? '',
                'linea' => $numero,
            ];
        }

        if ($numero > self::MAX_PREGUNTAS) {
            return [
                'preguntas' => [],
                'errores' => ['Un bloque admite como máximo 20 preguntas. Has pegado ' . $numero . '.'],
            ];
        }

        if ($numero === 0 && $errores === []) {
            $errores[] = 'No se encontró ninguna pregunta. Cada una debe ir entre --- y usar las etiquetas ARTICULO, PREGUNTA, A, B, C, D, CORRECTA y EXPLICACION.';
        }

        return ['preguntas' => $preguntas, 'errores' => $errores];
    }

    /**
     * @return array<string, string>|null
     */
    private function leerCampos(string $trozo): ?array
    {
        $campos = [];
        $actual = null;
        $tieneEtiqueta = false;

        foreach (preg_split('/\n/', $trozo) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '```')) {
                continue;
            }

            if (preg_match('/^(?:[\s>#`*_-]*)(ARTICULO|PREGUNTA|A|B|C|D|CORRECTA|EXPLICACION|TEMA|LEY)(?:[*_`]*)\s*[:)]\s*(.*)$/iu', $linea, $coincidencias)) {
                $tieneEtiqueta = true;
                $actual = strtoupper($coincidencias[1]);
                if ($actual === 'PREGUNTA' && isset($campos['PREGUNTA'])) {
                    $campos['__duplicada'] = '1';
                }
                $campos[$actual] = $this->limpiarTexto($coincidencias[2]);
                continue;
            }

            if ($actual !== null) {
                $campos[$actual] = $this->limpiarTexto($campos[$actual] . ' ' . $linea);
            }
        }

        return $tieneEtiqueta ? $campos : null;
    }

    private function limpiarTexto(string $valor): string
    {
        $valor = preg_replace('/^(?:\*\*|__|`)+/u', '', $valor) ?? $valor;
        $valor = preg_replace('/(?:\*\*|__|`)+$/u', '', $valor) ?? $valor;
        $valor = preg_replace('/\*\*(.+?)\*\*/u', '$1', $valor) ?? $valor;
        $valor = preg_replace('/__(.+?)__/u', '$1', $valor) ?? $valor;
        $valor = preg_replace('/`([^`]+)`/u', '$1', $valor) ?? $valor;
        $valor = str_replace(['**', '__', '`'], '', $valor);

        return trim($valor);
    }

    private function normalizarCorrecta(string $valor): string
    {
        $valor = strtoupper(trim($valor));
        if (preg_match('/^([ABCD])\b/u', $valor, $coincidencias)) {
            return $coincidencias[1];
        }

        return $valor;
    }
}
