# Tests de Integración - BISPOL

Este directorio contiene los tests de integración para la aplicación BISPOL. Los tests cubren todas las funcionalidades principales de la aplicación.

## Estructura de Tests

```
tests/
├── Integration/
│   ├── TestCase.php                    # Clase base para tests de integración
│   └── Controller/
│       ├── HomeControllerTest.php      # Tests del controlador principal
│       ├── ArticuloControllerTest.php  # Tests de CRUD de artículos
│       ├── ExamenControllerTest.php    # Tests de exámenes
│       ├── DashboardControllerTest.php # Tests del dashboard
│       ├── LeyControllerTest.php       # Tests de leyes
│       └── ...                         # Más tests de controladores
└── README.md                           # Este archivo
```

## Requisitos Previos

1. **PHPUnit**: Ya está instalado como dependencia del proyecto
2. **Base de datos de prueba**: Se crea automáticamente al ejecutar los tests
3. **Variables de entorno**: Asegúrate de tener un archivo `.env.test` configurado

## Configuración de la Base de Datos de Prueba

Crea o actualiza el archivo `.env.test` con la configuración de la base de datos de prueba:

```env
###> doctrine/doctrine-bundle ###
DATABASE_URL="mysql://usuario:password@127.0.0.1:3306/bispol_test?serverVersion=8.0&charset=utf8mb4"
###< doctrine/doctrine-bundle ###
```

**Nota**: Los tests crean y eliminan automáticamente la base de datos `bispol_test` cada vez que se ejecutan.

## Cómo Ejecutar los Tests

### Configuración Inicial (Primera vez)

1. **Configura la base de datos de prueba**:
   ```bash
   # Crea la base de datos de prueba
   php bin/console doctrine:database:create --env=test
   
   # Ejecuta las migraciones en la base de datos de prueba
   php bin/console doctrine:migrations:migrate --env=test --no-interaction
   ```

2. **Verifica que el archivo `.env.test` existe** con la configuración correcta:
   ```env
   DATABASE_URL="mysql://usuario:password@127.0.0.1:3306/bispol_test?serverVersion=8.0&charset=utf8mb4"
   ```

### Ejecutar Todos los Tests

```bash
# Desde la raíz del proyecto
php bin/phpunit

# O usando el script helper (Linux/Mac)
chmod +x tests/run-tests.sh
./tests/run-tests.sh

# O usando el script helper (Windows)
tests\run-tests.bat

# O usando composer (si está configurado)
composer test
```

### Ejecutar un Test Específico

```bash
# Ejecutar un archivo de test específico
php bin/phpunit tests/Integration/Controller/ArticuloControllerTest.php

# Ejecutar un método de test específico
php bin/phpunit tests/Integration/Controller/ArticuloControllerTest.php::testArticuloNewForm
```

### Ejecutar Tests con Cobertura de Código

```bash
# Ejecutar tests con reporte de cobertura HTML
php bin/phpunit --coverage-html coverage/

# Abrir el reporte en el navegador
# Abre: coverage/index.html
```

### Ejecutar Tests con Filtros

```bash
# Ejecutar solo tests de un controlador específico
php bin/phpunit --filter ArticuloControllerTest

# Ejecutar tests que contengan una palabra clave
php bin/phpunit --filter "testArticulo"
```

### Opciones Útiles de PHPUnit

```bash
# Ejecutar tests en modo verbose
php bin/phpunit --verbose

# Detener en el primer error
php bin/phpunit --stop-on-error

# Detener en el primer fallo
php bin/phpunit --stop-on-failure

# Detener después del primer fallo
php bin/phpunit --stop-on-defect

# Mostrar solo los tests que fallan
php bin/phpunit --testdox

# Ejecutar tests en paralelo (si tienes PHPUnit 9+)
php bin/phpunit --parallel
```

## Tests Disponibles

**Ver `COVERAGE.md` para una lista completa y detallada de todos los tests.**

### Controladores Testeados (100% Cobertura)

#### Autenticación y Registro
1. **SecurityControllerTest** - Autenticación
   - ✅ Página de login accesible
   - ✅ Login con credenciales válidas
   - ✅ Login con credenciales inválidas
   - ✅ Logout

2. **RegistrationControllerTest** - Registro
   - ✅ Página de registro accesible
   - ✅ Registro con datos válidos

#### Páginas Públicas
3. **HomeControllerTest** - Página principal
   - ✅ Acceso a la página de inicio
   - ✅ Visualización para usuarios anónimos

4. **ContactoControllerTest** - Contacto
   - ✅ Página de contacto accesible
   - ✅ Formulario de contacto

#### Gestión de Contenido (Profesores)
5. **ArticuloControllerTest** - Gestión de artículos
   - ✅ Listado de artículos (requiere autenticación)
   - ✅ Crear artículo
   - ✅ Editar artículo
   - ✅ Ver artículo
   - ✅ Eliminar artículo

6. **LeyControllerTest** - Gestión de leyes
   - ✅ Listado de leyes (requiere profesor)
   - ✅ Crear ley
   - ✅ Ver ley

