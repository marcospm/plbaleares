<?php

namespace App\Form;

use App\Entity\ExamenSemanal;
use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use App\Entity\Grupo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ExamenSemanalMunicipalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Examen',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'El nombre del examen es obligatorio.'),
                ],
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('fechaApertura', DateTimeType::class, [
                'label' => 'Fecha y Hora de Apertura',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'datetime-local'
                ],
                'constraints' => [
                    new NotBlank(message: 'La fecha de apertura es obligatoria.'),
                ],
            ])
            ->add('fechaCierre', DateTimeType::class, [
                'label' => 'Fecha y Hora de Cierre',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'datetime-local'
                ],
                'constraints' => [
                    new NotBlank(message: 'La fecha de cierre es obligatoria.'),
                ],
            ])
            ->add('dificultad', ChoiceType::class, [
                'label' => 'Dificultad',
                'choices' => [
                    'Fácil' => 'facil',
                    'Moderada' => 'moderada',
                    'Difícil' => 'dificil',
                ],
                'required' => true,
                'placeholder' => 'Selecciona dificultad',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'La dificultad es obligatoria.'),
                ],
            ])
            ->add('numeroPreguntas', IntegerType::class, [
                'label' => 'Número de Preguntas (opcional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Dejar vacío para usar todas las disponibles'
                ],
                'help' => 'Número de preguntas que tendrá el examen. Si se deja vacío, se usarán todas las preguntas disponibles.'
            ])
            ->add('municipio', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Municipio',
                'placeholder' => 'Selecciona un municipio',
                'attr' => ['class' => 'form-control', 'id' => 'examen_semanal_municipio'],
                'help' => 'Selecciona el municipio para el examen (obligatorio)',
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un municipio.'),
                ],
            ])
            ->add('temasMunicipales', EntityType::class, [
                'class' => TemaMunicipal::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'mapped' => false, // No mapear directamente, lo procesaremos manualmente
                'label' => 'Temas Municipales',
                'attr' => ['class' => 'form-control', 'id' => 'examen_semanal_temasMunicipales'],
                'help' => 'Selecciona los temas municipales (obligatorio). Los temas se filtrarán automáticamente según el municipio seleccionado.',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('t')
                        ->where('1 = 0'); // No mostrar nada inicialmente
                },
            ])
            ->add('grupo', EntityType::class, [
                'class' => Grupo::class,
                'choice_label' => 'nombre',
                'required' => false,
                'label' => 'Grupo (opcional)',
                'placeholder' => 'Todos los alumnos',
                'help' => 'Si seleccionas un grupo, solo los alumnos de ese grupo podrán ver y realizar este examen.',
                'attr' => ['class' => 'form-control'],
            ])
        ;
        
        // Agregar listener para permitir cualquier valor en temasMunicipales antes de la validación
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            
            // Si hay datos de temas municipales, actualizar el campo para aceptarlos
            if (isset($data['temasMunicipales']) && !empty($data['temasMunicipales'])) {
                $temaIds = is_array($data['temasMunicipales']) ? $data['temasMunicipales'] : [$data['temasMunicipales']];
                
                // Recrear el campo con un query_builder que incluya los temas seleccionados
                $form->add('temasMunicipales', EntityType::class, [
                    'class' => TemaMunicipal::class,
                    'choice_label' => 'nombre',
                    'multiple' => true,
                    'expanded' => false,
                    'required' => false,
                    'mapped' => false,
                    'label' => 'Temas Municipales',
                    'attr' => ['class' => 'form-control', 'id' => 'examen_semanal_temasMunicipales'],
                    'query_builder' => function ($er) use ($temaIds) {
                        return $er->createQueryBuilder('t')
                            ->where('t.id IN (:ids)')
                            ->setParameter('ids', $temaIds);
                    },
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExamenSemanal::class,
        ]);
    }
}
