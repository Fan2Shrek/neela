<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Deliberately not entity-backed: a secret field must never be echoed back into the
 * page, so it's never pre-filled from the stored value — leaving it blank means "keep
 * the current token", not "clear it" (that's what the checkbox is for).
 */
final class AppSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('githubToken', PasswordType::class, [
                'label' => 'settings.github_token',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ghp_...',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('removeGithubToken', CheckboxType::class, [
                'label' => 'settings.remove_github_token',
                'required' => false,
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