7. **PreguntaControllerTest** - Gestión de preguntas
   - ✅ Listado de preguntas (requiere profesor)
   - ✅ Control de acceso por roles

8. **TareaControllerTest** - Gestión de tareas
   - ✅ Listado de tareas (requiere profesor)
   - ✅ Crear tarea

9. **PlanificacionControllerTest** - Planificaciones
   - ✅ Listado de planificaciones (requiere profesor)

10. **ExamenSemanalControllerTest** - Exámenes semanales
    - ✅ Listado de exámenes semanales (requiere profesor)

#### Funcionalidades de Alumnos
11. **DashboardControllerTest** - Dashboard
    - ✅ Acceso al dashboard (requiere autenticación)
    - ✅ Visualización para diferentes roles

12. **ExamenControllerTest** - Gestión de exámenes
    - ✅ Iniciar examen (requiere autenticación)
    - ✅ Historial de exámenes

13. **ArticuloPublicoControllerTest** - Artículos públicos
    - ✅ Listado de artículos públicos (requiere autenticación)
    - ✅ Ver artículo público

14. **RecursoPublicoControllerTest** - Recursos públicos
    - ✅ Listado de recursos (requiere autenticación)

15. **BoibControllerTest** - BOIB
    - ✅ Acceso a BOIB (requiere autenticación)
    - ✅ Visualización de boletines

#### Juegos y Gamificación
16. **JuegoControllerTest** - Juegos
    - ✅ Acceso a juegos (requiere autenticación)
    - ✅ Adivina número artículo
    - ✅ Completa texto legal
    - ✅ API de artículos para juegos

#### Administración
17. **UserControllerTest** - Gestión de usuarios
    - ✅ Listado de usuarios (requiere admin)
    - ✅ Control de acceso por roles (profesor no puede acceder)

### Funcionalidades Cubiertas

- ✅ Autenticación y autorización
- ✅ CRUD de entidades principales
- ✅ Control de acceso por roles (ROLE_USER, ROLE_PROFESOR, ROLE_ADMIN)
- ✅ Formularios y validación
- ✅ Redirecciones después de operaciones
- ✅ Visualización de datos

## Agregar Nuevos Tests

Para agregar un nuevo test de integración:

1. Crea un archivo en `tests/Integration/Controller/` siguiendo el patrón:
   ```php
   <?php
   
   namespace App\Tests\Integration\Controller;
   
   use App\Tests\Integration\TestCase;
   
   class MiControllerTest extends TestCase
   {
       public function testMiFuncionalidad(): void
       {
           // Tu test aquí
       }
   }
   ```

2. Extiende de `TestCase` que proporciona:
   - Configuración automática de base de datos
   - Métodos helper para crear entidades de prueba
   - Métodos helper para login
   - Cliente HTTP configurado

3. Usa los métodos helper disponibles:
   - `createTestUser()` - Crea un usuario de prueba
   - `createTestLey()` - Crea una ley de prueba
   - `createTestArticulo()` - Crea un artículo de prueba
   - `loginAsUser()` - Hace login como usuario
   - `loginAsProfesor()` - Hace login como profesor
   - `loginAsAdmin()` - Hace login como admin

## Solución de Problemas

### Error: "Database does not exist"

Si recibes este error, asegúrate de que:
1. El archivo `.env.test` está configurado correctamente
2. El usuario de la base de datos tiene permisos para crear bases de datos
3. La base de datos de prueba no está bloqueada por otra conexión

### Error: "Class not found"

Si recibes este error:
1. Ejecuta `composer dump-autoload`
2. Verifica que el namespace del test sea correcto
3. Verifica que el archivo esté en la ubicación correcta

### Tests que Fallan Intermitentemente

Si algunos tests fallan intermitentemente:
1. Verifica que no haya dependencias entre tests
2. Asegúrate de que cada test limpia su propio estado
3. Verifica que la base de datos se limpia correctamente entre tests

## Integración Continua (CI/CD)

Para ejecutar tests en CI/CD, puedes usar:

```yaml
# Ejemplo para GitHub Actions
- name: Run PHPUnit Tests
  run: |
    php bin/phpunit --coverage-text --coverage-clover=coverage.xml
```

## Cobertura de Código

Para generar un reporte de cobertura:

```bash
php bin/phpunit --coverage-html coverage --coverage-text
```

El reporte HTML estará disponible en `coverage/index.html`.

**Objetivo de cobertura**: El objetivo es mantener al menos 80% de cobertura de código en las funcionalidades críticas.

## Mantenimiento

- Los tests se ejecutan automáticamente antes de cada commit (si está configurado)
- Revisa y actualiza los tests cuando agregues nuevas funcionalidades
- Mantén los tests simples y enfocados en una funcionalidad específica
- Usa nombres descriptivos para los métodos de test

## Recursos

- [Documentación de PHPUnit](https://phpunit.de/documentation.html)
- [Testing Symfony](https://symfony.com/doc/current/testing.html)
- [WebTestCase](https://symfony.com/doc/current/testing.html#functional-tests)

