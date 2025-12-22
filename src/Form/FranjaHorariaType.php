<?php

namespace App\Form;

use App\Entity\FranjaHoraria;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class FranjaHorariaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('diaSemana', ChoiceType::class, [
                'label' => 'Día de la Semana',
                'choices' => [
                    'Lunes' => 1,
                    'Martes' => 2,
                    'Miércoles' => 3,
                    'Jueves' => 4,
                    'Viernes' => 5,
                    'Sábado' => 6,
                    'Domingo' => 7,
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('horaInicio', TimeType::class, [
                'label' => 'Hora de Inicio',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('horaFin', TimeType::class, [
                'label' => 'Hora de Fin',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('tipoActividad', ChoiceType::class, [
                'label' => 'Tipo de Actividad',
                'choices' => [
                    'Repaso Básico' => 'repaso_basico',
                    'Estudio y Tareas' => 'estudio_tareas',
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcionRepaso', TextType::class, [
                'label' => 'Descripción del Repaso',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: Repaso de articulado, Repaso de nombres de leyes']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FranjaHoraria::class,
            'constraints' => [
                new Callback([$this, 'validate']),
            ],
        ]);
    }

    public function validate($data, ExecutionContextInterface $context): void
    {
        if (!$data instanceof FranjaHoraria) {
            return;
        }

        if ($data->getHoraInicio() && $data->getHoraFin()) {
            if ($data->getHoraInicio() >= $data->getHoraFin()) {
                $context->buildViolation('La hora de fin debe ser posterior a la hora de inicio.')
                    ->atPath('horaFin')
                    ->addViolation();
            }
        }

        if ($data->getTipoActividad() === 'repaso_basico' && empty($data->getDescripcionRepaso())) {
            $context->buildViolation('La descripción del repaso es obligatoria para actividades de repaso básico.')
                ->atPath('descripcionRepaso')
                ->addViolation();
        }
    }
}

