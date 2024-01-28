<?php

namespace App\Form\Extensions;

use App\Form\AddressFormType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Date;

class EventTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastname', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom',
                ]
            ])
            ->add('firstname', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Prénom',
                ]
            ])
            ->add('eventName', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom de l\'événement',
                ]
            ])
            ->add('eventDate', DateType::class, [
                'label' => 'Date de l\'événement',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'min' => (new \DateTime())->format('Y-m-d')
                ]
            ])
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [AddressFormType::class];
    }
}