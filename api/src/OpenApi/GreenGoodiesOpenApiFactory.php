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

final readonly class GreenGoodiesOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

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

        if ($productsPath = $paths->getPath('/api/products')) {
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
                            'imagePath' => ['type' => 'string', 'example' => 'assets/images/home/product-1.jpg'],
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
