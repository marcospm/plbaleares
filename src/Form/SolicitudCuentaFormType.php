<?php

namespace App\Form;

use App\Dto\SolicitudCuentaDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SolicitudCuentaFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Tu nombre completo',
                ],
                'constraints' => [
                    new NotBlank(message: 'Introduce tu nombre.'),
                    new Length(max: 255),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'tu@email.com',
                ],
                'constraints' => [
                    new NotBlank(message: 'Introduce tu email.'),
                    new Email(message: 'Introduce un email válido.'),
                ],
            ])
            ->add('telefono', TelType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: +34 600 123 456',
                ],
            ])
            ->add('mensaje', TextareaType::class, [
                'label' => 'Cuéntanos qué buscas',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Indícanos la oposición, convocatoria o cualquier información para preparar una llamada contigo.',
                ],
                'constraints' => [
                    new NotBlank(message: 'Cuéntanos brevemente qué necesitas.'),
                    new Length(
                        min: 10,
                        minMessage: 'El mensaje debe tener al menos {{ limit }} caracteres.',
                        max: 5000,
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SolicitudCuentaDto::class,
        ]);
    }
}
