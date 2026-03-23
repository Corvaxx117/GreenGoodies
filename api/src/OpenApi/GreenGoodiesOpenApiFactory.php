<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\Schema;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Enrichit la documentation OpenAPI générée par API Platform avec les routes custom du projet.
 */
final readonly class GreenGoodiesOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // Les informations globales rappellent les deux modes d'authentification exposés par l'application.
        $openApi = $openApi->withInfo(
            $openApi->getInfo()
                ->withSummary('API REST GreenGoodies')
                ->withDescription(<<<'MARKDOWN'
API REST de GreenGoodies.

- Swagger UI : `/api/docs`
- Spécification OpenAPI JSON : `/api/docs.jsonopenapi`
- Authentification front : JWT Bearer obtenu via `/auth`
- Authentification commerçant : clé API `X-API-Key` pour `/api/merchant/products`
MARKDOWN),
        );

        $components = $this->buildComponents($openApi->getComponents());
        $openApi = $openApi->withComponents($components);

        $paths = $openApi->getPaths();

        // Les routes suivantes ne sont pas toutes des ApiResource, elles sont donc documentées manuellement.
        $paths->addPath('/auth', new PathItem(
            post: new Operation(
                operationId: 'postAuthToken',
                tags: ['Authentication'],
                responses: [
                    '200' => $this->jsonResponse(
                        'JWT généré avec succès.',
                        $this->refSchema('AuthTokenResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'Identifiants invalides.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Obtenir un JWT',
                description: 'Retourne un JWT utilisable par le front pour les routes protégées de l’API.',
                requestBody: $this->jsonRequestBody(
                    'Identifiants de connexion.',
                    $this->refSchema('AuthTokenRequest'),
                    true,
                ),
                security: [],
            ),
        ));

        $paths->addPath('/api/register', new PathItem(
            post: new Operation(
                operationId: 'postRegisterUser',
                tags: ['Users'],
                responses: [
                    '201' => $this->jsonResponse(
                        'Utilisateur créé.',
                        $this->refSchema('RegisterResponse'),
                    ),
                    '409' => $this->jsonResponse(
                        'Email déjà utilisé.',
                        $this->refSchema('ErrorResponse'),
                    ),
                    '422' => $this->jsonResponse(
                        'Données invalides.',
                        $this->refSchema('ValidationErrorResponse'),
                    ),
                ],
                summary: 'Créer un compte',
                description: 'Inscription d’un utilisateur GreenGoodies.',
                requestBody: $this->jsonRequestBody(
                    'Données d’inscription.',
                    $this->refSchema('RegisterRequest'),
                    true,
                ),
                security: [],
            ),
        ));

        $paths->addPath('/api/me', new PathItem(
            post: $paths->getPath('/api/me')?->getPost(),
            get: new Operation(
                operationId: 'getCurrentUser',
                tags: ['Users'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Profil utilisateur courant.',
                        $this->refSchema('CurrentUserResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'JWT manquant ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Récupérer le profil courant',
                description: 'Retourne les informations du compte authentifié côté front.',
                security: [['JWT' => []]],
            ),
            delete: new Operation(
                operationId: 'deleteCurrentUser',
                tags: ['Users'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Compte supprimé.',
                        $this->refSchema('MessageResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'JWT manquant ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Supprimer le compte courant',
                description: 'Supprime le compte authentifié ainsi que ses données associées.',
                security: [['JWT' => []]],
            ),
        ));

        $paths->addPath('/api/me/api-key/activate', new PathItem(
            post: new Operation(
                operationId: 'postActivateMerchantApiKey',
                tags: ['Merchant API'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Clé API activée.',
                        $this->refSchema('ActivateApiKeyResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'JWT manquant ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Activer la clé API commerçant',
                description: 'Active l’accès API commerçant et retourne la clé API en clair une seule fois.',
                security: [['JWT' => []]],
            ),
        ));

        $paths->addPath('/api/me/api-key/deactivate', new PathItem(
            post: new Operation(
                operationId: 'postDeactivateMerchantApiKey',
                tags: ['Merchant API'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Clé API désactivée.',
                        $this->refSchema('MessageResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'JWT manquant ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Désactiver la clé API commerçant',
                description: 'Désactive l’accès API commerçant du compte authentifié.',
                security: [['JWT' => []]],
            ),
        ));

        $paths->addPath('/api/cart', new PathItem(
            get: new Operation(
                operationId: 'getCart',
                tags: ['Cart'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Panier courant.',
                        $this->refSchema('CartResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'JWT manquant ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Récupérer le panier courant',
                description: 'Retourne la commande brouillon de l’utilisateur connecté.',
                security: [['JWT' => []]],
            ),
        ));

        $paths->addPath('/api/cart/items/{slug}', new PathItem(
            post: new Operation(
                operationId: 'postCartItem',
                tags: ['Cart'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Panier mis à jour.',
                        $this->refSchema('CartResponse'),
                    ),
                    '404' => $this->jsonResponse(
                        'Produit introuvable.',
                        $this->refSchema('ErrorResponse'),
                    ),
                    '422' => $this->jsonResponse(
                        'Quantité invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Ajouter ou mettre à jour un produit du panier',
                description: 'Met à jour la quantité d’un produit dans le panier courant. `0` retire le produit.',
                parameters: [
                    new Parameter(
                        name: 'slug',
                        in: 'path',
                        description: 'Slug du produit à ajouter ou mettre à jour.',
                        required: true,
                        schema: ['type' => 'string'],
                        example: 'necessaire-deodorant-bio',
                    ),
                ],
                requestBody: $this->jsonRequestBody(
                    'Quantité souhaitée pour ce produit.',
                    $this->refSchema('CartItemRequest'),
                    true,
                ),
                security: [['JWT' => []]],
            ),
        ));

        $paths->addPath('/api/cart/clear', new PathItem(
            post: new Operation(
                operationId: 'postClearCart',
                tags: ['Cart'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Panier vidé.',
                        $this->refSchema('MessageResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'JWT manquant ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Vider le panier',
                description: 'Supprime la commande brouillon en cours.',
                security: [['JWT' => []]],
            ),
        ));

        $paths->addPath('/api/cart/checkout', new PathItem(
            post: new Operation(
                operationId: 'postCheckoutCart',
                tags: ['Cart'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Commande validée.',
                        $this->refSchema('CheckoutCartResponse'),
                    ),
                    '400' => $this->jsonResponse(
                        'Panier vide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'JWT manquant ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Valider la commande',
                description: 'Valide le panier courant et le transforme en commande.',
                security: [['JWT' => []]],
            ),
        ));

        $paths->addPath('/api/account', new PathItem(
            get: new Operation(
                operationId: 'getAccountDashboard',
                tags: ['Users'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Données du compte.',
                        $this->refSchema('AccountResponse'),
                    ),
                    '401' => $this->jsonResponse(
                        'JWT manquant ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Récupérer les données du compte',
                description: 'Retourne les dernières commandes validées et l’état de l’accès API du compte.',
                security: [['JWT' => []]],
            ),
        ));

        if ($productsPath = $paths->getPath('/api/products')) {
            // Les opérations API Platform existantes sont redocumentées avec un wording métier plus explicite.
            $paths->addPath('/api/products', $productsPath
                ->withGet(
                    $productsPath->getGet()?->withTags(['Catalog'])
                        ->withSummary('Lister les produits du catalogue')
                        ->withDescription('Retourne les produits publiés visibles sur le catalogue public.')
                )
                ->withPost(
                    $productsPath->getPost()?->withTags(['Catalog'])
                        ->withSummary('Créer un produit')
                        ->withDescription('Ajoute un produit pour l’utilisateur authentifié côté front.')
                        ->withSecurity([['JWT' => []]])
                ),
            );
        }

        if ($productPath = $paths->getPath('/api/products/{slug}')) {
            $paths->addPath('/api/products/{slug}', $productPath->withGet(
                $productPath->getGet()?->withTags(['Catalog'])
                    ->withSummary('Voir un produit')
                    ->withDescription('Retourne le détail d’un produit publié à partir de son slug.'),
            ));
        }

        if ($brandsPath = $paths->getPath('/api/brands')) {
            $paths->addPath('/api/brands', $brandsPath->withGet(
                $brandsPath->getGet()?->withTags(['Brands'])
                    ->withSummary('Lister les marques')
                    ->withDescription('Retourne la liste des marques disponibles pour référencer un produit.'),
            ));
        }

        if ($brandPath = $paths->getPath('/api/brands/{name}')) {
            $paths->addPath('/api/brands/{name}', $brandPath->withGet(
                $brandPath->getGet()?->withTags(['Brands'])
                    ->withSummary('Voir une marque')
                    ->withDescription('Retourne une marque à partir de son nom.'),
            ));
        }

        $paths->addPath('/api/merchant/products', new PathItem(
            get: new Operation(
                operationId: 'getMerchantProducts',
                tags: ['Merchant API'],
                responses: [
                    '200' => $this->jsonResponse(
                        'Produits du commerçant.',
                        $this->merchantProductsResponseSchema(),
                    ),
                    '401' => $this->jsonResponse(
                        'Clé API manquante ou invalide.',
                        $this->refSchema('ErrorResponse'),
                    ),
                ],
                summary: 'Lister les produits du commerçant',
                description: 'Retourne uniquement les produits du propriétaire de la clé API `X-API-Key`.',
                parameters: [
                    new Parameter(
                        name: 'X-API-Key',
                        in: 'header',
                        description: 'Clé API commerçant activée depuis le compte utilisateur.',
                        required: true,
                        schema: ['type' => 'string'],
                        example: 'GGK_1234567890ABCDEF1234567890ABCDEF12345678',
                    ),
                ],
                security: [['merchantApiKey' => []]],
            ),
        ));

        return $openApi;
    }

    private function buildComponents(Components $components): Components
    {
        $schemas = $components->getSchemas() ?? new \ArrayObject();
        $securitySchemes = $components->getSecuritySchemes() ?? new \ArrayObject();

        // Deux schémas d'authentification coexistent : JWT pour le front, clé API pour les commerçants.
        $securitySchemes['JWT'] = new SecurityScheme(
            type: 'http',
            description: 'JWT obtenu via /auth',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );

        $securitySchemes['merchantApiKey'] = new SecurityScheme(
            type: 'apiKey',
            description: 'Clé API commerçant envoyée dans l’en-tête X-API-Key',
            name: 'X-API-Key',
            in: 'header',
        );

        $schemas['AuthTokenRequest'] = $this->schema([
            'type' => 'object',
            'required' => ['email', 'password'],
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'merchant@greengoodies.test'],
                'password' => ['type' => 'string', 'example' => 'Password123!'],
            ],
        ]);

        $schemas['AuthTokenResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'token' => ['type' => 'string', 'example' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'],
            ],
        ]);

        $schemas['RegisterRequest'] = $this->schema([
            'type' => 'object',
            'required' => ['firstName', 'lastName', 'email', 'password', 'acceptTerms'],
            'properties' => [
                'firstName' => ['type' => 'string', 'example' => 'Aurelie'],
                'lastName' => ['type' => 'string', 'example' => 'Martin'],
                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'aurelie@example.com'],
                'password' => ['type' => 'string', 'example' => 'Password123!'],
                'acceptTerms' => ['type' => 'boolean', 'example' => true],
            ],
        ]);

        $schemas['RegisterResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 3],
                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'aurelie@example.com'],
                'firstName' => ['type' => 'string', 'example' => 'Aurelie'],
                'lastName' => ['type' => 'string', 'example' => 'Martin'],
            ],
        ]);

        $schemas['CurrentUserResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'merchant@greengoodies.test'],
                'firstName' => ['type' => 'string', 'example' => 'Aurelie'],
                'lastName' => ['type' => 'string', 'example' => 'Martin'],
                'roles' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'example' => ['ROLE_USER'],
                ],
                'apiAccessEnabled' => ['type' => 'boolean', 'example' => true],
                'apiKeyPrefix' => ['type' => 'string', 'nullable' => true, 'example' => 'GGK_1234567890AB'],
            ],
        ]);

        $schemas['ActivateApiKeyResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Accès API activé.'],
                'apiKey' => ['type' => 'string', 'example' => 'GGK_1234567890ABCDEF1234567890ABCDEF12345678'],
            ],
        ]);

        $schemas['MessageResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Accès API désactivé.'],
            ],
        ]);

        $schemas['CartItemRequest'] = $this->schema([
            'type' => 'object',
            'required' => ['quantity'],
            'properties' => [
                'quantity' => ['type' => 'integer', 'minimum' => 0, 'example' => 2],
            ],
        ]);

        $schemas['CartResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'reference' => ['type' => 'string', 'nullable' => true, 'example' => 'GG-A1B2C3D4E5F6'],
                'status' => ['type' => 'string', 'example' => 'draft'],
                'itemCount' => ['type' => 'integer', 'example' => 3],
                'deliveryCents' => ['type' => 'integer', 'example' => 0],
                'deliveryLabel' => ['type' => 'string', 'example' => 'Offert'],
                'totalCents' => ['type' => 'integer', 'example' => 850],
                'isEmpty' => ['type' => 'boolean', 'example' => false],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'productSlug' => ['type' => 'string', 'nullable' => true, 'example' => 'necessaire-deodorant-bio'],
                            'name' => ['type' => 'string', 'example' => 'Nécessaire, déodorant Bio'],
                            'imagePath' => ['type' => 'string', 'nullable' => true, 'example' => '/assets/images/home/product-8.jpg'],
                            'quantity' => ['type' => 'integer', 'example' => 1],
                            'unitPriceCents' => ['type' => 'integer', 'example' => 850],
                            'lineTotalCents' => ['type' => 'integer', 'example' => 850],
                        ],
                    ],
                ],
            ],
        ]);

        $schemas['CheckoutCartResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Commande validée avec succès.'],
                'reference' => ['type' => 'string', 'example' => 'GG-A1B2C3D4E5F6'],
                'validatedAt' => ['type' => 'string', 'format' => 'date-time'],
                'totalCents' => ['type' => 'integer', 'example' => 850],
            ],
        ]);

        $schemas['AccountResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'example' => 1],
                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'merchant@greengoodies.test'],
                        'firstName' => ['type' => 'string', 'example' => 'Aurelie'],
                        'lastName' => ['type' => 'string', 'example' => 'Martin'],
                    ],
                ],
                'apiAccessEnabled' => ['type' => 'boolean', 'example' => false],
                'apiKeyPrefix' => ['type' => 'string', 'nullable' => true, 'example' => 'GGK_1234567890AB'],
                'orders' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'reference' => ['type' => 'string', 'example' => 'GG-A1B2C3D4E5F6'],
                            'validatedAt' => ['type' => 'string', 'format' => 'date-time'],
                            'totalCents' => ['type' => 'integer', 'example' => 18550],
                        ],
                    ],
                ],
            ],
        ]);

        $schemas['ErrorResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Identifiants incorrects'],
            ],
        ]);

        $schemas['ValidationErrorResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Les données envoyées sont invalides.'],
                'errors' => ['type' => 'string', 'example' => 'email: This value is not a valid email address.'],
            ],
        ]);

        return $components
            ->withSchemas($schemas)
            ->withSecuritySchemes($securitySchemes);
    }

    private function jsonRequestBody(string $description, Schema $schema, bool $required = false): RequestBody
    {
        return new RequestBody(
            description: $description,
            content: new \ArrayObject([
                'application/json' => new MediaType(
                    schema: $schema,
                ),
            ]),
            required: $required,
        );
    }

    private function jsonResponse(string $description, Schema $schema): Response
    {
        return new Response(
            description: $description,
            content: new \ArrayObject([
                'application/json' => new MediaType(
                    schema: $schema,
                ),
            ]),
        );
    }

    private function refSchema(string $name): Schema
    {
        return $this->schema([
            '$ref' => sprintf('#/components/schemas/%s', $name),
        ]);
    }

    private function merchantProductsResponseSchema(): Schema
    {
        // Ce schéma documente la réponse collection custom renvoyée par la route commerçant.
        return $this->schema([
            'type' => 'object',
            'properties' => [
                '@context' => ['type' => 'string', 'example' => '/api/contexts/Product'],
                '@id' => ['type' => 'string', 'example' => '/api/merchant/products'],
                '@type' => ['type' => 'string', 'example' => 'Collection'],
                'totalItems' => ['type' => 'integer', 'example' => 9],
                'member' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            '@id' => ['type' => 'string', 'example' => '/api/products/kit-hygiene-recyclable'],
                            '@type' => ['type' => 'string', 'example' => 'Product'],
                            'slug' => ['type' => 'string', 'example' => 'kit-hygiene-recyclable'],
                            'brand' => [
                                'type' => 'object',
                                'properties' => [
                                    '@id' => ['type' => 'string', 'example' => '/api/brands/Eco%20Panda'],
                                    '@type' => ['type' => 'string', 'example' => 'Brand'],
                                    'name' => ['type' => 'string', 'example' => 'Eco Panda'],
                                ],
                            ],
                            'name' => ['type' => 'string', 'example' => "Kit d'hygiène recyclable"],
                            'shortDescription' => ['type' => 'string', 'example' => 'Pour une salle de bain eco-friendly'],
                            'description' => ['type' => 'string', 'example' => 'Description longue du produit'],
                            'priceCents' => ['type' => 'integer', 'example' => 2499],
                            'imagePath' => ['type' => 'string', 'example' => '/assets/images/home/product-1.jpg'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function schema(array $definition): Schema
    {
        $schema = new Schema();

        foreach ($definition as $key => $value) {
            $schema[$key] = $value;
        }

        return $schema;
    }
}
