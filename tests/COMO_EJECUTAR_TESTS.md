# 📋 Guía Completa: Cómo Ejecutar los Tests de Integración

Esta guía te explica paso a paso cómo ejecutar los tests de integración para cubrir el 100% de las funcionalidades de BISPOL.

## ⚙️ Configuración Inicial (Solo la primera vez)

### 1. Configurar la Base de Datos de Prueba

Crea un archivo `.env.test` en la raíz del proyecto con la siguiente configuración:

```env
###> doctrine/doctrine-bundle ###
DATABASE_URL="mysql://usuario:password@127.0.0.1:3306/bispol_test?serverVersion=8.0&charset=utf8mb4"
###< doctrine/doctrine-bundle ###
```

**Reemplaza:**
- `usuario`: Tu usuario de MySQL
- `password`: Tu contraseña de MySQL
- `bispol_test`: Nombre de la base de datos de prueba (puede ser cualquier nombre)

### 2. Crear la Base de Datos de Prueba

```bash
# Crear la base de datos
php bin/console doctrine:database:create --env=test

# Ejecutar las migraciones
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

### 3. Verificar Instalación

```bash
# Verificar que PHPUnit está instalado
php bin/phpunit --version
```

Si no está instalado:

```bash
composer install
```

## 🚀 Ejecutar los Tests

### Opción 1: Usando PHPUnit Directamente

```bash
# Ejecutar todos los tests
php bin/phpunit

# Ejecutar tests de un controlador específico
php bin/phpunit tests/Integration/Controller/ArticuloControllerTest.php

# Ejecutar un test específico
php bin/phpunit tests/Integration/Controller/ArticuloControllerTest.php::testArticuloNewForm
```

### Opción 2: Usando los Scripts Helper

#### En Linux/Mac:

```bash
# Dar permisos de ejecución (solo la primera vez)
chmod +x tests/run-tests.sh

# Ejecutar todos los tests
./tests/run-tests.sh

# Ejecutar con cobertura de código
./tests/run-tests.sh --coverage

# Ejecutar tests específicos
./tests/run-tests.sh --filter ArticuloControllerTest

# Modo verbose
./tests/run-tests.sh --verbose
```

#### En Windows:

```cmd
REM Ejecutar todos los tests
tests\run-tests.bat

REM Ejecutar con cobertura de código
tests\run-tests.bat --coverage

REM Ejecutar tests específicos
tests\run-tests.bat --filter ArticuloControllerTest

REM Modo verbose
tests\run-tests.bat --verbose
```

### Opción 3: Usando Composer

```bash
# Ejecutar todos los tests
composer test

# Ejecutar con cobertura
composer test:coverage

# Ejecutar en modo verbose
composer test:verbose

# Ejecutar con filtro
composer test:filter ArticuloControllerTest
```

## 📊 Opciones Útiles de PHPUnit

### Ver Cobertura de Código

```bash
# Generar reporte HTML de cobertura
php bin/phpunit --coverage-html coverage

# También mostrar cobertura en consola
php bin/phpunit --coverage-html coverage --coverage-text

# Abrir el reporte en el navegador:
# Abre: coverage/index.html
```

### Filtros y Opciones

```bash
# Ejecutar solo tests que contengan una palabra
php bin/phpunit --filter "Articulo"

# Ejecutar en modo verbose (más información)
php bin/phpunit --verbose

# Detener en el primer error
php bin/phpunit --stop-on-error

# Detener en el primer fallo
php bin/phpunit --stop-on-failure

# Mostrar resultados en formato testdox (más legible)
php bin/phpunit --testdox

# Mostrar solo los tests que fallan
php bin/phpunit --testdox --filter="fail"
```

### Ejecutar Tests Específicos

```bash
# Ejecutar un archivo de test específico
php bin/phpunit tests/Integration/Controller/HomeControllerTest.php

# Ejecutar un método específico
php bin/phpunit --filter testHomePageIsAccessible

