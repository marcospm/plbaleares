<?php

namespace App\Service;

use App\Entity\Dificultad;
use App\Repository\ConfiguracionDificultadExamenRepository;

class DificultadExamenService
{
    public function __construct(
        private ConfiguracionDificultadExamenRepository $configuracionRepository,
    ) {
    }

    /**
     * @return list<Dificultad>
     */
    public function niveles(): array
    {
        return [Dificultad::FACIL, Dificultad::MODERADA, Dificultad::DIFICIL];
    }

    public function estaActiva(Dificultad|string $dificultad): bool
    {
        $nivel = $this->comoNivel($dificultad);
        if (!$nivel instanceof Dificultad || $nivel === Dificultad::INDETERMINADO) {
            return false;
        }

        $config = $this->configuracionRepository->obtener();
        $activas = array_values(array_filter(
            $this->niveles(),
            fn (Dificultad $candidata): bool => $this->marcada($config, $candidata)
        ));
        if ($activas === []) {
            $activas = [Dificultad::DIFICIL];
        }

        return in_array($nivel, $activas, true);
    }

    /**
     * Dificultad válida al crear o empezar un examen.
     * Indeterminado solo se admite en el pegado de preguntas, que también clasifica el bloque.
     */
    public function acepta(string $dificultad, bool $permitirIndeterminado = false): bool
    {
        if ($permitirIndeterminado && $dificultad === Dificultad::INDETERMINADO->value) {
            return true;
        }

        return $this->estaActiva($dificultad);
    }

    /**
     * @return array<string, string>
     */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->niveles() as $nivel) {
            if ($this->estaActiva($nivel)) {
                $choices[$nivel->getLabel()] = $nivel->value;
            }
        }

        return $choices;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function nivelesActivos(): array
    {
        $niveles = [];
        foreach ($this->choices() as $label => $value) {
            $niveles[] = ['value' => $value, 'label' => $label];
        }

        return $niveles;
    }

    /**
     * @return array<string, mixed>
     */
    public function opcionesCampo(string $label = 'Dificultad', bool $conPlaceholder = true): array
    {
        $choices = $this->choices();
        $opciones = [
            'label' => $label,
            'choices' => $choices,
            'required' => true,
            'attr' => ['class' => 'form-control'],
            'invalid_message' => 'Esa dificultad no está disponible.',
        ];
        if ($conPlaceholder && count($choices) > 1) {
            $opciones['placeholder'] = 'Selecciona dificultad';
        }

        return $opciones;
    }

    private function comoNivel(Dificultad|string $dificultad): ?Dificultad
    {
        if ($dificultad instanceof Dificultad) {
            return $dificultad;
        }

        return Dificultad::tryFrom($dificultad);
    }

    private function marcada(\App\Entity\ConfiguracionDificultadExamen $config, Dificultad $nivel): bool
    {
        return match ($nivel) {
            Dificultad::FACIL => $config->isFacil(),
            Dificultad::MODERADA => $config->isModerada(),
            Dificultad::DIFICIL => $config->isDificil(),
            Dificultad::INDETERMINADO => false,
        };
    }
}
