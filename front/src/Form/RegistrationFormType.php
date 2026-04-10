<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Décrit le formulaire d'inscription affiché par le front.
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Le front valide les règles de base avant de transmettre la demande d'inscription à l'API.
        $builder
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('accountType', ChoiceType::class, [
                'label' => 'Type de compte',
                'choices' => [
                    'Client' => 'customer',
                    'Commerçant' => 'merchant',
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(['customer', 'merchant']),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => 'Mot de passe',
                ],
                'second_options' => [
                    'label' => 'Confirmation mot de passe',
                ],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 8)],
            ])
            ->add('acceptTerms', CheckboxType::class, [
                'label' => 'J’accepte les CGU de GreenGoodies',
                'constraints' => [new Assert\IsTrue()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Un jeton distinct protège la soumission du formulaire d'inscription.
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'front_register',
        ]);
    }
}
