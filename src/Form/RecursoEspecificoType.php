<?php

namespace App\Form;

use App\Entity\RecursoEspecifico;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecursoEspecificoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Recurso',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('alumnos', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'username',
                'label' => 'Alumnos Asignados',
                'multiple' => true,
                'expanded' => true,
                'required' => true,
                'query_builder' => $options['alumnos_query_builder'],
                'attr' => ['class' => 'form-control']
            ])
            ->add('archivo', FileType::class, [
                'label' => 'Archivo',
                'required' => $options['require_file'],
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'help' => 'Tamaño máximo: 50MB. Se aceptan cualquier tipo de archivo.'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecursoEspecifico::class,
            'require_file' => true,
            'alumnos_query_builder' => null,
        ]);
    }
}





