<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $appEnv,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if ($this->appEnv !== 'dev') {
            throw new \RuntimeException('Les fixtures ne peuvent être chargées qu\'en environnement dev.');
        }
        // User
        $user = new User();
        $user->setEmail('admin@example.com')
        ->setFullName('Administrateur')
        ->setPassword($this->passwordHasher->hashPassword($user, 'password'))
        ->setRoles(['ROLE_ADMIN'])
        ;
        
        $manager->persist($user);

        // Organization
        $org = new Organization();
        $org->setName('Ma Boutique');
        $manager->persist($org);

        // Membership
        $membership = new Membership($user, $org);
        $manager->persist($membership);

        // Categories
        $catElec = new Category();
        $catElec->setName('Électronique')
        ->setOrganization($org)
        ->persist($catElec);

        $catVet = new Category();
        $catVet->setName('Vêtements');
        $catVet->setOrganization($org);
        $manager->persist($catVet);

        // Tags
        $tagNew = new Tag();
        $tagNew->setName('Nouveau');
        $tagNew->setOrganization($org);
        $manager->persist($tagNew);

        $tagPromo = new Tag();
        $tagPromo->setName('Promotion');
        $tagPromo->setOrganization($org);
        $manager->persist($tagPromo);

        // Product
        $product = new Product();
        $product->setName('Câble HDMI 2m');
        $product->setReference('HDMI-2M');
        $product->setDescription('Câble HDMI haute vitesse, compatible 4K. Longueur : 2 mètres.');
        $product->setOrganization($org);
        $product->setCategory($catElec);
        $product->addTag($tagNew);

        $price = new ProductPrice();
        $price->setPurchasePriceCents(599);  // 5,99 €
        $price->setSellingPriceCents(1499);  // 14,99 €
        $product->setPrice($price);

        $manager->persist($product);

        $manager->flush();
    }
}
