<?php

namespace App\Form;

use App\Entity\MensajeContacto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class MensajeContactoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Tu nombre completo'],
                'constraints' => [
                    new NotBlank(message: 'Por favor, introduce tu nombre.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'tu@email.com'],
                'constraints' => [
                    new NotBlank(message: 'Por favor, introduce tu email.'),
                    new Email(message: 'Por favor, introduce un email válido.'),
                ],
            ])
            ->add('asunto', TextType::class, [
                'label' => 'Asunto',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Asunto del mensaje (opcional)'],
            ])
            ->add('mensaje', TextareaType::class, [
                'label' => 'Mensaje',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 6, 'placeholder' => 'Escribe tu mensaje aquí...'],
                'constraints' => [
                    new NotBlank(message: 'Por favor, introduce tu mensaje.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MensajeContacto::class,
        ]);
    }
}

