<?php

namespace App\Form;

use App\Entity\Grupo;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GrupoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Grupo',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: Grupo A, Grupo de Refuerzo, etc.'
                ]
            ])
            ->add('alumnos', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getNombreDisplay() . ' (' . $user->getUsername() . ')';
                },
                'query_builder' => function(UserRepository $er) {
                    return AlumnosPorCategoria::ordenar($er->createQueryBuilder('u')
                        ->where('u.activo = :activo')
                        ->andWhere('u.roles NOT LIKE :roleProfesor')
                        ->andWhere('u.roles NOT LIKE :roleAdmin')
                        ->setParameter('activo', true)
                        ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
                        ->setParameter('roleAdmin', '%"ROLE_ADMIN"%'));
                },
                'group_by' => AlumnosPorCategoria::etiqueta(...),
                'choice_translation_domain' => false,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'size' => 10
                ],
                'label' => 'Alumnos',
                'help' => 'Los alumnos están agrupados por categoría. Mantén pulsada Ctrl para elegir varios.'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Grupo::class,
        ]);
    }
}

