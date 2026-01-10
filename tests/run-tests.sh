#!/bin/bash

# Script para ejecutar tests de integración
# Uso: ./tests/run-tests.sh [opciones]

set -e

echo "🧪 Ejecutando tests de integración de BISPOL..."
echo ""

# Colores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verificar que estamos en el directorio correcto
if [ ! -f "phpunit.dist.xml" ]; then
    echo -e "${RED}Error: No se encontró phpunit.dist.xml. Ejecuta este script desde la raíz del proyecto.${NC}"
    exit 1
fi

# Verificar que PHPUnit está instalado
if [ ! -f "vendor/bin/phpunit" ] && [ ! -f "bin/phpunit" ]; then
    echo -e "${YELLOW}PHPUnit no encontrado. Instalando dependencias...${NC}"
    composer install
fi

# Configurar base de datos de prueba si no existe
echo -e "${YELLOW}Configurando base de datos de prueba...${NC}"
php bin/console doctrine:database:create --env=test --if-not-exists 2>/dev/null || true
php bin/console doctrine:schema:create --env=test 2>/dev/null || true

# Ejecutar tests
echo -e "${GREEN}Ejecutando tests...${NC}"
echo ""

if [ "$1" = "--coverage" ]; then
    echo "📊 Ejecutando tests con cobertura de código..."
    php bin/phpunit --coverage-html coverage --coverage-text
    echo -e "${GREEN}✓ Reporte de cobertura generado en coverage/index.html${NC}"
elif [ "$1" = "--filter" ]; then
    FILTER="$2"
    echo "🔍 Ejecutando tests con filtro: $FILTER"
    php bin/phpunit --filter "$FILTER"
elif [ "$1" = "--verbose" ]; then
    php bin/phpunit --verbose
elif [ "$1" = "--testdox" ]; then
    php bin/phpunit --testdox
else
    php bin/phpunit "$@"
fi

echo ""
echo -e "${GREEN}✓ Tests completados${NC}"

