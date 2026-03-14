<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Organization;
use App\Entity\Product;
use App\Entity\Tag;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Organization $organization */
        $organization = $options['organization'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['placeholder' => 'Ex : Câble HDMI 2m', 'maxlength' => 200],
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'required' => false,
                'attr' => ['placeholder' => 'Ex : REF-001', 'maxlength' => 100],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Description du produit...'],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => '-- Aucune catégorie --',
                'query_builder' => fn (EntityRepository $er): QueryBuilder => $er->createQueryBuilder('c')
                    ->where('c.organization = :org')
                    ->setParameter('org', $organization)
                    ->orderBy('c.name', 'ASC'),
                'choice_label' => 'name',
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'label' => 'Tags',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'query_builder' => fn (EntityRepository $er): QueryBuilder => $er->createQueryBuilder('t')
                    ->where('t.organization = :org')
                    ->setParameter('org', $organization)
                    ->orderBy('t.name', 'ASC'),
                'choice_label' => 'name',
            ])
            ->add('purchasePriceCents', NumberType::class, [
                'label' => 'Prix d\'achat (€)',
                'required' => false,
                'scale' => 2,
                'mapped' => false,
                'attr' => ['placeholder' => '0,00', 'step' => '0.01', 'min' => '0'],
            ])
            ->add('sellingPriceCents', NumberType::class, [
                'label' => 'Prix de vente (€)',
                'required' => false,
                'scale' => 2,
                'mapped' => false,
                'attr' => ['placeholder' => '0,00', 'step' => '0.01', 'min' => '0'],
            ])
            ->add('images', FileType::class, [
                'label' => 'Images',
                'required' => false,
                'multiple' => true,
                'mapped' => false,
                'constraints' => [
                    new All([
                        'constraints' => [
                            new File([
                                'maxSize' => '5M',
                                'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                                'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, WebP, GIF).',
                            ]),
                        ],
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);

        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', Organization::class);
    }
}