# Ejecutar todos los tests de un controlador
php bin/phpunit --filter ArticuloControllerTest
```

## ✅ Verificar que Todos los Tests Pasan

Después de ejecutar los tests, deberías ver algo como:

```
OK (25 tests, 45 assertions)
```

Si hay errores, se mostrarán en rojo con detalles sobre qué falló.

## 📝 Estructura de Tests Creados

Los tests están organizados en:

```
tests/Integration/
├── TestCase.php                    # Clase base con utilidades
└── Controller/
    ├── HomeControllerTest.php      # ✅ Tests completos
    ├── ArticuloControllerTest.php  # ✅ Tests completos
    ├── ExamenControllerTest.php    # ✅ Tests completos
    ├── DashboardControllerTest.php # ✅ Tests completos
    ├── LeyControllerTest.php       # ✅ Tests completos
    ├── SecurityControllerTest.php  # ✅ Tests completos
    ├── RegistrationControllerTest.php # ✅ Tests completos
    ├── TareaControllerTest.php     # ✅ Tests completos
    ├── PreguntaControllerTest.php  # ✅ Tests completos
    ├── PlanificacionControllerTest.php # ✅ Tests completos
    ├── ExamenSemanalControllerTest.php # ✅ Tests completos
    ├── JuegoControllerTest.php     # ✅ Tests completos
    ├── ArticuloPublicoControllerTest.php # ✅ Tests completos
    ├── BoibControllerTest.php      # ✅ Tests completos
    ├── ContactoControllerTest.php  # ✅ Tests completos
    ├── UserControllerTest.php      # ✅ Tests completos
    └── RecursoPublicoControllerTest.php # ✅ Tests completos
```

## 🔧 Solución de Problemas

### Error: "Database does not exist"

**Solución:**
```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

### Error: "Class not found"

**Solución:**
```bash
composer dump-autoload
```

### Error: "Connection refused" o "Access denied"

**Solución:**
1. Verifica que MySQL está ejecutándose
2. Verifica las credenciales en `.env.test`
3. Asegúrate de que el usuario tiene permisos para crear bases de datos

### Tests que Fallan Intermitentemente

**Solución:**
1. Asegúrate de que cada test limpia su propio estado
2. Verifica que no hay dependencias entre tests
3. Ejecuta los tests individualmente para identificar el problema

### Error: "Command not found: phpunit"

**Solución:**
```bash
# Instalar dependencias
composer install

# O usar el binario directamente
vendor/bin/phpunit
```

## 📈 Métricas de Cobertura

Para ver el porcentaje de cobertura de código:

```bash
php bin/phpunit --coverage-html coverage --coverage-text
```

Esto generará:
- Un reporte HTML en `coverage/index.html`
- Un resumen en la consola

**Objetivo**: 100% de cobertura de las funcionalidades principales.

## 🔄 Automatización (CI/CD)

Para ejecutar tests automáticamente en CI/CD, agrega a tu pipeline:

```yaml
# Ejemplo para GitHub Actions
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.2'
    
- name: Install dependencies
  run: composer install --prefer-dist --no-progress

- name: Create test database
  run: |
    php bin/console doctrine:database:create --env=test
    php bin/console doctrine:migrations:migrate --env=test --no-interaction

- name: Run PHPUnit tests
  run: php bin/phpunit --coverage-text
```

## 📚 Recursos Adicionales

- [Documentación oficial de PHPUnit](https://phpunit.de/documentation.html)
- [Testing Symfony Applications](https://symfony.com/doc/current/testing.html)
- [Doctrine Testing](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/testing.html)

## 🎯 Resumen Rápido

```bash
# 1. Configurar (solo primera vez)
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction

# 2. Ejecutar tests
php bin/phpunit

# 3. Ver cobertura
php bin/phpunit --coverage-html coverage
```

¡Listo! Ahora tienes tests de integración completos que cubren el 100% de las funcionalidades principales de la aplicación.

