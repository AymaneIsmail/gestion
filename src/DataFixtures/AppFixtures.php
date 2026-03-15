<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductPrice;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const PRODUCT_NAMES = [
        'Câble HDMI', 'Écouteurs Bluetooth', 'Clavier mécanique', 'Souris ergonomique',
        'Webcam HD', 'Hub USB-C', 'Chargeur rapide', 'Batterie externe', 'Casque audio',
        'Enceinte portable', 'Montre connectée', 'Tablette tactile', 'Disque SSD',
        'Carte mémoire', 'Adaptateur HDMI', 'Câble USB-C', 'Lampe de bureau LED',
        'Support téléphone', 'Tapis de souris XXL', 'Ventilateur PC', 'Stylo tactile',
        'Pochette ordinateur', 'Sac à dos tech', 'Station de recharge', 'Micro USB',
        'T-shirt coton', 'Jean slim', 'Veste légère', 'Pull laine', 'Robe d\'été',
        'Chemise oxford', 'Short sport', 'Legging fitness', 'Manteau hiver', 'Cardigan',
        'Chaussettes pack', 'Polo classique', 'Blazer casual', 'Sweat capuche', 'Top rayé',
        'Montre analogique', 'Parfum 100ml', 'Crème hydratante', 'Sac cuir', 'Lunettes soleil',
        'Ceinture cuir', 'Portefeuille slim', 'Chapeau bob', 'Écharpe laine', 'Gants hiver',
        'Livre développement', 'Roman policier', 'Guide voyage', 'Carnet de notes',
        'Agenda 2026', 'Stylo roller', 'Marqueurs couleur', 'Post-it multicolor',
        'Calculatrice', 'Classeur A4', 'Pot à crayons', 'Bloc-notes', 'Règle 30cm',
        'Ciseaux bureau', 'Agrafeuse', 'Lampe loupe', 'Colle forte', 'Scotch invisible',
        'Cafetière expresso', 'Grille-pain 2 fentes', 'Bouilloire électrique', 'Robot mixeur',
        'Poêle antiadhésive', 'Casserole inox', 'Planche à découper', 'Couteau chef',
        'Boîte conservation', 'Carafe filtrante', 'Mug isotherme', 'Gourde sport',
        'Plaid polaire', 'Coussin déco', 'Vase céramique', 'Cadre photo 20x30',
        'Bougie parfumée', 'Miroir mural', 'Horloge murale', 'Lampe de chevet',
        'Tapis salon', 'Rideau occultant', 'Organiseur tiroir', 'Boîte à bijoux',
        'Yoga mat', 'Haltères 5kg', 'Corde à sauter', 'Bande résistance',
        'Gourde isotherme', 'Serviette microfibre', 'Short running', 'Brassière sport',
        'Chaussures trail', 'Sac de sport', 'Bonnet natation', 'Lunettes piscine',
        'Raquette badminton', 'Ballon de foot', 'Protège-genoux', 'Gants de boxe',
    ];

    private const DESCRIPTIONS = [
        'Produit de qualité supérieure, idéal pour un usage quotidien. Robuste et fiable.',
        'Design élégant et fonctionnel. Parfait pour les professionnels exigeants.',
        'Fabriqué avec des matériaux premium pour une durabilité maximale.',
        'Léger et compact, facile à transporter partout avec vous.',
        'Grande polyvalence d\'utilisation, s\'adapte à tous les besoins.',
        null,
        null,
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $appEnv,
        private readonly string $uploadDirectory,
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

        $categories = [];
        foreach (['Électronique', 'Vêtements', 'Maison', 'Sport', 'Bureau', 'Beauté'] as $name) {
            $cat = (new Category())->setName($name)->setOrganization($org);
            $manager->persist($cat);
            $categories[] = $cat;
        }

        $tags = [];
        foreach (['Nouveau', 'Promotion', 'Bestseller', 'Éco', 'Premium'] as $name) {
            $tag = (new Tag())->setName($name)->setOrganization($org);
            $manager->persist($tag);
            $tags[] = $tag;
        }

        // Prépare le dossier d'upload
        $subDir = 'products/' . date('Y/m');
        $uploadPath = $this->uploadDirectory . '/' . $subDir;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0775, true);
        }

        $names = self::PRODUCT_NAMES;
        shuffle($names);

        for ($i = 0; $i < 200; $i++) {
            $name = ($names[$i % count($names)] ?? 'Produit') . ' ' . ($i + 1);

            $purchaseCents = random_int(500, 15000);
            $margin        = random_int(10, 60);
            $sellingCents  = (int) ($purchaseCents * (1 + $margin / 100));

            $price = (new ProductPrice())
                ->setPurchasePriceCents($purchaseCents)
                ->setSellingPriceCents($sellingCents);

            $product = (new Product())
                ->setName($name)
                ->setReference('REF-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT))
                ->setDescription(self::DESCRIPTIONS[array_rand(self::DESCRIPTIONS)])
                ->setOrganization($org)
                ->setCategory($categories[array_rand($categories)])
                ->setStockQuantity(random_int(0, 150))
                ->setPrice($price);

            // 1 à 2 tags aléatoires
            $shuffledTags = $tags;
            shuffle($shuffledTags);
            $product->addTag($shuffledTags[0]);
            if (random_int(0, 1)) {
                $product->addTag($shuffledTags[1]);
            }

            // Image depuis picsum.photos
            $imagePath = $this->downloadPicsumImage($uploadPath, $subDir, $i + 1);
            if ($imagePath !== null) {
                $image = (new ProductImage())
                    ->setPath($imagePath)
                    ->setOriginalName('product-' . ($i + 1) . '.jpg');
                $product->addImage($image);
                $manager->persist($image);
            }

            $manager->persist($product);

            // Flush par lots pour éviter les saturations mémoire
            if ($i % 20 === 19) {
                $manager->flush();
            }
        }

        $manager->flush();
    }

    private function downloadPicsumImage(string $uploadPath, string $subDir, int $seed): ?string
    {
        $filename = 'fixture-' . $seed . '.jpg';
        $fullPath = $uploadPath . '/' . $filename;

        if (file_exists($fullPath)) {
            return $subDir . '/' . $filename;
        }

        $url = 'https://picsum.photos/seed/' . $seed . '/400/400';
        $context = stream_context_create(['http' => ['timeout' => 10, 'follow_location' => true]]);
        $data = @file_get_contents($url, false, $context);

        if ($data === false) {
            return null;
        }

        file_put_contents($fullPath, $data);

        return $subDir . '/' . $filename;
    }
}
