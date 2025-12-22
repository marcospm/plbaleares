<?php

namespace App\Form;

use App\Entity\Pregunta;
use App\Entity\Tema;
use App\Entity\Ley;
use App\Entity\Articulo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreguntaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('texto', TextareaType::class, [
                'label' => 'Texto de la Pregunta',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ])
            ->add('opcionA', TextareaType::class, [
                'label' => 'Opción A',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 2]
            ])
            ->add('opcionB', TextareaType::class, [
                'label' => 'Opción B',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 2]
            ])
            ->add('opcionC', TextareaType::class, [
                'label' => 'Opción C',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 2]
            ])
            ->add('opcionD', TextareaType::class, [
                'label' => 'Opción D',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 2]
            ])
            ->add('respuestaCorrecta', ChoiceType::class, [
                'label' => 'Respuesta Correcta',
                'choices' => [
                    'A' => 'A',
                    'B' => 'B',
                    'C' => 'C',
                    'D' => 'D',
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
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
            ->add('retroalimentacion', TextareaType::class, [
                'label' => 'Retroalimentación',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ])
            ->add('tema', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Tema',
                'attr' => ['class' => 'form-control']
            ])
            ->add('ley', EntityType::class, [
                'class' => Ley::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Ley',
                'attr' => ['class' => 'form-control']
            ])
            ->add('articulo', EntityType::class, [
                'class' => Articulo::class,
                'choice_label' => function(Articulo $articulo) {
                    return 'Art. ' . $articulo->getNumero() . ' - ' . $articulo->getLey()->getNombre();
                },
                'required' => true,
                'label' => 'Artículo',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Pregunta::class,
        ]);
    }
}

