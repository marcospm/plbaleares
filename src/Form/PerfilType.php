<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PerfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'tu@email.com',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'Nueva Contraseña',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Deja en blanco si no quieres cambiarla',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Repetir Nueva Contraseña',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Repite la nueva contraseña',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'constraints' => [
                    new Length(
                        min: 6,
                        minMessage: 'La contraseña debe tener al menos {{ limit }} caracteres',
                        max: 4096,
                    ),
                ],
                'invalid_message' => 'Las contraseñas no coinciden.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
