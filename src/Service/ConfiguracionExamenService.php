<?php

namespace App\Service;

use App\Entity\ConfiguracionExamen;
use App\Repository\ConfiguracionExamenRepository;
use Doctrine\ORM\EntityManagerInterface;

class ConfiguracionExamenService
{
    public function __construct(
        private ConfiguracionExamenRepository $configuracionExamenRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Normalizar porcentajes para que sumen 100%
     * 
     * @param array<ConfiguracionExamen> $configuraciones
     * @return array<ConfiguracionExamen>
     */
    public function normalizarPorcentajes(array $configuraciones): array
    {
        // Calcular suma total de porcentajes
        $sumaTotal = 0;
        foreach ($configuraciones as $config) {
            if ($config->getPorcentaje() !== null) {
                $sumaTotal += (float) $config->getPorcentaje();
            }
        }

        // Si la suma es 0 o null, no normalizar
        if ($sumaTotal <= 0) {
            return $configuraciones;
        }

        // Normalizar cada porcentaje proporcionalmente
        foreach ($configuraciones as $config) {
            if ($config->getPorcentaje() !== null) {
                $porcentajeOriginal = (float) $config->getPorcentaje();
                $porcentajeNormalizado = ($porcentajeOriginal / $sumaTotal) * 100;
                $config->setPorcentaje(number_format($porcentajeNormalizado, 2, '.', ''));
            }
        }

        return $configuraciones;
    }

    /**
     * Calcular distribución equitativa si no hay configuración
     * 
     * @param int $numeroTemas
     * @return float
     */
    public function calcularDistribucionEquitativa(int $numeroTemas): float
    {
        if ($numeroTemas <= 0) {
            return 0;
        }
        return round(100 / $numeroTemas, 2);
    }

    /**
     * Obtener porcentaje configurado para un tema, o distribución equitativa si no hay configuración
     * 
     * @param array<ConfiguracionExamen> $configuraciones
     * @param int $temaId
     * @param int $totalTemas
     * @return float
     */
    public function obtenerPorcentajeParaTema(array $configuraciones, int $temaId, int $totalTemas): float
    {
        foreach ($configuraciones as $config) {
            if ($config->getTema() && $config->getTema()->getId() === $temaId) {
                $porcentaje = $config->getPorcentaje();
                if ($porcentaje !== null) {
                    return (float) $porcentaje;
                }
            }
        }

        // Si no hay configuración, distribución equitativa
        return $this->calcularDistribucionEquitativa($totalTemas);
    }
}

