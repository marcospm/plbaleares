<?php

namespace App\Service;

/**
 * Prompt del simulacro aptitudinal. El texto de maquetación es el que ya
 * genera el PDF con la imagen de Bispol. El nivel cambia la dificultad y
 * las áreas elegidas cambian el alcance: una, varias o las seis.
 */
final class PromptExamenPsicotecnico
{
    public const NIVEL_NORMAL = 'normal';
    public const NIVEL_ALTO = 'alto';

    private const PREGUNTAS_POR_AREA = 14;
    private const MINUTOS_POR_AREA = 10;

    /**
     * @return array<string, string>
     */
    public function niveles(): array
    {
        return [
            self::NIVEL_NORMAL => 'Normal',
            self::NIVEL_ALTO => 'Alto (muy difícil)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function areas(): array
    {
        $areas = [];
        foreach ($this->definicion() as $clave => $area) {
            $areas[$clave] = $area['nombre'];
        }

        return $areas;
    }

    public function esNivel(string $nivel): bool
    {
        return isset($this->niveles()[$nivel]);
    }

    /**
     * null significa las seis áreas, en el orden del examen.
     * Una lista vacía o sin claves válidas se devuelve vacía.
     *
     * @param list<string>|null $areas
     * @return list<string>
     */
    public function normalizar(?array $areas): array
    {
        $orden = array_keys($this->definicion());
        if ($areas === null) {
            return $orden;
        }

        $pedidas = [];
        foreach ($areas as $area) {
            if (is_string($area) && in_array($area, $orden, true)) {
                $pedidas[$area] = true;
            }
        }

        $seleccion = [];
        foreach ($orden as $clave) {
            if (isset($pedidas[$clave])) {
                $seleccion[] = $clave;
            }
        }

        return $seleccion;
    }

    /**
     * @param list<string>|null $areas
     */
    public function generar(string $nivel, ?array $areas = null): string
    {
        if (!$this->esNivel($nivel)) {
            throw new \InvalidArgumentException('Nivel de dificultad no válido.');
        }

        $claves = $this->normalizar($areas);
        if ($claves === []) {
            throw new \InvalidArgumentException('Selecciona al menos un área.');
        }

        $total = count($claves) * self::PREGUNTAS_POR_AREA;
        $minutos = count($claves) * self::MINUTOS_POR_AREA;
        $maximo = $total * 3;
        $nivelPortada = $nivel === self::NIVEL_ALTO ? 'Alto (muy difícil)' : 'Normal';
        $bloqueNivel = $this->bloqueNivel($nivel, $claves);
        $lamina = $this->bloqueLamina($claves);
        $estructura = $this->estructura($claves, $nivelPortada, $total, $minutos, $maximo);
        $cierre = $this->antesDeEntregar($claves);

        return <<<PROMPT
Genera un simulacro NUEVO de la prueba aptitudinal psicotécnica de Policía Local
(formato del proceso unificado de Baleares), con preguntas originales y distintas
a las de simulacros anteriores, y entrégamelo en PDF con la imagen de Bispol.

ARCHIVO DE REFERENCIA
- Te adjunto el PDF «Simulacro_psicotecnico_Bispol». Ábrelo y copia su formato y sus estilos: portada, cabecera, pie, colores, tipografías, franjas de título, márgenes, tamaño de página, numeración, cómo se presentan las preguntas y las opciones, la lámina de memoria y la página de soluciones.
- El contenido nuevo (preguntas, áreas, dificultad, número de ítems y tiempos) es el de este mensaje. El aspecto visual es el de ese PDF. Si una indicación de estilo de este texto no coincide con el archivo, manda el archivo.
{$this->alcance($claves)}
FORMATO DEL EXAMEN
- {$total} preguntas, 4 opciones (A–D), una sola correcta. Reparte la letra correcta al azar.
{$this->listadoAreas($claves)}
{$bloqueNivel}
{$lamina}
{$estructura}
IMAGEN DE BISPOL
- Cabecera en todas las páginas: el logo pequeño en azul marino a la izquierda
  y «SIMULACRO PSICOTÉCNICO · PRUEBA APTITUDINAL» a la derecha.
- Pie: «bispol.es · info@bispol.es · 620 17 62 59» y «página / total».
- Títulos de sección en franjas azul marino #312d45 con texto blanco en
  mayúsculas espaciadas y una estrella ★ delante.
- Títulos en Montserrat y texto en Inter. Para las piezas y cuadrículas de
  las figuras usa el lila #8a84b8, para que no se confundan con el negro.

{$cierre}
PROMPT;
    }

    /**
     * @param list<string> $claves
     */
    private function alcance(array $claves): string
    {
        if (count($claves) === count($this->definicion())) {
            return '';
        }

        $nombres = array_map(fn (string $clave): string => $this->definicion()[$clave]['nombre'], $claves);
        $lista = $this->unir($nombres);
        if (count($claves) === 1) {
            return "Es un simulacro de una sola aptitud: {$lista}. No incluyas ninguna otra.\n";
        }

        return "Es un ómnibus solo de estas aptitudes: {$lista}. No incluyas ninguna otra.\n";
    }

    /**
     * @param list<string> $claves
     */
    private function listadoAreas(array $claves): string
    {
        $n = count($claves);
        $aptitud = $n === 1 ? '1 aptitud' : "{$n} aptitudes";
        $cierre = $n === count($this->definicion()) ? 'en este orden:' : 'en este orden y ninguna más:';
        $lineas = ["- {$aptitud} × 14 preguntas, {$cierre}"];
        $desde = 1;
        foreach ($claves as $indice => $clave) {
            $hasta = $desde + self::PREGUNTAS_POR_AREA - 1;
            $area = $this->definicion()[$clave];
            $numero = $indice + 1;
            $lineas[] = "  {$numero}. {$area['nombre']} ({$desde}–{$hasta}): {$area['detalle']}";
            $desde = $hasta + 1;
        }

        return implode("\n", $lineas);
    }

    /**
     * @param list<string> $claves
     */
    private function bloqueNivel(string $nivel, array $claves): string
    {
        $lineas = [$nivel === self::NIVEL_ALTO ? $this->generalAlto() : $this->generalNormal()];
        foreach ($claves as $clave) {
            $lineas[] = $this->definicion()[$clave][$nivel];
        }
        if ($nivel === self::NIVEL_ALTO) {
            $lineas[] = '- Si dos opciones se pueden defender, reescribe la pregunta.';
        }

        return implode("\n", $lineas);
    }

    /**
     * @param list<string> $claves
     */
    private function bloqueLamina(array $claves): string
    {
        if (!in_array('memoria', $claves, true)) {
            return "LÁMINA DE MEMORIA\n- No incluyas lámina de memoria: este simulacro no lleva memoria visual.\n";
        }

        return <<<'LAMINA'
LÁMINA DE MEMORIA
- Una página propia: una cuadrícula 3×3 de figuras de colores, cada una con
  un número y una letra, y una franja con 3 vehículos y sus matrículas.
  Cambia figuras, colores, números, letras, vehículos y matrículas respecto
  a otros simulacros.
LAMINA;
    }

    /**
     * @param list<string> $claves
     */
    private function estructura(array $claves, string $nivelPortada, int $total, int $minutos, int $maximo): string
    {
        $n = count($claves);
        $aptitudes = $n === 1 ? 'la aptitud' : "las {$n} aptitudes";
        if (in_array('memoria', $claves, true)) {
            $tiempo = "3 min de memorización sin tomar notas, 8 min de espera, {$minutos} min para las\n   {$total} preguntas (las 14 primeras sobre la lámina)";
        } else {
            $tiempo = "{$minutos} min para las {$total} preguntas";
        }

        $pasos = [
            "1. Portada: bloque azul marino #312d45 con el logo adjunto en blanco (la gorra\n   en blanco con sus detalles en azul marino), título «Simulacro psicotécnico»\n   y subtítulo «PRUEBA APTITUDINAL · POLICÍA LOCAL». Debajo del subtítulo,\n   indica el nivel: «{$nivelPortada}». Debajo, las instrucciones:\n   {$tiempo}, puntuación +3 por acierto,\n   −1 por fallo, 0 en blanco, máximo {$maximo}. Añade una tabla con {$aptitudes}\n   y su rango de preguntas.",
        ];
        $numero = 2;
        if (in_array('memoria', $claves, true)) {
            $pasos[] = "{$numero}. Lámina de memoria.";
            ++$numero;
        }
        $pasos[] = "{$numero}. Preguntas, agrupadas por aptitud.";
        ++$numero;
        $pasos[] = "{$numero}. Soluciones: número, letra correcta y explicación breve de cada pregunta.";

        $lista = implode("\n", $pasos);

        return <<<PDF
ESTRUCTURA DEL PDF (A4)
{$lista}
No incluyas hoja de respuestas, ni notas sobre el centil o el baremo, ni
frases del tipo «preguntas originales» o «las soluciones están al final».
PDF;
    }

    /**
     * @param list<string> $claves
     */
    private function antesDeEntregar(array $claves): string
    {
        if (count($claves) === count($this->definicion())) {
            return <<<'CIERRE'
ANTES DE ENTREGAR
- Comprueba con código cada respuesta: los recuentos, las series, los
  pliegues de los cubos, que las piezas sean asimétricas y que en cada
  pregunta haya 4 opciones distintas y una sola correcta.
- Revisa visualmente el PDF y envíamelo.
CIERRE;
        }

        $partes = ['que en cada pregunta haya 4 opciones distintas y una sola correcta'];
        if (in_array('numerico', $claves, true) || in_array('abstracto', $claves, true)) {
            array_unshift($partes, 'las series');
        }
        if (in_array('atencion', $claves, true) || in_array('memoria', $claves, true)) {
            array_unshift($partes, 'los recuentos');
        }
        if (in_array('espacial', $claves, true)) {
            $partes[] = 'los pliegues de los cubos y que las piezas sean asimétricas';
        }

        $comprobacion = $this->unir($partes);

        return <<<CIERRE
ANTES DE ENTREGAR
- Comprueba con código cada respuesta: {$comprobacion}.
- Revisa visualmente el PDF y envíamelo.
CIERRE;
    }

    /**
     * @param list<string> $partes
     */
    private function unir(array $partes): string
    {
        $partes = array_values($partes);
        $n = count($partes);
        if ($n <= 1) {
            return $partes[0] ?? '';
        }
        $ultima = array_pop($partes);

        return implode(', ', $partes).' y '.$ultima;
    }

    private function generalNormal(): string
    {
        return <<<'NIVEL'
- Dificultad: normal, la de una convocatoria estándar. Varía los tipos dentro
  de cada bloque. Cada pregunta se resuelve en uno o dos pasos y el enunciado
  dice con claridad qué hay que hacer.
NIVEL;
    }

    private function generalAlto(): string
    {
        return <<<'NIVEL'
- Dificultad: alto. Tiene que ser muy difícil, en el extremo de una
  convocatoria exigente. Varía los tipos dentro de cada bloque. Sigue habiendo
  una sola respuesta correcta y cada pregunta se resuelve en menos de un
  minuto, pero hacen falta varios pasos: quien se quede en el primero tiene
  que caer en un distractor que también está entre las opciones.
NIVEL;
    }

    /**
     * @return array<string, array{nombre: string, detalle: string, normal: string, alto: string}>
     */
    private function definicion(): array
    {
        return [
            'memoria' => [
                'nombre' => 'Memoria visual',
                'detalle' => 'preguntas sobre una lámina que se memoriza antes.',
                'normal' => '- Memoria: figuras, colores y matrículas bien distinguibles. Pregunta por un dato principal (figura, color, número, letra, vehículo o matrícula).',
                'alto' => '- Memoria: mantén la cuadrícula 3×3 y la franja de 3 vehículos, pero acerca figuras, colores, letras y matrículas para que se confundan. Pregunta por un detalle secundario (una letra pequeña, la posición o un número que no es el más visible), no por el elemento que más destaca.',
            ],
            'verbal' => [
                'nombre' => 'Razonamiento verbal',
                'detalle' => 'sinónimos, antónimos, analogías, palabra que no encaja, significado de palabras, ortografía, refranes, frase correcta.',
                'normal' => '- Verbal: sinónimos, antónimos y analogías de una sola relación, con vocabulario frecuente. Ortografía y refranes sin trampas de redacción.',
                'alto' => '- Verbal: analogías con un matiz (parte/todo, grado o función) y un distractor que solo cumple la mitad de la relación. En ortografía y frase correcta, el fallo tiene que ser fino.',
            ],
            'numerico' => [
                'nombre' => 'Razonamiento numérico',
                'detalle' => 'series numéricas y problemas cortos (proporciones, porcentajes, medias, ecuaciones sencillas).',
                'normal' => '- Numérico: series de un solo patrón y problemas de un cálculo (una proporción, un porcentaje, una media o una ecuación sencilla).',
                'alto' => '- Numérico: series con dos patrones alternos, porcentajes encadenados o una media en dos pasos. Las cifras que salen si el cálculo se queda a medias van como opciones.',
            ],
            'abstracto' => [
                'nombre' => 'Razonamiento abstracto',
                'detalle' => 'series de figuras, matrices 3×3 con casilla «?», figura diferente. Todas dibujadas en SVG.',
                'normal' => '- Abstracto: una sola regla por serie o matriz (forma, número, giro o relleno). Sigue dibujándolas en SVG.',
                'alto' => '- Abstracto: matrices 3×3 con dos reglas a la vez. Las opciones erróneas cumplen solo una de las dos. Siguen dibujadas en SVG.',
            ],
            'espacial' => [
                'nombre' => 'Razonamiento espacial',
                'detalle' => 'giros de piezas frente a su imagen en espejo, reflejos, desarrollos de cubo (cara opuesta), contar cubos en isométrico, giros de cuadrículas. Todas dibujadas en SVG.',
                'normal' => '- Espacial: una sola transformación (giro, reflejo o desarrollo). Las piezas siguen en SVG y son asimétricas.',
                'alto' => '- Espacial: dos transformaciones combinadas (giro y espejo, o un desarrollo con dos caras marcadas). Incluye la imagen especular de la solución como distractor. Todo en SVG, con piezas asimétricas.',
            ],
            'atencion' => [
                'nombre' => 'Atención',
                'detalle' => 'contar letras, números o símbolos; elegir la opción idéntica (códigos, matrículas, DNI, nombres); claves letra-número; filas iguales o distintas.',
                'normal' => '- Atención: el objetivo se distingue sin una excepción escondida en la consigna.',
                'alto' => '- Atención: filas largas y una consigna con una excepción explícita. El error de contar de más o de menos es una de las opciones.',
            ],
        ];
    }
}
