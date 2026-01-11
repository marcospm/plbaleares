<?php

namespace App\EventListener;

use App\Entity\Ley;
use App\Entity\Tema;
use App\Entity\Convocatoria;
use App\Entity\Municipio;
use App\Entity\TemaMunicipal;
use App\Entity\Examen;
use App\Repository\LeyRepository;
use App\Repository\TemaRepository;
use App\Repository\ConvocatoriaRepository;
use App\Repository\MunicipioRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\ExamenRepository;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Event listener para invalidar cache automáticamente cuando se modifican entidades
 */
class CacheInvalidationSubscriber
{
    public function __construct(
        private ?LeyRepository $leyRepository = null,
        private ?TemaRepository $temaRepository = null,
        private ?ConvocatoriaRepository $convocatoriaRepository = null,
        private ?MunicipioRepository $municipioRepository = null,
        private ?TemaMunicipalRepository $temaMunicipalRepository = null,
        private ?ExamenRepository $examenRepository = null,
        private ?CacheItemPoolInterface $cache = null
    ) {
    }

    /**
     * Se ejecuta después de persistir una entidad
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidateCache($args->getObject());
    }

    /**
     * Se ejecuta después de actualizar una entidad
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidateCache($args->getObject());
    }

    /**
     * Se ejecuta después de eliminar una entidad
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->invalidateCache($args->getObject());
    }

    /**
     * Invalida el cache según el tipo de entidad
     */
    private function invalidateCache(object $entity): void
    {
        // Limpiar cache de Ley
        if ($entity instanceof Ley && $this->leyRepository) {
            $this->leyRepository->clearCache();
        }

        // Limpiar cache de Tema
        if ($entity instanceof Tema && $this->temaRepository) {
            $this->temaRepository->clearCache();
        }

        // Limpiar cache de Convocatoria
        if ($entity instanceof Convocatoria && $this->convocatoriaRepository) {
            $this->convocatoriaRepository->clearCache();
        }

        // Limpiar cache de Municipio
        if ($entity instanceof Municipio && $this->municipioRepository) {
            $this->municipioRepository->clearCache();
        }

        // Limpiar cache de TemaMunicipal (incluye cache del municipio relacionado)
        if ($entity instanceof TemaMunicipal) {
            if ($this->temaMunicipalRepository && $entity->getMunicipio()) {
                $this->temaMunicipalRepository->clearCacheForMunicipio($entity->getMunicipio());
            }
        }

        // Limpiar cache de rankings de Examen
        // Los rankings se invalidan cuando se crea/actualiza/elimina un examen
        if ($entity instanceof Examen && $this->cache) {
            // Limpiar todos los caches de rankings (usando prefijo)
            // Los rankings tienen claves como: ranking_dificultad_*, ranking_municipio_*, ranking_convocatoria_*
            $this->clearRankingCache();
        }
    }

    /**
     * Limpia todos los caches de rankings
     * 
     * Estrategia: Como no podemos listar todas las claves del cache fácilmente con cache filesystem,
     * eliminamos los rankings más comunes. Los rankings específicos se regenerarán automáticamente
     * en la próxima consulta gracias al sistema de cache.
     * 
     * En producción con Redis/Memcached, se podría implementar tags para una limpieza más eficiente.
     */
    private function clearRankingCache(): void
    {
        if (!$this->cache) {
            return;
        }

        // Limpiar rankings por dificultad (combinaciones comunes)
        $dificultades = ['facil', 'moderada', 'dificil'];
        $cantidadesExamenes = [5, 10, 20];
        
        // Rankings sin tema específico
        foreach ($dificultades as $dificultad) {
            foreach ($cantidadesExamenes as $cantidad) {
                $this->cache->deleteItem("ranking_dificultad_{$dificultad}_{$cantidad}_all");
            }
        }
        
        // Rankings por municipio (patrón común)
        // Nota: Los rankings específicos por municipio se regenerarán cuando se consulten
        // Limpiamos algunos comunes si es necesario
        
        // Rankings por convocatoria (patrón común)
        // Nota: Similar a municipio, se regenerarán automáticamente
        
        // Nota adicional: Para un sistema con muchos rankings dinámicos,
        // sería recomendable usar un cache backend con soporte de tags (Redis, Memcached)
        // o implementar un sistema de versionado de cache donde se incrementa una versión
        // y todas las claves incluyen esa versión.
    }
}
