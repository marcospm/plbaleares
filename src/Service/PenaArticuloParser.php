<?php

namespace App\Service;

class PenaArticuloParser
{
    public const TIPOS = [
        'prision' => 'Prisión',
        'multa' => 'Multa',
        'trabajos' => 'Trabajos en beneficio de la comunidad',
        'privacion' => 'Privación del permiso',
        'inhabilitacion' => 'Inhabilitación',
    ];

    private const CATALOGO = [
        'prision' => ['3 a 6 meses', '6 a 12 meses', '6 a 18 meses', '1 a 2 años', '1 a 3 años', '2 a 5 años', '2 a 6 años'],
        'multa' => ['1 a 3 meses', '3 a 6 meses', '6 a 12 meses', '12 a 24 meses', '1 a 2 años', '1 a 3 años', '2 a 5 años'],
        'trabajos' => ['10 a 30 días', '31 a 90 días', '31 a 180 días'],
        'privacion' => ['1 a 4 años', '1 a 6 años', '2 a 6 años'],
        'inhabilitacion' => ['1 a 3 años', '2 a 5 años', '6 a 12 años', '10 a 15 años'],
    ];

    /**
     * @return array{
     *     tipos: list<string>,
     *     tramos: array<string, list<string>>,
     *     opciones: array<string, list<string>>
     * }|null
     */
    public function extraer(?string $explicacion): ?array
    {
        $texto = $this->normalizar($explicacion);
        if ($texto === '') {
            return null;
        }

        $tramos = [];
        foreach ($this->buscarTramos($texto) as $tramo) {
            $tramos[$tramo['tipo']][$tramo['etiqueta']] = $tramo['etiqueta'];
        }

        $tipos = array_keys($tramos);
        foreach (array_keys(self::TIPOS) as $tipo) {
            if (!in_array($tipo, $tipos, true) && $this->mencionaTipo($texto, $tipo)) {
                $tipos[] = $tipo;
            }
        }

        $tipos = array_values(array_filter(
            array_keys(self::TIPOS),
            static fn (string $tipo): bool => in_array($tipo, $tipos, true)
        ));

        if ($tipos === []) {
            return null;
        }

        $tramosLimpios = [];
        $opciones = [];
        foreach (array_keys(self::TIPOS) as $tipo) {
            $etiquetas = array_values($tramos[$tipo] ?? []);
            $tramosLimpios[$tipo] = $etiquetas;
            $opciones[$tipo] = $this->opciones($etiquetas, $tipo);
        }

        return [
            'tipos' => $tipos,
            'tramos' => $tramosLimpios,
            'opciones' => $opciones,
        ];
    }

