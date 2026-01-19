<?php

namespace App\Form;

use App\Entity\Plantilla;
use App\Entity\Tema;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlantillaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la Plantilla',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: Plantilla Tema 1 - Fácil'
                ]
            ])
            ->add('tema', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Tema',
                'attr' => ['class' => 'form-control'],
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('t')
                        ->where('t.activo = :activo')
                        ->setParameter('activo', true)
                        ->orderBy('t.nombre', 'ASC');
                }
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Plantilla::class,
        ]);
    }
}
