<?php

namespace App\Form;

use App\Entity\Tema;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class ExamenIniciarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('temas', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => true,
                'required' => true,
                'label' => 'Selecciona los Temas',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('t')
                        ->where('t.activo = :activo')
                        ->setParameter('activo', true)
                        ->orderBy('t.nombre', 'ASC');
                },
                'constraints' => [
                    new Count(min: 1, minMessage: 'Debes seleccionar al menos un tema')
                ],
                'attr' => ['class' => 'form-check'],
                'placeholder' => false,
            ])
            ->add('dificultad', ChoiceType::class, [
                'label' => 'Dificultad',
                'choices' => [
                    'Fácil' => 'facil',
                    'Moderada' => 'moderada',
                    'Difícil' => 'dificil',
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('numeroPreguntas', ChoiceType::class, [
                'label' => 'Número de Preguntas',
                'choices' => [
                    '20' => 20,
                    '30' => 30,
                    '40' => 40,
                    '50' => 50,
                    '60' => 60,
                    '70' => 70,
                    '80' => 80,
                    '90' => 90,
                    '100' => 100,
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // No hay data_class porque es un formulario de configuración
        ]);
    }
}

