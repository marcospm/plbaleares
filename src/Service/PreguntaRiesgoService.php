<?php

namespace App\Service;

use App\Entity\PreguntaRiesgo;
use App\Entity\User;
use App\Repository\PreguntaRiesgoRepository;
use Doctrine\ORM\EntityManagerInterface;

class PreguntaRiesgoService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PreguntaRiesgoRepository $preguntaRiesgoRepository
    ) {
    }

    /**
     * Persiste o actualiza preguntas de riesgo en batch
     * 
     * @param User $usuario
     * @param array $preguntasRiesgo Array con ['pregunta_id' => int|null, 'pregunta_municipal_id' => int|null, 'acertada' => bool]
     * @param bool $esMunicipal
     */
    public function persistirPreguntasRiesgo(User $usuario, array $preguntasRiesgo, bool $esMunicipal = false): void
    {
        if (empty($preguntasRiesgo)) {
            return;
        }

        $batchSize = 50;
        $count = 0;
        $usuarioId = $usuario->getId();

        foreach ($preguntasRiesgo as $preguntaId => $datos) {
            // Asegurar que el ID sea un entero
            $preguntaId = (int) $preguntaId;
            $acertada = $datos['acertada'] ?? false;
            
            // Recargar usuario si es necesario (después de un clear)
            if (!$this->entityManager->contains($usuario)) {
                $usuario = $this->entityManager->getRepository(\App\Entity\User::class)->find($usuarioId);
                if (!$usuario) {
                    continue; // Si no se puede cargar el usuario, saltar esta iteración
                }
            }
            
            if ($esMunicipal) {
                $preguntaRiesgo = $this->preguntaRiesgoRepository->findByUsuarioYPreguntaMunicipal($usuario, $preguntaId);
            } else {
                $preguntaRiesgo = $this->preguntaRiesgoRepository->findByUsuarioYPregunta($usuario, $preguntaId);
            }

            if ($preguntaRiesgo) {
                // Actualizar registro existente
                $preguntaRiesgo->setAcertada($acertada);
                $preguntaRiesgo->setFechaActualizacion(new \DateTime());
            } else {
                // Crear nuevo registro
                $preguntaRiesgo = new PreguntaRiesgo();
                $preguntaRiesgo->setUsuario($usuario);
                $preguntaRiesgo->setAcertada($acertada);
                
                if ($esMunicipal) {
                    $preguntaMunicipal = $this->entityManager->getRepository(\App\Entity\PreguntaMunicipal::class)->find($preguntaId);
                    if ($preguntaMunicipal) {
                        $preguntaRiesgo->setPreguntaMunicipal($preguntaMunicipal);
                        $preguntaRiesgo->setPregunta(null);
                    } else {
                        continue; // Si no se encuentra la pregunta, saltar
                    }
                } else {
                    $pregunta = $this->entityManager->getRepository(\App\Entity\Pregunta::class)->find($preguntaId);
                    if ($pregunta) {
                        $preguntaRiesgo->setPregunta($pregunta);
                        $preguntaRiesgo->setPreguntaMunicipal(null);
                    } else {
                        continue; // Si no se encuentra la pregunta, saltar
                    }
                }
            }

            $this->entityManager->persist($preguntaRiesgo);

            if (++$count % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(); // Limpiar para evitar problemas de memoria
            }
        }

        // Flush final para los registros restantes
        $this->entityManager->flush();
    }

    /**
     * Calcula el porcentaje de acierto por tema
     * 
     * @param User $usuario
     * @param array $temaIds IDs de temas a filtrar (opcional)
     * @return array Array con ['tema_id' => porcentaje] donde porcentaje es 0-100
     */
    public function calcularPorcentajesPorTema(User $usuario, array $temaIds = []): array
    {
        $estadisticas = $this->preguntaRiesgoRepository->findEstadisticasPorTema($usuario, $temaIds);
        
        $porcentajes = [];
        foreach ($estadisticas as $temaId => $stats) {
            $total = $stats['total'];
            $acertadas = $stats['acertadas'];
            
            if ($total > 0) {
                $porcentaje = ($acertadas / $total) * 100;
                $porcentajes[$temaId] = round($porcentaje, 2);
            } else {
                $porcentajes[$temaId] = 0.0;
            }
        }

        return $porcentajes;
    }

    /**
     * Calcula el porcentaje de acierto por tema municipal
     * 
     * @param User $usuario
     * @param array $temaMunicipalIds IDs de temas municipales a filtrar (opcional)
     * @return array Array con ['tema_municipal_id' => porcentaje] donde porcentaje es 0-100
     */
    public function calcularPorcentajesPorTemaMunicipal(User $usuario, array $temaMunicipalIds = []): array
    {
        $estadisticas = $this->preguntaRiesgoRepository->findEstadisticasPorTemaMunicipal($usuario, $temaMunicipalIds);
        
        $porcentajes = [];
        foreach ($estadisticas as $temaMunicipalId => $stats) {
            $total = $stats['total'];
            $acertadas = $stats['acertadas'];
            
            if ($total > 0) {
                $porcentaje = ($acertadas / $total) * 100;
                $porcentajes[$temaMunicipalId] = round($porcentaje, 2);
            } else {
                $porcentajes[$temaMunicipalId] = 0.0;
            }
        }

        return $porcentajes;
    }

    /**
     * Obtiene los IDs de preguntas marcadas como riesgo por el usuario
     * 
     * @param User $usuario
     * @param bool $esMunicipal
     * @return array Array con IDs de preguntas
     */
    public function obtenerPreguntasMarcadasComoRiesgo(User $usuario, bool $esMunicipal = false): array
    {
        if ($esMunicipal) {
            return $this->preguntaRiesgoRepository->findPreguntasMunicipalesMarcadasComoRiesgo($usuario);
        } else {
            return $this->preguntaRiesgoRepository->findPreguntasMarcadasComoRiesgo($usuario);
        }
    }
}
