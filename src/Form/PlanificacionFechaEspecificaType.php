<?php

namespace App\Form;

use App\Entity\PlanificacionPersonalizada;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanificacionFechaEspecificaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la Planificación',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ej: Planificación Semana 1 - Enero 2025'
                ]
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Descripción opcional de la planificación...'
                ]
            ])
            ->add('fechaInicio', DateType::class, [
                'label' => 'Fecha de Inicio',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('fechaFin', DateType::class, [
                'label' => 'Fecha de Fin',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
            ]);
            
        // Solo agregar el campo de usuarios si no estamos editando
        if (!$isEdit) {
            $builder->add('usuarios', EntityType::class, [
                'class' => User::class,
                'label' => 'Alumnos',
                'required' => true,
                'multiple' => true,
                'expanded' => false,
                'mapped' => false, // No mapear a la entidad
                'choice_label' => 'username',
                'placeholder' => 'Selecciona uno o más alumnos',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.roles LIKE :role')
                        ->andWhere('u.activo = :activo')
                        ->andWhere('(u.roles NOT LIKE :roleProfesor AND u.roles NOT LIKE :roleAdmin)')
                        ->setParameter('role', '%ROLE_USER%')
                        ->setParameter('roleProfesor', '%ROLE_PROFESOR%')
                        ->setParameter('roleAdmin', '%ROLE_ADMIN%')
                        ->setParameter('activo', true)
                        ->orderBy('u.username', 'ASC');
                },
                'attr' => [
                    'class' => 'form-select',
                    'size' => 5
                ]
            ]);
        }
        
        $builder->add('franjasHorarias', CollectionType::class, [
            'entry_type' => ActividadFechaEspecificaType::class,
            'entry_options' => ['label' => false],
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => 'Actividades por Fecha',
            'attr' => [
                'class' => 'actividades-collection'
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanificacionPersonalizada::class,
            'is_edit' => false,
        ]);
        
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}

