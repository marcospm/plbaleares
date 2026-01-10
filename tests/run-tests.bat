@echo off
REM Script para ejecutar tests de integración en Windows
REM Uso: tests\run-tests.bat [opciones]

echo Ejecutando tests de integracion de BISPOL...
echo.

REM Verificar que estamos en el directorio correcto
if not exist "phpunit.dist.xml" (
    echo Error: No se encontro phpunit.dist.xml. Ejecuta este script desde la raiz del proyecto.
    exit /b 1
)

REM Verificar que PHPUnit esta instalado
if not exist "vendor\bin\phpunit" if not exist "bin\phpunit" (
    echo PHPUnit no encontrado. Instalando dependencias...
    composer install
)

REM Configurar base de datos de prueba
echo Configurando base de datos de prueba...
php bin\console doctrine:database:create --env=test --if-not-exists 2>nul
php bin\console doctrine:schema:create --env=test 2>nul

REM Ejecutar tests
echo Ejecutando tests...
echo.

if "%1"=="--coverage" (
    echo Ejecutando tests con cobertura de codigo...
    php bin\phpunit --coverage-html coverage --coverage-text
    echo Reporte de cobertura generado en coverage\index.html
) else if "%1"=="--filter" (
    echo Ejecutando tests con filtro: %2
    php bin\phpunit --filter "%2"
) else if "%1"=="--verbose" (
    php bin\phpunit --verbose
) else if "%1"=="--testdox" (
    php bin\phpunit --testdox
) else (
    php bin\phpunit %*
)

echo.
echo Tests completados

