<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Recipe;
use App\Service\RecipeTranslator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Event\SubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class RecipeForm extends AbstractType
{
    public function __construct(private readonly RecipeTranslator $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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

        // The same three fields in each language. None is required in the browser:
        // the author fills one language and the other is translated automatically.
        foreach (['fr' => '', 'en' => 'En'] as $locale => $suffix) {
            $builder
                ->add('title'.$suffix, TextType::class, [
                    'label' => 'recipe_form.title',
                    'help' => 'recipe_form.title_help',
                    'required' => false,
                    'attr' => ['maxlength' => 80, 'lang' => $locale],
                ])
                ->add('ingredients'.$suffix, TextareaType::class, [
                    'label' => 'recipe_form.ingredients',
                    'help' => 'recipe_form.ingredients_help',
                    'required' => false,
                    'attr' => ['rows' => 6, 'lang' => $locale],
                ])
                ->add('content'.$suffix, TextareaType::class, [
                    'label' => 'recipe_form.steps',
                    'help' => 'recipe_form.steps_help',
                    'required' => false,
                    'attr' => ['rows' => 8, 'lang' => $locale],
                ]);
        }

        // Translate the missing language after the submitted values are set on the
        // recipe, but before validation, so both languages are validated.
        $before = null;
        $builder
            ->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) use (&$before): void {
                $before = $this->translator->snapshot($event->getForm()->getData());
            })
            ->addEventListener(FormEvents::SUBMIT, function (SubmitEvent $event) use (&$before): void {
                $this->translator->sync($event->getData(), $before);
            });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['auto_translation'] = $this->translator->isEnabled();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}
