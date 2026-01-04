<?php

namespace App\Form;

use App\Entity\RecursoEspecifico;
use App\Entity\User;
use App\Entity\Grupo;
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
            ->add('grupo', EntityType::class, [
                'class' => Grupo::class,
                'choice_label' => function(Grupo $grupo) {
                    return $grupo->getNombre() . ' (' . $grupo->getAlumnos()->count() . ' alumno' . ($grupo->getAlumnos()->count() != 1 ? 's' : '') . ')';
                },
                'label' => 'Asignar a Grupo (opcional)',
                'required' => false,
                'placeholder' => 'Ningún grupo (asignar manualmente)',
                'query_builder' => $options['grupos_query_builder'],
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'grupo-select'
                ],
                'help' => 'Si seleccionas un grupo, el recurso se asignará automáticamente a todos los alumnos de ese grupo y solo será visible para ellos.'
            ])
            ->add('alumnos', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getNombreDisplay() . ' (' . $user->getUsername() . ')';
                },
                'label' => 'Alumnos Asignados (opcional si seleccionas un grupo)',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => $options['alumnos_query_builder'],
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'alumnos-select'
                ],
                'help' => 'Selecciona alumnos adicionales si lo deseas. Si has seleccionado un grupo, todos los alumnos del grupo se asignarán automáticamente.'
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
            'grupos_query_builder' => null,
        ]);
    }
}











