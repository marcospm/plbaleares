# ⚡ Inicio Rápido - Tests de Integración

## Configuración Rápida (5 minutos)

### 1. Configurar Base de Datos de Prueba

Crea el archivo `.env.test`:

```env
DATABASE_URL="mysql://root:tu_password@127.0.0.1:3306/bispol_test?serverVersion=8.0&charset=utf8mb4"
```

### 2. Crear Base de Datos

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

### 3. Ejecutar Tests

```bash
# Todos los tests
php bin/phpunit

# Con cobertura de código
php bin/phpunit --coverage-html coverage

# Abrir reporte de cobertura
# Abre: coverage/index.html en tu navegador
```

## Comandos Útiles

```bash
# Ejecutar un test específico
php bin/phpunit tests/Integration/Controller/ArticuloControllerTest.php

# Ejecutar con filtro
php bin/phpunit --filter ArticuloControllerTest

# Modo verbose (más información)
php bin/phpunit --verbose

# Ver solo los que pasan
php bin/phpunit --testdox
```

## Estructura de Tests

```
tests/Integration/Controller/
├── HomeControllerTest.php           ✅ Página principal
├── SecurityControllerTest.php       ✅ Login/Logout
├── RegistrationControllerTest.php   ✅ Registro
├── ArticuloControllerTest.php       ✅ CRUD Artículos
├── LeyControllerTest.php            ✅ CRUD Leyes
├── ExamenControllerTest.php         ✅ Exámenes
├── DashboardControllerTest.php      ✅ Dashboard
├── TareaControllerTest.php          ✅ Tareas
├── PreguntaControllerTest.php       ✅ Preguntas
├── PlanificacionControllerTest.php  ✅ Planificaciones
├── ExamenSemanalControllerTest.php  ✅ Exámenes semanales
├── JuegoControllerTest.php          ✅ Juegos
├── ArticuloPublicoControllerTest.php ✅ Artículos públicos
├── RecursoPublicoControllerTest.php ✅ Recursos
├── BoibControllerTest.php           ✅ BOIB
├── ContactoControllerTest.php       ✅ Contacto
└── UserControllerTest.php           ✅ Usuarios (admin)
```

## Solución de Problemas Rápida

**Error: "Database does not exist"**
```bash
php bin/console doctrine:database:create --env=test
```

**Error: "Class not found"**
```bash
composer dump-autoload
```

**No se ejecutan los tests**
```bash
composer install
```

## Para Más Información

Ver `tests/README.md` y `tests/COMO_EJECUTAR_TESTS.md` para documentación completa.

