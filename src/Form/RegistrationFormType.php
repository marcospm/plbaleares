<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Nombre de Usuario (para login)',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Elige un nombre de usuario para iniciar sesión'
                ],
                'constraints' => [
                    new NotBlank(message: 'Por favor, introduce un nombre de usuario'),
                    new Length(
                        min: 3,
                        minMessage: 'El nombre de usuario debe tener al menos {{ limit }} caracteres',
                        max: 50,
                    ),
                ],
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Tu nombre completo'
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Contraseña',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Mínimo 6 caracteres']
                ],
                'second_options' => [
                    'label' => 'Repetir Contraseña',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Repite la contraseña']
                ],
                'invalid_message' => 'Las contraseñas no coinciden',
                'constraints' => [
                    new NotBlank(message: 'Por favor, introduce una contraseña'),
                    new Length(
                        min: 6,
                        minMessage: 'La contraseña debe tener al menos {{ limit }} caracteres',
                        max: 4096,
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

