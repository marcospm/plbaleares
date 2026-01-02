<?php

namespace App\Form;

use App\Entity\ExamenSemanal;
use App\Entity\Tema;
use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use App\Entity\Convocatoria;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Count;

class ExamenSemanalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEditMode = $options['is_edit_mode'] ?? false;
        $examenSemanal = $options['examen_semanal'] ?? null;
        
        // Campos básicos del examen (siempre editables)
        // En modo creación, estos campos se ocultan y no se usan (se usan campos separados)
        // Por lo tanto, no deben mapearse a la entidad en modo creación
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Examen',
                'required' => $isEditMode,
                'mapped' => $isEditMode, // Solo mapear en modo edición
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'mapped' => $isEditMode, // Solo mapear en modo edición
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('fechaApertura', DateTimeType::class, [
                'label' => 'Fecha y Hora de Apertura',
                'required' => $isEditMode,
                'mapped' => $isEditMode, // Solo mapear en modo edición
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'datetime-local'
                ]
            ])
            ->add('fechaCierre', DateTimeType::class, [
                'label' => 'Fecha y Hora de Cierre',
                'required' => $isEditMode,
                'mapped' => $isEditMode, // Solo mapear en modo edición
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'datetime-local'
                ]
            ])
        ;
        
        // Solo agregar dificultad si NO está en modo edición
        // En modo creación, este campo se oculta (se usan dificultadGeneral y dificultadMunicipal)
        // Por lo tanto, no debe mapearse a la entidad
        if (!$isEditMode) {
            $builder->add('dificultad', ChoiceType::class, [
                'label' => 'Dificultad',
                'choices' => [
                    'Fácil' => 'facil',
                    'Moderada' => 'moderada',
                    'Difícil' => 'dificil',
                ],
                'required' => false,
                'mapped' => false, // No mapear en modo creación
                'attr' => ['class' => 'form-control']
            ]);
        }

        // En modo edición, no agregar más campos (solo los básicos ya agregados arriba)
        if (!$isEditMode) {
            // Modo creación: incluir todos los campos (se ocultan en el template)
            // Estos campos no se mapean porque se usan campos separados (nombreGeneral, fechaAperturaGeneral, etc.)
            $builder
                ->add('numeroPreguntas', IntegerType::class, [
                    'label' => 'Número de Preguntas (opcional)',
                    'required' => false,
                    'mapped' => false, // No mapear en modo creación
                    'attr' => [
                        'class' => 'form-control',
                        'min' => 1,
                        'placeholder' => 'Dejar vacío para usar todas las disponibles'
                    ],
                    'help' => 'Número de preguntas que tendrá el examen. Si se deja vacío, se usarán todas las preguntas disponibles.'
                ])
                ->add('temas', EntityType::class, [
                    'class' => Tema::class,
                    'choice_label' => 'nombre',
                    'multiple' => true,
                    'expanded' => false,
                    'required' => false,
                    'label' => 'Temas del Temario General',
                    'attr' => ['class' => 'form-control'],
                    'help' => 'Selecciona los temas para crear un examen del temario general (obligatorio si creas examen de 30 temas)'
                ])
                ->add('municipio', EntityType::class, [
                    'class' => Municipio::class,
                    'choice_label' => 'nombre',
                    'required' => false,
                    'label' => 'Municipio para Examen Municipal',
                    'placeholder' => 'Ninguno',
                    'attr' => ['class' => 'form-control', 'id' => 'examen_semanal_municipio'],
                    'help' => 'Selecciona un municipio para crear un examen municipal (obligatorio si creas examen municipal)'
                ])
                ->add('temasMunicipales', EntityType::class, [
                    'class' => TemaMunicipal::class,
                    'choice_label' => 'nombre',
                    'multiple' => true,
                    'expanded' => false,
                    'required' => false,
                    'label' => 'Temas Municipales',
                    'attr' => ['class' => 'form-control', 'id' => 'examen_semanal_temasMunicipales'],
                    'help' => 'Selecciona los temas municipales para el examen municipal (obligatorio si seleccionas municipio). Los temas se filtrarán automáticamente según el municipio seleccionado.',
                    'query_builder' => function ($er) {
                        return $er->createQueryBuilder('t')
                            ->where('1 = 0'); // No mostrar nada inicialmente
                    },
                ])
                ->add('convocatoria', EntityType::class, [
                    'class' => Convocatoria::class,
                    'choice_label' => 'nombre',
                    'required' => false,
                    'label' => 'Convocatoria para Examen de Convocatoria',
                    'placeholder' => 'Ninguna',
                    'attr' => ['class' => 'form-control', 'id' => 'examen_semanal_convocatoria'],
                    'help' => 'Selecciona una convocatoria (obligatorio si creas examen de convocatoria). El examen incluirá automáticamente todos los temas de todos los municipios de la convocatoria seleccionada.'
                ])
                ->add('temasMunicipalesConvocatoria', EntityType::class, [
                    'class' => TemaMunicipal::class,
                    'choice_label' => function($tema) {
                        return $tema->getNombre() . ' (' . $tema->getMunicipio()->getNombre() . ')';
                    },
                    'multiple' => true,
                    'expanded' => false,
                    'required' => false,
                    'mapped' => false, // No mapear a la entidad
                    'label' => 'Temas Municipales de la Convocatoria',
                    'attr' => ['class' => 'form-control', 'id' => 'examen_semanal_temasMunicipalesConvocatoria'],
                    'help' => 'Se cargarán automáticamente todos los temas de todos los municipios de la convocatoria seleccionada.',
                    'query_builder' => function ($er) {
                        return $er->createQueryBuilder('t')
                            ->where('1 = 0'); // No mostrar nada inicialmente
                    },
                ])
            ;

            // Campos para examen general (solo en modo creación)
            $builder
                ->add('nombreGeneral', TextType::class, [
                    'label' => 'Nombre del Examen General',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control'],
                    'help' => 'Nombre específico para el examen del temario general (requerido si seleccionas temas generales)'
                ])
                ->add('descripcionGeneral', TextareaType::class, [
                    'label' => 'Descripción del Examen General (opcional)',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control', 'rows' => 3]
                ])
                ->add('fechaAperturaGeneral', DateTimeType::class, [
                    'label' => 'Fecha y Hora de Apertura - General',
                    'required' => false,
                    'mapped' => false,
                    'widget' => 'single_text',
                    'html5' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'type' => 'datetime-local'
                    ]
                ])
                ->add('fechaCierreGeneral', DateTimeType::class, [
                    'label' => 'Fecha y Hora de Cierre - General',
                    'required' => false,
                    'mapped' => false,
                    'widget' => 'single_text',
                    'html5' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'type' => 'datetime-local'
                    ]
                ])
                ->add('dificultadGeneral', ChoiceType::class, [
                    'label' => 'Dificultad - General',
                    'choices' => [
                        'Fácil' => 'facil',
                        'Moderada' => 'moderada',
                        'Difícil' => 'dificil',
                    ],
                    'required' => false,
                    'mapped' => false,
                    'placeholder' => 'Selecciona dificultad',
                    'attr' => ['class' => 'form-control']
                ])
                ->add('numeroPreguntasGeneral', IntegerType::class, [
                    'label' => 'Número de Preguntas - General',
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'min' => 1,
                        'placeholder' => 'Dejar vacío para usar todas las disponibles'
                    ],
                    'help' => 'Número de preguntas que tendrá el examen general. Si se deja vacío, se usarán todas las preguntas disponibles.'
                ])
            ;

            // Campos para examen municipal (solo en modo creación)
            $builder
                ->add('nombreMunicipal', TextType::class, [
                    'label' => 'Nombre del Examen Municipal',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control'],
                    'help' => 'Nombre específico para el examen municipal (requerido si seleccionas municipio y temas municipales)'
                ])
                ->add('descripcionMunicipal', TextareaType::class, [
                    'label' => 'Descripción del Examen Municipal (opcional)',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control', 'rows' => 3]
                ])
                ->add('fechaAperturaMunicipal', DateTimeType::class, [
                    'label' => 'Fecha y Hora de Apertura - Municipal',
                    'required' => false,
                    'mapped' => false,
                    'widget' => 'single_text',
                    'html5' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'type' => 'datetime-local'
                    ]
                ])
                ->add('fechaCierreMunicipal', DateTimeType::class, [
                    'label' => 'Fecha y Hora de Cierre - Municipal',
                    'required' => false,
                    'mapped' => false,
                    'widget' => 'single_text',
                    'html5' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'type' => 'datetime-local'
                    ]
                ])
                ->add('dificultadMunicipal', ChoiceType::class, [
                    'label' => 'Dificultad - Municipal',
                    'choices' => [
                        'Fácil' => 'facil',
                        'Moderada' => 'moderada',
                        'Difícil' => 'dificil',
                    ],
                    'required' => false,
                    'mapped' => false,
                    'placeholder' => 'Selecciona dificultad',
                    'attr' => ['class' => 'form-control']
                ])
                ->add('numeroPreguntasMunicipal', IntegerType::class, [
                    'label' => 'Número de Preguntas - Municipal',
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'min' => 1,
                        'placeholder' => 'Dejar vacío para usar todas las disponibles'
                    ],
                    'help' => 'Número de preguntas que tendrá el examen municipal. Si se deja vacío, se usarán todas las preguntas disponibles.'
                ])
            ;

            // Campos para examen de convocatoria (solo en modo creación)
            $builder
                ->add('nombreConvocatoria', TextType::class, [
                    'label' => 'Nombre del Examen de Convocatoria',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control'],
                    'help' => 'Nombre específico para el examen de convocatoria (requerido si seleccionas convocatoria)'
                ])
                ->add('descripcionConvocatoria', TextareaType::class, [
                    'label' => 'Descripción del Examen de Convocatoria (opcional)',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control', 'rows' => 3]
                ])
                ->add('fechaAperturaConvocatoria', DateTimeType::class, [
                    'label' => 'Fecha y Hora de Apertura - Convocatoria',
                    'required' => false,
                    'mapped' => false,
                    'widget' => 'single_text',
                    'html5' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'type' => 'datetime-local'
                    ]
                ])
                ->add('fechaCierreConvocatoria', DateTimeType::class, [
                    'label' => 'Fecha y Hora de Cierre - Convocatoria',
                    'required' => false,
                    'mapped' => false,
                    'widget' => 'single_text',
                    'html5' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'type' => 'datetime-local'
                    ]
                ])
                ->add('dificultadConvocatoria', ChoiceType::class, [
                    'label' => 'Dificultad - Convocatoria',
                    'choices' => [
                        'Fácil' => 'facil',
                        'Moderada' => 'moderada',
                        'Difícil' => 'dificil',
                    ],
                    'required' => false,
                    'mapped' => false,
                    'placeholder' => 'Selecciona dificultad',
                    'attr' => ['class' => 'form-control']
                ])
                ->add('numeroPreguntasConvocatoria', IntegerType::class, [
                    'label' => 'Número de Preguntas - Convocatoria',
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'min' => 1,
                        'placeholder' => 'Dejar vacío para usar todas las disponibles'
                    ],
                    'help' => 'Número de preguntas que tendrá el examen de convocatoria. Si se deja vacío, se usarán todas las preguntas disponibles de todos los temas de todos los municipios de la convocatoria.'
                ])
            ;
            
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExamenSemanal::class,
            'is_edit_mode' => false,
            'examen_semanal' => null,
        ]);
    }
}

