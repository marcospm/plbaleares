<?php

namespace App\Form;

use App\Entity\Tarea;
use App\Entity\Tema;
use App\Entity\Ley;
use App\Entity\Articulo;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TareaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la Tarea',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 5]
            ])
            ->add('semanaAsignacion', DateType::class, [
                'label' => 'Semana de Asignación (Lunes)',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'date'
                ],
                'help' => 'Selecciona el lunes de la semana a la que pertenece esta tarea'
            ])
            ->add('usuarios', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'username',
                'multiple' => true,
                'expanded' => false,
                'required' => true,
                'mapped' => false,
                'label' => 'Usuarios',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.activo = :activo')
                        ->andWhere('u.roles LIKE :role')
                        ->setParameter('activo', true)
                        ->setParameter('role', '%ROLE_USER%')
                        ->orderBy('u.username', 'ASC');
                },
                'attr' => ['class' => 'form-control']
            ])
            ->add('tema', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'required' => false,
                'label' => 'Tema relacionado (opcional)',
                'attr' => ['class' => 'form-control']
            ])
            ->add('ley', EntityType::class, [
                'class' => Ley::class,
                'choice_label' => 'nombre',
                'required' => false,
                'label' => 'Ley relacionada (opcional)',
                'attr' => ['class' => 'form-control']
            ])
            ->add('articulo', EntityType::class, [
                'class' => Articulo::class,
                'choice_label' => function(Articulo $articulo) {
                    return 'Art. ' . $articulo->getNumero() . ' - ' . $articulo->getLey()->getNombre();
                },
                'required' => false,
                'label' => 'Artículo relacionado (opcional)',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tarea::class,
        ]);
    }
}

