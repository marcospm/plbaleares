<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
