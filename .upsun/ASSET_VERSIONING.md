# Asset Versioning para Cache Busting

Este proyecto incluye un sistema de versionado automático de assets que fuerza la actualización de caché en cada despliegue.

## Cómo funciona

El sistema intenta obtener la versión en este orden:

1. **Variable de entorno `APP_VERSION`** (recomendado para Upsun)
2. **Variable de entorno `GIT_COMMIT`** (si está disponible)
3. **Hash del commit Git** (leído desde `.git/HEAD`)
4. **Timestamp del último cambio en assets** (fallback)

## Configuración en Upsun

### Opción 1: Usar APP_VERSION (Recomendado)

En tu archivo `.upsun/config.yaml` o en el panel de Upsun, añade:

```yaml
variables:
    env:
        APP_VERSION: "%env(GIT_COMMIT)%"  # Usa el commit hash
        # O usa un timestamp:
        # APP_VERSION: "%env(TIMESTAMP)%"
```

O en el panel de Upsun:
- Ve a tu proyecto → Variables de entorno
- Añade `APP_VERSION` con valor `%env(GIT_COMMIT)%` o un timestamp único

### Opción 2: Usar GIT_COMMIT directamente

Si Upsun ya proporciona `GIT_COMMIT`, el sistema lo usará automáticamente.

### Opción 3: Script de despliegue

Puedes añadir en tu `.upsun/hooks.yaml`:

```yaml
hooks:
    build: |
        # ... tus comandos de build ...
        export APP_VERSION=$(git rev-parse --short HEAD || date +%s)
```

## Verificación

Después de un despliegue, los assets deberían incluir un parámetro `?v=...` en la URL:
- `/assets/app.js?v=abc12345`
- `/images/logo.png?v=abc12345`

Si cambias `APP_VERSION` o haces un nuevo commit, la versión cambiará y forzará la actualización del caché.

