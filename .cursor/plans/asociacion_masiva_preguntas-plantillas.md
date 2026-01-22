---
name: Asociación masiva preguntas-plantillas
overview: Crear una interfaz web para que el admin pueda asociar preguntas a plantillas de forma masiva usando filtros, con distribución inteligente que reutiliza plantillas existentes del mismo tema/dificultad y crea nuevas solo cuando sea necesario, manteniendo un máximo de ~50 preguntas por plantilla.
todos: []
---

# Plan: Asociación Masiva de Preguntas a Plantillas

## Objetivo

Crear una interfaz web administrativa que permita asociar preguntas a plantillas de forma masiva mediante filtros, con distribución inteligente que mantenga la consistencia (máximo ~50 preguntas por plantilla).

## Estructura de la Solución

### 1. Nuevo Controlador: `AsociarPreguntasPlantillaController`

**Archivo**: `src/Controller/AsociarPreguntasPlantillaController.php`

- Ruta principal: `/admin/asociar-preguntas-plantilla`
- Acceso: `ROLE_PROFESOR`
- Soporte para preguntas generales y municipales (parámetro `?tipo=general|municipal`)

**Métodos**:

- `index()`: Muestra el formulario de filtros y la lista de preguntas sin plantilla
- `asociar()` (POST): Procesa la asociación masiva con distribución inteligente

### 2. Formulario de Filtros

**Archivo**: `src/Form/AsociarPreguntasPlantillaType.php`

Filtros disponibles:

- **Tema** (Tema o TemaMunicipal según tipo)
- **Dificultad** (facil, moderada, dificil)
- **Rango de IDs** (ID desde - ID hasta)
- **Solo sin plantilla** (checkbox, por defecto activado)
- **Tipo** (general/municipal, hidden field)

### 3. Lógica de Distribución Inteligente

**Algoritmo** (en el método `asociar()`):

1. **Obtener preguntas filtradas** sin plantilla asignada
2. **Agrupar por (tema, dificultad)**
3. **Para cada grupo**:

- Buscar plantillas existentes del mismo tema y dificultad
- Ordenar por número de preguntas (ascendente, para llenar primero las que tienen menos)
- Distribuir preguntas:
  - Llenar plantillas existentes hasta 50 preguntas
  - Si sobran preguntas, crear nuevas plantillas (máximo 50 por plantilla)
  - Nombre automático: `"{Tema} - {Dificultad} - {Número}"` (ej: "Derechos Fundamentales - Fácil - 1")

4. **Validaciones**:

- No exceder 50 preguntas por plantilla
- Solo asociar preguntas que coincidan con el tema/dificultad de la plantilla
- Mostrar resumen de la operación (preguntas asociadas, plantillas usadas/creadas)

### 4. Template Principal

**Archivo**: `templates/asociar_preguntas_plantilla/index.html.twig`

**Secciones**:

- **Formulario de filtros** (arriba)
- **Tabla de preguntas** (con paginación si hay muchas):
- ID, Texto (truncado), Tema, Dificultad, Estado (activo/inactivo)
- Checkbox para selección individual (opcional, para casos específicos)
- **Resumen de selección**: "X preguntas seleccionadas"
- **Botón "Asociar a Plantillas"** que ejecuta la distribución inteligente
- **Resultado de la operación** (flash messages con detalles)

### 5. Métodos en Repositorios

**`src/Repository/PreguntaRepository.php`**:

- `findSinPlantillaPorFiltros($temaId, $dificultad, $idDesde, $idHasta)`: Busca preguntas sin plantilla con filtros

**`src/Repository/PreguntaMunicipalRepository.php`**:

- `findSinPlantillaPorFiltros($temaMunicipalId, $dificultad, $idDesde, $idHasta)`: Similar para preguntas municipales

**`src/Repository/PlantillaRepository.php`**:

- `findPorTemaYDificultad($tema, $dificultad)`: Busca plantillas existentes
- `findUltimaPorTemaYDificultad($tema, $dificultad)`: Para obtener el número de secuencia

**`src/Repository/PlantillaMunicipalRepository.php`**:

- Métodos equivalentes para plantillas municipales

### 6. Servicio de Distribución (Opcional pero recomendado)

**Archivo**: `src/Service/AsociacionPreguntasPlantillaService.php`

Encapsula la lógica de distribución para reutilización:

- `distribuirPreguntas($preguntas, $tipo = 'general'): array`
- Retorna: `['asociadas' => int, 'plantillas_usadas' => [], 'plantillas_creadas' => []]`

## Flujo de Usuario

1. Admin accede a `/admin/asociar-preguntas-plantilla?tipo=general`
2. Selecciona filtros (tema, dificultad, rango de IDs)
3. Ve la lista de preguntas que coinciden con los filtros y no tienen plantilla
4. Hace clic en "Asociar a Plantillas"
5. El sistema:

- Agrupa preguntas por (tema, dificultad)
- Busca/crea plantillas según la estrategia inteligente
- Asocia las preguntas
- Muestra resumen: "50 preguntas asociadas a 2 plantillas existentes, 30 preguntas asociadas a 1 plantilla nueva"

## Consideraciones Técnicas

- **Transacciones**: Usar transacciones de Doctrine para asegurar atomicidad
- **Performance**: Para grandes volúmenes, procesar en lotes (batch processing)
- **Validación**: Verificar que las preguntas seleccionadas realmente no tengan plantilla antes de asociar
- **Flash Messages**: Mostrar mensajes detallados del resultado de la operación
- **Navegación**: Botón para volver a la lista de plantillas

## Archivos a Crear/Modificar

**Nuevos**:

- `src/Controller/AsociarPreguntasPlantillaController.php`
- `src/Form/AsociarPreguntasPlantillaType.php`
- `src/Service/AsociacionPreguntasPlantillaService.php` (opcional pero recomendado)
- `templates/asociar_preguntas_plantilla/index.html.twig`

**Modificar**:

- `src/Repository/PreguntaRepository.php` (añadir método `findSinPlantillaPorFiltros`)
- `src/Repository/PreguntaMunicipalRepository.php` (añadir método `findSinPlantillaPorFiltros`)
- `src/Repository/PlantillaRepository.php` (añadir métodos de búsqueda)
- `src/Repository/PlantillaMunicipalRepository.php` (añadir métodos de búsqueda)
- `templates/plantilla/index.html.twig` (añadir botón/link a la nueva funcionalidad)

## Validaciones y Reglas de Negocio

1. **Máximo 50 preguntas por plantilla**: Si una plantilla tiene 48 preguntas y se intentan asociar 5, solo se asocian 2
2. **Coincidencia tema/dificultad**: Solo se pueden asociar preguntas a plantillas del mismo tema y dificultad
3. **Preguntas ya asociadas**: Ignorar preguntas que ya tienen plantilla (a menos que se permita reasignación)
4. **Nombres de plantillas**: Generar nombres únicos para nuevas plantillas

## Mejoras Futuras (Fuera del alcance inicial)

- Permitir reasignación de preguntas (cambiar de plantilla)
- Vista previa de la distribución antes de confirmar
- Exportar/importar asociaciones
- Estadísticas de distribución
