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
    ) {}

    public function load(ObjectManager $manager): void
    {
        if ($this->appEnv !== 'dev') {
            throw new \RuntimeException('Les fixtures ne peuvent être chargées qu\'en environnement dev.');
        }

        $user = new User();
        $user->setEmail('admin@example.com')
            ->setFullName('Administrateur')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $manager->persist($user);

        $org = (new Organization())->setName('Ma Boutique');
        $manager->persist($org);

        $manager->persist(new Membership($user, $org));

        $catElec = (new Category())->setName('Électronique')->setOrganization($org);
        $catVet  = (new Category())->setName('Vêtements')->setOrganization($org);
        $manager->persist($catElec);
        $manager->persist($catVet);

        $tagNew   = (new Tag())->setName('Nouveau')->setOrganization($org);
        $tagPromo = (new Tag())->setName('Promotion')->setOrganization($org);
        $manager->persist($tagNew);
        $manager->persist($tagPromo);

        $price = (new ProductPrice())
            ->setPurchasePriceCents(599)
            ->setSellingPriceCents(1499);

        $product = (new Product())
            ->setName('Câble HDMI 2m')
            ->setReference('HDMI-2M')
            ->setDescription('Câble HDMI haute vitesse, compatible 4K. Longueur : 2 mètres.')
            ->setOrganization($org)
            ->setCategory($catElec)
            ->addTag($tagNew)
            ->setPrice($price);
        $manager->persist($product);

        $manager->flush();
    }
}
