<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Recipe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class RecipeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'recipe_form.title',
                'attr' => ['placeholder' => 'recipe_form.title_placeholder'],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'choice_translation_domain' => false,
                'placeholder' => 'recipe_form.category_placeholder',
                'label' => 'recipe_form.category',
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'recipe_form.duration',
                'attr' => ['min' => 1, 'max' => 1439],
            ])
            ->add('ingredients', TextareaType::class, [
                'label' => 'recipe_form.ingredients',
                'help' => 'recipe_form.one_per_line',
                'attr' => ['rows' => 7, 'placeholder' => 'recipe_form.ingredients_placeholder'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'recipe_form.steps',
                'help' => 'recipe_form.one_per_line',
                'attr' => ['rows' => 9, 'placeholder' => 'recipe_form.steps_placeholder'],
            ])
            // Optional English version, shown to visitors browsing in English.
            ->add('titleEn', TextType::class, [
                'label' => 'recipe_form.title_en',
                'required' => false,
            ])
            ->add('ingredientsEn', TextareaType::class, [
                'label' => 'recipe_form.ingredients_en',
                'help' => 'recipe_form.one_per_line',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('contentEn', TextareaType::class, [
                'label' => 'recipe_form.steps_en',
                'help' => 'recipe_form.one_per_line',
                'required' => false,
                'attr' => ['rows' => 6],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'recipe_form.photo',
                'help' => 'recipe_form.photo_help',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new Image(
                        maxSize: '4M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}
