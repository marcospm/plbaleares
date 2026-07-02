<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => [
                'label' => 'Nueva contrasena',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Minimo 6 caracteres',
                ],
            ],
            'second_options' => [
                'label' => 'Repetir nueva contrasena',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Repite tu contrasena',
                ],
            ],
            'invalid_message' => 'Las contrasenas no coinciden.',
            'constraints' => [
                new NotBlank(message: 'Introduce una nueva contrasena.'),
                new Length(
                    min: 6,
                    minMessage: 'La contrasena debe tener al menos {{ limit }} caracteres.',
                    max: 4096
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
