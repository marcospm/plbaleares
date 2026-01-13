---
name: Optimización del Login
overview: Plan para mejorar la velocidad del login eliminando consultas duplicadas, implementando eager loading de relaciones críticas, añadiendo caché de sesión y optimizando el dashboard.
todos: []
---

# Plan de Optimización del Login

## Problemas Identificados

1. **Consulta duplicada en autenticación**: `AppCustomAuthenticator` hace `findOneBy` para verificar usuario activo, pero Symfony Security vuelve a cargar el usuario desde BD
2. **Lazy loading de relaciones**: Las relaciones ManyToMany (alumnos, profesores, grupos, convocatorias) causan N+1 queries
3. **Dashboard con múltiples consultas**: Ejecuta muchas consultas COUNT que podrían cachearse
4. **Falta caché de sesión**: El usuario se carga desde BD en cada request
5. **Falta índice explícito**: El campo `username` podría beneficiarse de un índice adicional

## Soluciones Propuestas

### 1. Eliminar consulta duplicada en autenticación

**Archivo**: `src/Security/AppCustomAuthenticator.php`

- Eliminar la consulta `findOneBy` en `authenticate()` (línea 40)
- Mover la validación de usuario activo a un UserProvider personalizado o a `onAuthenticationSuccess`
- Esto elimina 1 consulta SQL innecesaria por login

### 2. Crear UserProvider con Eager Loading

**Archivo**: `src/Security/UserProvider.php` (nuevo)

- Crear un UserProvider personalizado que extienda `EntityUserProvider`
- Implementar `refreshUser()` con eager loading de relaciones críticas (alumnos, profesores, grupos)
- Esto reduce las consultas N+1 cuando se accede a estas relaciones

**Archivo**: `config/packages/security.yaml`

- Configurar el provider para usar el UserProvider personalizado con eager loading

### 3. Optimizar carga de usuario en sesión

**Archivo**: `src/Security/UserProvider.php`

- Implementar caché de usuario en memoria durante la sesión
- Solo refrescar desde BD si han pasado más de X minutos o si se detecta cambio
- Usar `refreshUser()` solo cuando sea necesario

### 4. Optimizar Dashboard con caché

**Archivo**: `src/Controller/DashboardController.php`

- Cachear estadísticas del dashboard (totalAlumnos, totalExamenes, etc.) por 5-10 minutos
- Usar Symfony Cache con clave basada en usuario y rol
- Invalidar caché cuando se crean/modifican exámenes o usuarios
- Agrupar múltiples COUNT en una sola consulta cuando sea posible

**Archivo**: `config/packages/cache.yaml` (verificar configuración)

- Asegurar que hay un pool de caché configurado (cache.app)

### 5. Añadir índices de base de datos

**Archivo**: Crear migración Doctrine

- Añadir índice explícito en `username` (aunque ya hay UniqueConstraint, un índice adicional puede ayudar)
- Verificar que `activo` tiene índice (ya existe según `User.php` línea 14)
- Considerar índice compuesto `(username, activo)` para consultas de login

### 6. Optimizar consulta de verificación de usuario activo

**Archivo**: `src/Security/AppCustomAuthenticator.php`

- Si se mantiene la verificación, usar `findOneBy` con solo campos necesarios (`username` y `activo`)
- O mejor: mover esta validación al UserProvider

### 7. Lazy loading mejorado en Dashboard

**Archivo**: `src/Controller/DashboardController.php`

- Para profesores, ya se hace eager loading de alumnos (líneas 54-60), mantener esto
- Para alumnos, asegurar que las relaciones necesarias se cargan con eager loading
- Revisar `ExamenRepository::findByUsuario()` para asegurar eager loading

## Impacto Esperado

- **Reducción de consultas SQL**: De ~5-10 consultas a 1-2 consultas por login
- **Tiempo de login**: Reducción estimada del 40-60% en tiempo de respuesta
- **Carga del dashboard**: Reducción del 50-70% en tiempo de carga inicial (con caché)

## Orden de Implementación

1. Eliminar consulta duplicada en `AppCustomAuthenticator`
2. Crear UserProvider con eager loading
3. Añadir caché de estadísticas en Dashboard
4. Crear migración para índices adicionales
5. Optimizar consultas restantes

## Notas

- El firewall ya tiene `lazy: true` (línea 20 de security.yaml), lo cual es bueno
- Las relaciones ManyToMany pueden causar problemas de rendimiento si no se manejan correctamente
- El caché debe invalidarse apropiadamente cuando cambian los datos