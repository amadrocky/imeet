<?php

namespace App\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'N° et rue',
                ]
            ])
            ->add('postcode', IntegerType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Code postal',
                ]
            ])
            ->add('city', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ville',
                ]
            ])
            ->add('country', CountryType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Pays',
                ],
                'data' => 'FR'
            ])
            ->add('phoneNumber', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Téléphone',
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'attr' => [
                    'placeholder' => 'Email',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
    }
}
