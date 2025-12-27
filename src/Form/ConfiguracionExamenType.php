<?php

namespace App\Form;

use App\Entity\ConfiguracionExamen;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfiguracionExamenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('configuraciones', CollectionType::class, [
                'entry_type' => ConfiguracionExamenItemType::class,
                'entry_options' => ['label' => false],
                'allow_add' => false,
                'allow_delete' => false,
                'by_reference' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}

