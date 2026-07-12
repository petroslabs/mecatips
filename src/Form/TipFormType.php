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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TipFormType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

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
                // Le tip se rattache à une opération précise (feuille), pas à
                // une catégorie de premier niveau — regroupées par catégorie
                // parente (<optgroup>, non cliquable) plutôt que de répéter
                // le nom de la catégorie dans chaque libellé.
                'choice_label' => static fn (Category $category) => $category->getName(),
                'group_by' => static fn (Category $category) => $category->getParent()?->getName(),
                'label' => 'Opération concernée',
                'placeholder' => 'Choisir une opération',
                'query_builder' => static fn (CategoryRepository $repository) => $repository->createQueryBuilder('c')
                    ->join('c.parent', 'p')
                    ->addSelect('p')
                    ->where('c.parent IS NOT NULL')
                    ->orderBy('p.name', 'ASC')
                    ->addOrderBy('c.name', 'ASC'),
                // Une trentaine d'opérations dans un <select> plat n'est pas
                // pratique à parcourir — autocomplete en mode "local" (pas
                // d'appel réseau, ux-autocomplete filtre les <option> déjà
                // rendues côté client, largement suffisant à ce volume).
                // TomSelect respecte nativement les <optgroup> du <select>
                // qu'il enrichit.
                'autocomplete' => true,
                'constraints' => [
                    new NotBlank(message: 'Choisis une opération.'),
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
            ])
            ->add('vehicleLabel', TextType::class, [
                'label' => 'Véhicule concerné',
                'required' => false,
                'mapped' => false,
                'attr' => ['placeholder' => 'ex : Volkswagen Golf 4 1.9 TDI PD'],
                // Suggère les véhicules déjà en base pendant la saisie, pour
                // inciter à réutiliser une entrée existante plutôt qu'en
                // recréer une quasi-identique — mais un texte qui ne
                // correspond à rien reste soumis tel quel (create: true),
                // TipController::resolveVehicle() crée alors un nouveau
                // véhicule comme avant.
                'autocomplete' => true,
                'autocomplete_url' => $this->urlGenerator->generate('vehicle_autocomplete'),
                'tom_select_options' => ['create' => true, 'maxItems' => 1],
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
