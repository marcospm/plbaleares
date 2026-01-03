<?php

namespace App\Form;

use App\Entity\Examen;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ExamenSemanalPDFType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $examenSemanal = $options['examen_semanal'];
        $numeroPreguntas = $examenSemanal ? $examenSemanal->getNumeroPreguntas() : null;
        
        $builder
            ->add('aciertos', IntegerType::class, [
                'label' => 'Número de Aciertos',
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'max' => $numeroPreguntas,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(message: 'Debes introducir el número de aciertos.'),
                    new GreaterThanOrEqual(value: 0, message: 'Los aciertos no pueden ser negativos.'),
                    new LessThanOrEqual(
                        value: $numeroPreguntas ?? 999,
                        message: 'Los aciertos no pueden ser mayores que el número total de preguntas.'
                    )
                ]
            ])
            ->add('errores', IntegerType::class, [
                'label' => 'Número de Errores',
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'max' => $numeroPreguntas,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(message: 'Debes introducir el número de errores.'),
                    new GreaterThanOrEqual(value: 0, message: 'Los errores no pueden ser negativos.'),
                    new LessThanOrEqual(
                        value: $numeroPreguntas ?? 999,
                        message: 'Los errores no pueden ser mayores que el número total de preguntas.'
                    )
                ]
            ])
            ->add('enBlanco', IntegerType::class, [
                'label' => 'Número de Respuestas en Blanco',
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'max' => $numeroPreguntas,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(message: 'Debes introducir el número de respuestas en blanco.'),
                    new GreaterThanOrEqual(value: 0, message: 'Las respuestas en blanco no pueden ser negativas.'),
                    new LessThanOrEqual(
                        value: $numeroPreguntas ?? 999,
                        message: 'Las respuestas en blanco no pueden ser mayores que el número total de preguntas.'
                    )
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Guardar Resultados',
                'attr' => ['class' => 'btn btn-primary']
            ]);
        
        // Validación personalizada para asegurar que la suma sea correcta y no supere el total
        $builder->addEventListener(FormEvents::POST_SUBMIT, function ($event) use ($numeroPreguntas) {
            $examen = $event->getData();
            $form = $event->getForm();
            
            if ($examen && $numeroPreguntas !== null) {
                $aciertos = $examen->getAciertos() ?? 0;
                $errores = $examen->getErrores() ?? 0;
                $enBlanco = $examen->getEnBlanco() ?? 0;
                $suma = $aciertos + $errores + $enBlanco;
                
                if ($suma > $numeroPreguntas) {
                    $form->addError(new \Symfony\Component\Form\FormError(
                        sprintf('La suma de aciertos (%d) + errores (%d) + en blanco (%d) = %d, pero no puede superar el número total de preguntas (%d).', 
                            $aciertos, 
                            $errores, 
                            $enBlanco,
                            $suma,
                            $numeroPreguntas
                        )
                    ));
                } elseif ($suma < $numeroPreguntas) {
                    $form->addError(new \Symfony\Component\Form\FormError(
                        sprintf('La suma de aciertos (%d) + errores (%d) + en blanco (%d) = %d, pero debe ser igual al número total de preguntas (%d).', 
                            $aciertos, 
                            $errores, 
                            $enBlanco,
                            $suma,
                            $numeroPreguntas
                        )
                    ));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Examen::class,
            'examen_semanal' => null,
        ]);
    }
}

