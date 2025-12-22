<?php

namespace App\Form;

use App\Entity\TareaAsignada;
use App\Entity\FranjaHorariaPersonalizada;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AsignarFranjaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $franjasDisponibles = $options['franjas_disponibles'] ?? [];
        
        $builder
            ->add('franjaHoraria', EntityType::class, [
                'class' => FranjaHorariaPersonalizada::class,
                'choices' => $franjasDisponibles,
                'choice_label' => function(FranjaHorariaPersonalizada $franja) {
                    $dia = $franja->getNombreDia();
                    $horaInicio = $franja->getHoraInicio()->format('H:i');
                    $horaFin = $franja->getHoraFin()->format('H:i');
                    return "$dia $horaInicio-$horaFin";
                },
                'required' => false,
                'label' => 'Franja Horaria',
                'placeholder' => 'Selecciona una franja horaria (opcional)',
                'attr' => ['class' => 'form-control'],
                'help' => 'Solo se pueden asignar tareas a franjas de tipo "Estudio y Tareas"'
            ])
        ;

        // Si se está usando para asignación masiva, agregar campo para múltiples usuarios
        if ($options['masiva'] ?? false) {
            $builder->add('asignarMasivamente', CheckboxType::class, [
                'label' => 'Asignar la misma franja a todos los usuarios seleccionados',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-check-input']
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TareaAsignada::class,
            'masiva' => false,
            'franjas_disponibles' => [],
        ]);
    }
}

