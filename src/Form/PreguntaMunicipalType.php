<?php

namespace App\Form;

use App\Entity\PreguntaMunicipal;
use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use App\Entity\PlantillaMunicipal;
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
        $isNew = $options['is_new'] ?? false;

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
                    'Indeterminado' => 'indeterminado',
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('retroalimentacion', TextareaType::class, [
                'label' => 'Retroalimentación',
                'required' => false,
                'attr' => [
                    'class' => 'form-control tinymce-editor',
                    'rows' => 10
                ]
            ])
            ->add('municipio', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Municipio',
                'attr' => ['class' => 'form-control', 'id' => 'pregunta_municipal_municipio'],
                'data' => $isNew ? null : $municipio,
                'placeholder' => 'Selecciona municipio',
            ])
            ->add('temaMunicipal', EntityType::class, [
                'class' => TemaMunicipal::class,
                'choice_label' => 'nombre',
                'required' => true,
                'label' => 'Tema Municipal',
                'attr' => ['class' => 'form-control', 'id' => 'pregunta_municipal_temaMunicipal'],
                'query_builder' => function ($er) use ($municipio) {
                    $qb = $er->createQueryBuilder('t')
                        ->where('t.activo = :activo')
                        ->setParameter('activo', true);
                    if ($municipio) {
                        // Filtrar solo los temas del municipio seleccionado
                        $qb->andWhere('t.municipio = :municipio')
                           ->setParameter('municipio', $municipio);
                    } else {
                        // Si no hay municipio seleccionado, no mostrar ningún tema
                        $qb->andWhere('1 = 0');
                    }
                    return $qb->orderBy('t.nombre', 'ASC');
                },
                'placeholder' => $municipio ? 'Selecciona un tema' : 'Primero selecciona un municipio',
            ])
            ->add('plantilla', EntityType::class, [
                'class' => PlantillaMunicipal::class,
                'choice_label' => function(PlantillaMunicipal $plantilla) {
                    $nombre = $plantilla->getNombre();
                    $numPreguntas = $plantilla->getNumeroPreguntas();
                    return $nombre . ' (' . $numPreguntas . ' preguntas)';
                },
                'required' => true,
                'label' => 'Plantilla',
                'attr' => ['class' => 'form-control', 'id' => 'pregunta_municipal_plantilla'],
                'placeholder' => 'Selecciona una plantilla',
                'query_builder' => function ($er) use ($municipio) {
                    $qb = $er->createQueryBuilder('p')
                        ->innerJoin('p.temaMunicipal', 't')
                        ->where('t.activo = :activo')
                        ->setParameter('activo', true);
                    if ($municipio) {
                        $qb->andWhere('t.municipio = :municipio')
                           ->setParameter('municipio', $municipio);
                    } else {
                        $qb->andWhere('1 = 0');
                    }
                    return $qb->orderBy('t.nombre', 'ASC')
                              ->addOrderBy('p.nombre', 'ASC');
                },
                'group_by' => function($plantilla) {
                    return $plantilla->getTemaMunicipal() ? $plantilla->getTemaMunicipal()->getNombre() : 'Sin tema';
                }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreguntaMunicipal::class,
            'municipio' => null,
            'is_new' => false,
        ]);
    }
}






