<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Nombre de Usuario',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'usuario@ejemplo.com']
            ])
            ->add('telefono', TextType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '600 000 000']
            ])
            ->add('dni', TextType::class, [
                'label' => 'DNI/NIE',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '12345678A']
            ])
            ->add('fechaNacimiento', DateType::class, [
                'label' => 'Fecha de Nacimiento',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('sexo', ChoiceType::class, [
                'label' => 'Sexo',
                'required' => false,
                'choices' => [
                    'Masculino' => 'M',
                    'Femenino' => 'F',
                    'Otro' => 'O',
                ],
                'placeholder' => 'Seleccionar...',
                'attr' => ['class' => 'form-control']
            ])
            ->add('direccion', TextareaType::class, [
                'label' => 'Dirección',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Calle, número, piso...']
            ])
            ->add('codigoPostal', TextType::class, [
                'label' => 'Código Postal',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '07001']
            ])
            ->add('ciudad', TextType::class, [
                'label' => 'Ciudad',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Palma']
            ])
            ->add('provincia', TextType::class, [
                'label' => 'Provincia',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Islas Baleares']
            ])
            ->add('iban', TextType::class, [
                'label' => 'IBAN',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'ES91 2100 0418 4502 0005 1332', 'maxlength' => 34]
            ])
            ->add('banco', TextType::class, [
                'label' => 'Banco',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nombre del banco']
            ])
            ->add('notas', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Notas adicionales sobre el usuario...']
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

