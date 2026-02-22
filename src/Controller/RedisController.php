<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RedisController extends AbstractController
{
    public function __construct(
        private CacheInterface $redisCache,
        private CacheItemPoolInterface $redisPool
    ) {
    }

    #[Route('/redis/hello', name: 'app_redis_hello', methods: ['GET'])]
    public function hello(): JsonResponse
    {
        try {
            // Configurar timeout para evitar bloqueos largos
            set_time_limit(5); // Máximo 5 segundos para esta operación
            
            // Detectar si estamos usando Redis real o filesystem (fallback)
            $isRedis = $this->isUsingRedis();
            
            // Ejemplo básico: guardar y recuperar un valor
            $key = 'hello_world';
            $value = 'Hello from Redis! ' . date('Y-m-d H:i:s');
            
            // Guardar en Redis con TTL de 60 segundos
            $this->redisCache->get($key, function (ItemInterface $item) use ($value) {
                $item->expiresAfter(60);
                return $value;
            });
            
            // Recuperar el valor
            $cachedValue = $this->redisCache->get($key, function (ItemInterface $item) {
                return null;
            });
            
            return $this->json([
                'status' => 'success',
                'message' => $isRedis 
                    ? 'Redis está funcionando correctamente!' 
                    : 'Cache funcionando (usando filesystem como fallback - Redis no disponible en local)',
                'using_redis' => $isRedis,
                'data' => [
                    'key' => $key,
                    'value' => $cachedValue,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Symfony\Component\Cache\Exception\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Error de configuración de Redis: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'hint' => 'Verifica que Redis esté corriendo y accesible',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Error al conectar con Redis: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'hint' => 'Asegúrate de que Redis esté corriendo (redis-server) o desactiva Redis temporalmente',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/redis/test', name: 'app_redis_test', methods: ['GET', 'POST'])]
    public function test(Request $request): JsonResponse
    {
        try {
            // Configurar timeout para evitar bloqueos largos
            set_time_limit(5); // Máximo 5 segundos para esta operación
            
            $action = $request->query->get('action', 'get');
            $key = $request->query->get('key', 'test_key');
            $value = $request->query->get('value', 'test_value');
            
            $result = [];
            
            switch ($action) {
                case 'set':
                    // Guardar un valor
                    $this->redisCache->get($key, function (ItemInterface $item) use ($value) {
                        $item->expiresAfter(300); // 5 minutos
                        return $value;
                    });
                    $result = [
                        'action' => 'set',
                        'key' => $key,
                        'value' => $value,
                        'message' => 'Valor guardado correctamente',
                    ];
                    break;
                    
                case 'get':
                    // Recuperar un valor
                    $cachedValue = $this->redisCache->get($key, function (ItemInterface $item) {
                        return null;
                    });
                    $result = [
                        'action' => 'get',
                        'key' => $key,
                        'value' => $cachedValue,
                        'found' => $cachedValue !== null,
                    ];
                    break;
                    
                case 'delete':
                    // Eliminar un valor usando el pool
                    $this->redisPool->deleteItem($key);
                    $result = [
                        'action' => 'delete',
                        'key' => $key,
                        'message' => 'Clave eliminada (si existía)',
                    ];
                    break;
                    
                case 'info':
                    // Información sobre Redis - Test completo de lectura/escritura
                    $testKey = 'redis_info_test_' . time();
                    $testValue = 'test_value_' . time();
                    
                    // Primero eliminar la clave si existe (por si acaso)
                    try {
                        $this->redisPool->deleteItem($testKey);
                    } catch (\Exception $e) {
                        // Ignorar errores al eliminar
                    }
                    
                    // Guardar el valor usando get() con callback
                    $saved = $this->redisCache->get($testKey, function (ItemInterface $item) use ($testValue) {
                        $item->expiresAfter(10);
                        return $testValue;
                    });
                    
                    // Pequeña pausa para asegurar que se guardó
                    usleep(10000); // 10ms
                    
                    // Recuperar el valor inmediatamente
                    // Si el valor existe, el callback no se ejecuta y devuelve el valor en caché
                    $retrieved = $this->redisCache->get($testKey, function (ItemInterface $item) {
                        // Este callback solo se ejecuta si el valor NO existe en caché
                        return 'NOT_FOUND';
                    });
                    
                    // Verificar que ambos valores sean iguales al esperado
                    $savedOk = ($saved === $testValue);
                    $retrievedOk = ($retrieved === $testValue);
                    $testPassed = $savedOk && $retrievedOk;
                    
                    // Limpiar la clave de prueba
                    try {
                        $this->redisPool->deleteItem($testKey);
                    } catch (\Exception $e) {
                        // Ignorar errores al eliminar
                    }
                    
                    $result = [
                        'action' => 'info',
                        'status' => 'connected',
                        'test' => $testPassed ? 'passed' : 'failed',
                        'saved_ok' => $savedOk,
                        'retrieved_ok' => $retrievedOk,
                        'saved_value' => $saved,
                        'retrieved_value' => $retrieved,
                        'expected_value' => $testValue,
                        'saved_type' => gettype($saved),
                        'retrieved_type' => gettype($retrieved),
                        'expected_type' => gettype($testValue),
                        'message' => $testPassed 
                            ? 'Redis está conectado y funcionando correctamente' 
                            : 'Redis está conectado pero el test de lectura/escritura falló. Verifica los valores arriba.',
                    ];
                    break;
                    
                default:
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Acción no válida. Usa: set, get, delete o info',
                    ], Response::HTTP_BAD_REQUEST);
            }
            
            return $this->json([
                'status' => 'success',
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            
        } catch (\Symfony\Component\Cache\Exception\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Error de configuración de Redis: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'hint' => 'Verifica que Redis esté corriendo y accesible',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $isTimeout = strpos($errorMessage, 'timeout') !== false || 
                         strpos($errorMessage, 'Maximum execution time') !== false ||
                         strpos($errorMessage, 'Connection refused') !== false;
            
            return $this->json([
                'status' => 'error',
                'message' => 'Error: ' . $errorMessage,
                'error_type' => $isTimeout ? 'timeout' : 'other',
                'hint' => $isTimeout 
                    ? 'Redis no está disponible o no responde. Asegúrate de que Redis esté corriendo (redis-server) o desactiva Redis temporalmente en cache.yaml'
                    : 'Error desconocido. Verifica la configuración de Redis.',
                'trace' => $this->getParameter('kernel.debug') ? $e->getTraceAsString() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/redis', name: 'app_redis_admin', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(Request $request): Response
    {
        $action = $request->request->get('action', $request->query->get('action'));
        $result = null;
        
        // Procesar acciones
        if ($action === 'clear') {
            try {
                $this->redisPool->clear();
                $result = [
                    'success' => true,
                    'message' => 'Caché limpiado correctamente',
                ];
            } catch (\Exception $e) {
                $result = [
                    'success' => false,
                    'message' => 'Error al limpiar caché: ' . $e->getMessage(),
                ];
            }
        }
        
        // Obtener estadísticas
        $stats = $this->getCacheStats();
        
        // Si es petición AJAX, devolver JSON
        if ($request->isXmlHttpRequest() || $request->query->get('format') === 'json') {
            return $this->json([
                'status' => 'success',
                'stats' => $stats,
                'action_result' => $result,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }
        
        // Renderizar vista HTML
        return $this->render('redis/admin.html.twig', [
            'stats' => $stats,
            'action_result' => $result,
            'using_redis' => $this->isUsingRedis(),
        ]);
    }
    
    /**
     * Obtiene estadísticas del caché
     */
    private function getCacheStats(): array
    {
        $stats = [
            'using_redis' => $this->isUsingRedis(),
            'adapter_class' => get_class($this->redisPool),
            'memory' => null,
            'keys_count' => null,
            'info' => null,
            'sample_keys' => [],
            'cache_pools' => [],
        ];
        
        // Obtener información de todos los pools de caché
        $cachePools = [
            'cache.redis' => 'Caché Principal (Redis)',
            'cache.partidas' => 'Caché de Partidas',
            'cache.boe' => 'Caché de BOE',
            'cache.queries' => 'Caché de Consultas',
        ];
        
        foreach ($cachePools as $poolName => $poolLabel) {
            try {
                if ($this->container->has($poolName)) {
                    $pool = $this->container->get($poolName);
                    $poolStats = [
                        'name' => $poolName,
                        'label' => $poolLabel,
                        'adapter_class' => get_class($pool),
                        'is_redis' => $this->isPoolUsingRedis($pool),
                    ];
                    
                    // Si es Redis, intentar obtener estadísticas
                    if ($poolStats['is_redis']) {
                        try {
                            $redisClient = $this->getRedisClientFromPool($pool);
                            if ($redisClient) {
                                // Contar claves que empiezan con el prefijo del pool
                                try {
                                    $keys = $redisClient->keys('*');
                                    $poolStats['keys_count'] = count($keys);
                                } catch (\Exception $e) {
                                    $poolStats['keys_count'] = 'N/A';
                                }
                            }
                        } catch (\Exception $e) {
                            $poolStats['error'] = $e->getMessage();
                        }
                    }
                    
                    $stats['cache_pools'][] = $poolStats;
                }
            } catch (\Exception $e) {
                // Pool no disponible
            }
        }
        
        if ($this->isUsingRedis()) {
            try {
                // Intentar obtener el cliente Redis directamente
                $redisClient = $this->getRedisClient();
                
                if ($redisClient) {
                    // Obtener información de Redis
                    $info = $redisClient->info();
                    $stats['info'] = $info;
                    
                    // Memoria usada
                    if (isset($info['memory']['used_memory_human'])) {
                        $stats['memory'] = $info['memory']['used_memory_human'];
                    } elseif (isset($info['used_memory_human'])) {
                        $stats['memory'] = $info['used_memory_human'];
                    }
                    
                    // Contar claves (aproximado, puede ser lento en producción)
                    try {
                        $dbSize = $redisClient->dbSize();
                        $stats['keys_count'] = $dbSize;
                    } catch (\Exception $e) {
                        $stats['keys_count'] = 'N/A (error: ' . $e->getMessage() . ')';
                    }
                    
                    // Obtener algunas claves de ejemplo (limitado a 20 para no sobrecargar)
                    try {
                        $keys = $redisClient->keys('*');
                        $sampleKeys = array_slice($keys, 0, 20);
                        $stats['sample_keys'] = $sampleKeys;
                        $stats['total_keys_estimated'] = count($keys);
                    } catch (\Exception $e) {
                        $stats['sample_keys'] = [];
                        $stats['keys_error'] = $e->getMessage();
                    }
                }
            } catch (\Exception $e) {
                $stats['error'] = $e->getMessage();
            }
        }
        
        return $stats;
    }
    
    /**
     * Detecta si un pool específico está usando Redis
     */
    private function isPoolUsingRedis($pool): bool
    {
        try {
            $reflection = new \ReflectionClass($pool);
            $className = $reflection->getName();
            return strpos($className, 'Redis') !== false || 
                   strpos($className, 'Predis') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtiene el cliente Redis desde un pool específico
     */
    private function getRedisClientFromPool($pool)
    {
        try {
            $reflection = new \ReflectionClass($pool);
            
            if (method_exists($pool, 'getConnection')) {
                return $pool->getConnection();
            }
            
            $properties = ['redis', 'client', 'connection'];
            foreach ($properties as $prop) {
                if ($reflection->hasProperty($prop)) {
                    $property = $reflection->getProperty($prop);
                    $property->setAccessible(true);
                    $client = $property->getValue($pool);
                    if ($client && (is_object($client) && method_exists($client, 'info'))) {
                        return $client;
                    }
                }
            }
        } catch (\Exception $e) {
            // No se pudo obtener el cliente
        }
        
        return null;
    }
    
    /**
     * Obtiene el cliente Redis directamente desde el adaptador
     */
    private function getRedisClient()
    {
        try {
            // Intentar obtener el cliente Redis usando reflexión
            $reflection = new \ReflectionClass($this->redisPool);
            
            // Para RedisAdapter de Symfony, el cliente está en una propiedad privada
            if (method_exists($this->redisPool, 'getConnection')) {
                return $this->redisPool->getConnection();
            }
            
            // Intentar acceder a la propiedad 'redis' o 'client'
            $properties = ['redis', 'client', 'connection'];
            foreach ($properties as $prop) {
                if ($reflection->hasProperty($prop)) {
                    $property = $reflection->getProperty($prop);
                    $property->setAccessible(true);
                    $client = $property->getValue($this->redisPool);
                    if ($client && (is_object($client) && method_exists($client, 'info'))) {
                        return $client;
                    }
                }
            }
            
            // Si es Predis, intentar obtener el cliente
            if (strpos(get_class($this->redisPool), 'Predis') !== false) {
                // Para Predis, el cliente puede estar en diferentes lugares
                foreach ($properties as $prop) {
                    try {
                        $property = $reflection->getProperty($prop);
                        $property->setAccessible(true);
                        $client = $property->getValue($this->redisPool);
                        if ($client) {
                            return $client;
                        }
                    } catch (\ReflectionException $e) {
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            // No se pudo obtener el cliente
        }
        
        return null;
    }
    
    /**
     * Detecta si el pool está usando Redis real o filesystem como fallback
     */
    private function isUsingRedis(): bool
    {
        try {
            // Intentar obtener información del pool
            // Si es Redis, el pool será una instancia de RedisAdapter
            $reflection = new \ReflectionClass($this->redisPool);
            $className = $reflection->getName();
            
            // Verificar si es un adaptador de Redis
            return strpos($className, 'Redis') !== false || 
                   strpos($className, 'Predis') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
