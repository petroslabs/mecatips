<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Enum\TipType as TipTypeEnum;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TipFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new NotBlank(message: 'Donne un titre à ton tip.'),
                    new Length(max: 200),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Le tip',
                'attr' => ['rows' => 6],
                'constraints' => [
                    new NotBlank(message: 'Décris ton tip.'),
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
                'query_builder' => static fn (CategoryRepository $repository) => $repository->createQueryBuilder('c')
                    ->where('c.parent IS NULL')
                    ->orderBy('c.name', 'ASC'),
                'constraints' => [
                    new NotBlank(message: 'Choisis une catégorie.'),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => TipTypeEnum::class,
                'label' => 'Type de tip',
                'expanded' => true,
                // La value de l'enum porte le libellé français affiché ; le
                // choice_value reste le name anglais stable (soumis par le
                // formulaire), indépendant du texte d'affichage.
                'choice_label' => static fn (TipTypeEnum $type) => $type->value,
                'choice_value' => static fn (?TipTypeEnum $type) => $type?->name,
                'constraints' => [
                    new NotBlank(message: 'Choisis un type de tip.'),
                ],
            ])
            ->add('allVehicles', CheckboxType::class, [
                'label' => 'Valable pour tous véhicules',
                'required' => false,
                'mapped' => false,
                'data' => true,
                'attr' => ['id' => 'tip_all_vehicles'],
            ])
            ->add('vehicleLabel', TextType::class, [
                'label' => 'Véhicule concerné',
                'required' => false,
                'mapped' => false,
                'attr' => ['placeholder' => 'ex : Golf 4 1.9 TDI PD', 'list' => 'vehicle-labels'],
            ])
            ->add('tagsInput', TextType::class, [
                'label' => 'Tags (optionnel, séparés par une virgule)',
                'required' => false,
                'mapped' => false,
                'attr' => ['placeholder' => 'bruit, fuite, démarrage à froid'],
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
