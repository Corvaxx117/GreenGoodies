<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Brand;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $brands = [];

        foreach (['Eco Panda', 'Necessaire', 'Georganics', 'Terra', 'Earthly'] as $brandName) {
            $brand = new Brand($brandName);
            $manager->persist($brand);
            $brands[$brandName] = $brand;
        }

        $merchant = new User('merchant@greengoodies.test', 'Aurelie', 'Martin');
        $merchant
            ->acceptTerms()
            ->setPassword($this->passwordHasher->hashPassword($merchant, 'Password123!'));

        $customer = new User('customer@greengoodies.test', 'Camille', 'Durand');
        $customer
            ->acceptTerms()
            ->setPassword($this->passwordHasher->hashPassword($customer, 'Password123!'));

        $manager->persist($merchant);
        $manager->persist($customer);

        foreach ($this->catalog() as $productData) {
            $product = new Product();
            $product
                ->changeBrand($brands[$productData['brand']])
                ->rename($productData['name'])
                ->changeSlug($productData['slug'])
                ->changeShortDescription($productData['shortDescription'])
                ->changeDescription($productData['description'])
                ->changePriceCents($productData['priceCents'])
                ->changeImagePath($productData['imagePath'])
                ->assignSeller($merchant);

            $manager->persist($product);
        }

        $manager->flush();
    }

    /**
     * @return list<array{brand: string, name: string, slug: string, shortDescription: string, description: string, priceCents: int, imagePath: string}>
     */
    private function catalog(): array
    {
        return [
            [
                'brand' => 'Eco Panda',
                'name' => 'Kit d\'hygiène recyclable',
                'slug' => 'kit-hygiene-recyclable',
                'shortDescription' => 'Pour une salle de bain eco-friendly',
                'description' => "Un assortiment pensé pour remplacer les indispensables jetables de la salle de bain par des alternatives durables et agréables à utiliser.\n\nServiettes douces, accessoires rechargeables et matières naturelles composent ce kit facile à adopter au quotidien.\n\nIdéal pour démarrer une routine plus responsable sans sacrifier le confort ni l’esthétique.",
                'priceCents' => 2499,
                'imagePath' => 'assets/images/home/product-1.jpg',
            ],
            [
                'brand' => 'Earthly',
                'name' => 'Shot Tropical',
                'slug' => 'shot-tropical',
                'shortDescription' => 'Fruits frais, pressés à froid',
                'description' => "Un shot vitaminé aux saveurs tropicales, préparé à partir de fruits frais et pressés à froid pour préserver un maximum d’arômes.\n\nSon format court permet une consommation rapide, pratique avant une journée bien remplie ou après une séance de sport.\n\nUne boisson simple, fraîche et sans superflu, en accord avec l’univers GreenGoodies.",
                'priceCents' => 450,
                'imagePath' => 'assets/images/home/product-2.jpg',
            ],
            [
                'brand' => 'Terra',
                'name' => 'Gourde en bois',
                'slug' => 'gourde-en-bois',
                'shortDescription' => '50cl, bois d\'olivier',
                'description' => "Cette gourde réutilisable associe une silhouette épurée à une finition bois chaleureuse qui lui donne une vraie présence visuelle.\n\nSon format 50cl la rend facile à transporter pour le bureau, les trajets du quotidien ou les sorties du week-end.\n\nUne alternative durable aux bouteilles jetables, pensée pour durer et se patiner joliment avec le temps.",
                'priceCents' => 1690,
                'imagePath' => 'assets/images/home/product-3.jpg',
            ],
            [
                'brand' => 'Eco Panda',
                'name' => 'Disques Démaquillants x3',
                'slug' => 'disques-demaquillants-x3',
                'shortDescription' => 'Solution efficace pour vous démaquiller en douceur',
                'description' => "Trois disques lavables et réutilisables conçus pour remplacer les cotons jetables dans la routine du soir.\n\nLeur texture douce respecte les peaux sensibles tout en assurant un nettoyage efficace avec une eau micellaire ou une huile démaquillante.\n\nUn geste simple pour limiter les déchets dans la salle de bain sans changer ses habitudes.",
                'priceCents' => 1990,
                'imagePath' => 'assets/images/home/product-4.jpg',
            ],
            [
                'brand' => 'Terra',
                'name' => 'Bougie Lavande & Patchouli',
                'slug' => 'bougie-lavande-patchouli',
                'shortDescription' => 'Cire naturelle',
                'description' => "Une bougie parfumée aux notes profondes de patchouli adoucies par la lavande, pour une ambiance calme et enveloppante.\n\nSa cire naturelle assure une combustion propre et une diffusion progressive du parfum dans la pièce.\n\nUn objet déco et sensoriel qui s’intègre facilement dans un intérieur apaisé.",
                'priceCents' => 3200,
                'imagePath' => 'assets/images/home/product-5.jpg',
            ],
            [
                'brand' => 'Georganics',
                'name' => 'Brosse à dent',
                'slug' => 'brosse-a-dent',
                'shortDescription' => 'Bois de hêtre rouge issu de forêts gérées durablement',
                'description' => "Une brosse à dents au design simple, fabriquée à partir de bois issu de filières responsables.\n\nSa prise en main agréable et ses finitions sobres en font un objet du quotidien à la fois utile et cohérent avec une démarche zéro déchet.\n\nUne petite transition facile pour réduire durablement le plastique dans la salle de bain.",
                'priceCents' => 540,
                'imagePath' => 'assets/images/home/product-6.jpg',
            ],
            [
                'brand' => 'Terra',
                'name' => 'Kit couvert en bois',
                'slug' => 'kit-couvert-en-bois',
                'shortDescription' => 'Revêtement Bio en olivier & sac de transport',
                'description' => "Un kit compact avec cuillère, fourchette et couteau en bois, pensé pour les repas nomades et les pauses déjeuner à l’extérieur.\n\nLe sac de transport en tissu protège les couverts et facilite leur rangement dans un sac ou un panier.\n\nUne solution durable pour éviter les couverts jetables tout en gardant un objet beau et agréable à utiliser.",
                'priceCents' => 1230,
                'imagePath' => 'assets/images/home/product-7.jpg',
            ],
            [
                'brand' => 'Necessaire',
                'name' => 'Nécessaire, déodorant Bio',
                'slug' => 'necessaire-deodorant-bio',
                'shortDescription' => '50ml déodorant à l\'eucalyptus',
                'description' => "Déodorant Nécessaire, une formule révolutionnaire composée exclusivement d'ingrédients naturels pour une protection efficace et bienfaisante.\n\nChaque flacon de 50 ml renferme le secret d'une fraîcheur longue durée, sans compromettre votre bien-être ni l'environnement. Conçu avec soin, ce déodorant allie le pouvoir antibactérien des extraits de plantes aux vertus apaisantes des huiles essentielles, assurant une sensation de confort toute la journée.\n\nGrâce à sa formule non irritante et respectueuse de votre peau, Nécessaire offre une alternative saine aux déodorants conventionnels, tout en préservant l'équilibre naturel de votre corps.",
                'priceCents' => 850,
                'imagePath' => 'assets/images/home/product-8.jpg',
            ],
            [
                'brand' => 'Earthly',
                'name' => 'Savon Bio',
                'slug' => 'savon-bio',
                'shortDescription' => 'Thé, Orange & Girofle',
                'description' => "Un savon solide aux notes fraîches et épicées, formulé pour nettoyer la peau en douceur sans l’assécher.\n\nL’accord thé, orange et girofle lui donne une identité olfactive subtile, idéale pour un usage quotidien dans la salle de bain.\n\nSa composition simple et son format durable en font un incontournable du catalogue GreenGoodies.",
                'priceCents' => 1890,
                'imagePath' => 'assets/images/home/product-9.jpg',
            ],
        ];
    }
}
