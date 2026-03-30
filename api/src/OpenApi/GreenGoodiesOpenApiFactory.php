<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\Schema;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Complète la documentation générée par API Platform avec le login JWT natif et les schémas de sécurité globaux.
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

        $openApi = $openApi->withComponents($this->buildComponents($openApi->getComponents()));

        $paths = $openApi->getPaths();

        // La route de login JWT reste externe à API Platform et doit donc être documentée manuellement.
        $paths->addPath('/auth', new PathItem(
            post: new Operation(
                operationId: 'postAuthToken',
                tags: ['Authentication'],
                summary: 'Obtenir un JWT',
                description: 'Retourne un JWT utilisable par le front pour les routes protégées de l’API.',
                requestBody: $this->jsonRequestBody(
                    'Identifiants de connexion.',
                    $this->refSchema('AuthTokenRequest'),
                    true,
                ),
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
                security: [],
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

        $schemas['ErrorResponse'] = $this->schema([
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Identifiants incorrects'],
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

    private function schema(array $definition): Schema
    {
        $schema = new Schema();

        foreach ($definition as $key => $value) {
            $schema[$key] = $value;
        }

        return $schema;
    }
}
