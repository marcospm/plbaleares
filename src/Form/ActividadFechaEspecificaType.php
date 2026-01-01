<?php

namespace App\Form;

use App\Entity\FranjaHorariaPersonalizada;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActividadFechaEspecificaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fechaEspecifica', DateType::class, [
                'label' => 'Fecha',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'fecha-actividad'
                ]
            ])
            ->add('horaInicio', TimeType::class, [
                'label' => 'Hora Inicio',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('horaFin', TimeType::class, [
                'label' => 'Hora Fin',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('tipoActividad', ChoiceType::class, [
                'label' => 'Tipo de Actividad',
                'required' => true,
                'choices' => [
                    'Repaso Básico' => 'repaso_basico',
                    'Estudio y Tareas' => 'estudio_tareas',
                ],
                'attr' => [
                    'class' => 'tipo-actividad'
                ]
            ])
            ->add('descripcionRepaso', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Descripción breve de la actividad...'
                ]
            ])
            ->add('temas', TextareaType::class, [
                'label' => 'Temas',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Temas específicos a repasar o estudiar (uno por línea)...'
                ]
            ])
            ->add('recursos', TextareaType::class, [
                'label' => 'Recursos',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Recursos disponibles (PDFs, documentos, etc.)...'
                ]
            ])
            ->add('enlaces', TextareaType::class, [
                'label' => 'Enlaces',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Enlaces externos relevantes (uno por línea)...'
                ]
            ])
            ->add('notas', TextareaType::class, [
                'label' => 'Notas Adicionales',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Notas o instrucciones adicionales del profesor...'
                ]
            ])
            ->add('orden', IntegerType::class, [
                'label' => 'Orden',
                'required' => true,
                'data' => 1,
                'attr' => [
                    'min' => 1
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FranjaHorariaPersonalizada::class,
        ]);
    }
}

