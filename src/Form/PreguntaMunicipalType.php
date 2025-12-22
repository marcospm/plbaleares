<?php

namespace App\Form;

use App\Entity\PreguntaMunicipal;
use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreguntaMunicipalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $municipio = $options['municipio'] ?? null;

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
            ->add('municipio', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Municipio',
                'attr' => ['class' => 'form-control'],
                'data' => $municipio,
            ])
            ->add('temaMunicipal', EntityType::class, [
                'class' => TemaMunicipal::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Tema Municipal',
                'attr' => ['class' => 'form-control'],
                'query_builder' => function ($er) use ($municipio) {
                    $qb = $er->createQueryBuilder('t');
                    if ($municipio) {
                        $qb->where('t.municipio = :municipio')
                           ->setParameter('municipio', $municipio);
                    }
                    return $qb->orderBy('t.nombre', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreguntaMunicipal::class,
            'municipio' => null,
        ]);
    }
}