    public function titulo(?string $nombre, ?string $explicacion): string
    {
        $nombre = trim((string) $nombre);
        if ($nombre !== '') {
            return $nombre;
        }

        $texto = preg_replace('/\*\*/', '', (string) $explicacion) ?? '';
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($texto)) ?? '');
        $texto = preg_replace('/^Qué dice:\s*/iu', '', $texto) ?? $texto;
        $texto = preg_replace('/\s*Apunte test:.*$/iu', '', $texto) ?? $texto;
        $frase = trim((string) (preg_split('/(?<=\.)\s/u', $texto)[0] ?? ''), " \t\n\r\0\x0B.");
        if ($frase === '') {
            return '';
        }
        if (mb_strlen($frase) > 140) {
            $frase = rtrim(mb_substr($frase, 0, 137)) . '…';
        }

        return $frase;
    }

    private function normalizar(?string $texto): string
    {
        $texto = html_entity_decode(strip_tags((string) $texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = str_replace(['**', '*'], '', $texto);
        $texto = mb_strtolower($texto);
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            '–' => '-', '—' => '-', '→' => ' -> ',
        ]);
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * @return list<array{tipo: string, etiqueta: string}>
     */
    private function buscarTramos(string $texto): array
    {
        $patrones = [
            '/(prision|multa|trabajos en beneficio de la comunidad|tbc|privacion del derecho a conducir|privacion del permiso de conducir|privacion del permiso|inhabilitacion(?: especial| absoluta)?)\s+de\s+(\d+)\s*(?:a|-)\s*(\d+)\s*(meses|anos|dias)/u',
            '/(\d+)\s*(?:a|-)\s*(\d+)\s*(meses|anos|dias)\s+de\s+(prision|multa|trabajos en beneficio de la comunidad|inhabilitacion)/u',
            '/multa(?::| de)?\s*(?:\([a-z]\)\s*)?(\d+)\s*(?:a|-)\s*(\d+)\s*(meses|anos|dias)/u',
        ];

        $tramos = [];
        if (preg_match_all($patrones[0], $texto, $coincidencias, PREG_SET_ORDER)) {
            foreach ($coincidencias as $coincidencia) {
                $tramos[] = $this->tramo($coincidencia[1], (int) $coincidencia[2], (int) $coincidencia[3], $coincidencia[4]);
            }
        }
        if (preg_match_all($patrones[1], $texto, $coincidencias, PREG_SET_ORDER)) {
            foreach ($coincidencias as $coincidencia) {
                $tramos[] = $this->tramo($coincidencia[4], (int) $coincidencia[1], (int) $coincidencia[2], $coincidencia[3]);
            }
        }
        if (preg_match_all($patrones[2], $texto, $coincidencias, PREG_SET_ORDER)) {
            foreach ($coincidencias as $coincidencia) {
                $tramos[] = $this->tramo('multa', (int) $coincidencia[1], (int) $coincidencia[2], $coincidencia[3]);
            }
        }

        foreach (preg_split('/(?<=\.)\s/u', $texto) ?: [] as $frase) {
            if (preg_match('/\bmulta\b/u', $frase)
                && preg_match_all('/(\d+)\s*(?:a|-)\s*(\d+)\s*(meses|anos|dias)/u', $frase, $coincidencias, PREG_SET_ORDER)
            ) {
                foreach ($coincidencias as $coincidencia) {
                    $tramos[] = $this->tramo('multa', (int) $coincidencia[1], (int) $coincidencia[2], $coincidencia[3]);
                }
            }

            if (preg_match('/\bpena\b/u', $frase) && !preg_match('/\bmulta\b/u', $frase)
                && preg_match_all('/(\d+)\s*(?:a|-)\s*(\d+)\s*anos/u', $frase, $coincidencias, PREG_SET_ORDER)
            ) {
                foreach ($coincidencias as $coincidencia) {
                    $tramos[] = $this->tramo('prision', (int) $coincidencia[1], (int) $coincidencia[2], 'anos');
                }
            }
        }

        if (preg_match('/\bpena\b|\bprision\b/u', $texto)
            && preg_match_all('/(\d+)\s+anos y\s+(\d+)\s+meses a\s+(\d+)\s+anos/u', $texto, $coincidencias, PREG_SET_ORDER)
        ) {
            foreach ($coincidencias as $coincidencia) {
                $tramos[] = [
                    'tipo' => 'prision',
                    'etiqueta' => $coincidencia[1] . ' años y ' . $coincidencia[2] . ' meses a ' . $coincidencia[3] . ' años',
                ];
            }
        }

        return array_values(array_filter($tramos));
    }

    /**
     * @return array{tipo: string, etiqueta: string}|null
     */
    private function tramo(string $palabra, int $min, int $max, string $unidad): ?array
    {
        if ($min <= 0 || $max < $min) {
            return null;
        }

        $tipo = match (true) {
            str_starts_with($palabra, 'prision') => 'prision',
            str_starts_with($palabra, 'multa') => 'multa',
            str_starts_with($palabra, 'trabajo'), str_starts_with($palabra, 'tbc') => 'trabajos',
            str_starts_with($palabra, 'privacion') => 'privacion',
            str_starts_with($palabra, 'inhabilitacion') => 'inhabilitacion',
            default => null,
        };
        if ($tipo === null) {
            return null;
        }

        $nombreUnidad = ['meses' => 'meses', 'anos' => 'años', 'dias' => 'días'][$unidad] ?? $unidad;

        return [
            'tipo' => $tipo,
            'etiqueta' => $min . ' a ' . $max . ' ' . $nombreUnidad,
        ];
    }

    private function mencionaTipo(string $texto, string $tipo): bool
    {
        $texto = preg_replace('/rebajar la pena de prision\b/', '', $texto) ?? $texto;
        $texto = preg_replace('/pena de prision de mas de \d+ anos/', '', $texto) ?? $texto;

        return match ($tipo) {
            'prision' => (bool) preg_match('/->\s*prision\b|\bprision\b\s*(?:,|y\b|e\b|o\b)|\b(?:con|y|e|,|o)\s+prision\b|\bpena de prision\b/u', $texto),
            'multa' => (bool) preg_match('/->\s*multa\b|\bmulta\b\s*(?:,|y\b|e\b|o\b)|\b(?:con|y|e|,|o)\s+multa\b|\bpenas? de multa\b/u', $texto),
            'trabajos' => (bool) preg_match('/\btbc\b|trabajos en beneficio de la comunidad/u', $texto),
            'privacion' => (bool) preg_match('/privacion del (derecho a conducir|permiso)\b/u', $texto),
            'inhabilitacion' => (bool) preg_match('/\binhabilitacion\b/u', $texto),
            default => false,
        };
    }

    /**
     * @param list<string> $correctas
     * @return list<string>
     */
    private function opciones(array $correctas, string $tipo): array
    {
        $opciones = self::CATALOGO[$tipo] ?? [];
        foreach ($correctas as $correcta) {
            if (!in_array($correcta, $opciones, true)) {
                $opciones[] = $correcta;
            }
        }
        shuffle($opciones);

        return array_values($opciones);
    }
}
