<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Décrit le formulaire front utilisé pour créer un produit via l'API.
 */
final class ProductFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $brandChoices = [];

        // Les marques exposées par l'API sont converties en choix Symfony avec leur IRI comme valeur.
        foreach ($options['brands'] as $brand) {
            if (!is_array($brand) || !isset($brand['name'])) {
                continue;
            }

            $brandName = (string) $brand['name'];
            $brandChoices[$brandName] = sprintf('/api/brands/%s', rawurlencode($brandName));
        }

        $builder
            ->add('brand', ChoiceType::class, [
                'label' => 'Marque',
                'choices' => $brandChoices,
                'placeholder' => 'Sélectionnez une marque',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
            ])
            ->add('shortDescription', TextType::class, [
                'label' => 'Description courte',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description longue',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('priceCents', IntegerType::class, [
                'label' => 'Prix en centimes',
                'constraints' => [new Assert\NotBlank(), new Assert\Positive()],
            ])
            ->add('imagePath', TextType::class, [
                'label' => 'Chemin image public',
                'help' => 'Exemple : /assets/images/home/product-1.jpg',
                'constraints' => [new Assert\NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Le formulaire dépend d'une liste de marques injectée au moment du rendu.
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'front_product',
            'brands' => [],
        ]);

        $resolver->setAllowedTypes('brands', 'array');
    }
}
