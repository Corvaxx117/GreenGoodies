<?php

declare(strict_types=1);

namespace App\ApiResource\Account;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Auth\RegisterInput;
use App\ApiResource\Shared\MessageResource;
use App\ApiState\Account\ActivateApiKeyProcessor;
use App\ApiState\Account\CurrentUserViewProvider;
use App\ApiState\Account\DeactivateApiKeyProcessor;
use App\ApiState\Account\DeleteCurrentUserProcessor;
use App\ApiState\Auth\RegisterUserProcessor;
use App\Entity\User;

/**
 * Expose les opérations API liées au compte courant sans exposer directement l'entité Doctrine User.
 */
#[ApiResource(
    shortName: 'CurrentUser',
    operations: [
        new Post(
            uriTemplate: '/register',
            input: RegisterInput::class,
            output: self::class,
            read: false,
            processor: RegisterUserProcessor::class,
            security: "is_granted('PUBLIC_ACCESS')",
            openapi: new OpenApiOperation(
                tags: ['Users'],
                summary: 'Créer un compte',
                description: 'Inscription d’un utilisateur GreenGoodies.',
                security: [],
            ),
        ),
        new Get(
            uriTemplate: '/me',
            output: self::class,
            provider: CurrentUserViewProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Users'],
                summary: 'Récupérer le profil courant',
                description: 'Retourne les informations du compte authentifié côté front.',
                security: [['JWT' => []]],
            ),
        ),
        new Delete(
            uriTemplate: '/me',
            read: false,
            deserialize: false,
            validate: false,
            output: MessageResource::class,
            status: 200,
            processor: DeleteCurrentUserProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new OpenApiOperation(
                tags: ['Users'],
                summary: 'Supprimer le compte courant',
                description: 'Supprime le compte authentifié ainsi que ses données associées.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/me/api-key/activate',
            read: false,
            input: false,
            output: ActivateApiKeyResult::class,
            processor: ActivateApiKeyProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
            openapi: new OpenApiOperation(
                tags: ['Merchant API'],
                summary: 'Activer la clé API commerçant',
                description: 'Active l’accès API commerçant et retourne la clé API en clair une seule fois.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/me/api-key/deactivate',
            read: false,
            input: false,
            output: MessageResource::class,
            processor: DeactivateApiKeyProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
            openapi: new OpenApiOperation(
                tags: ['Merchant API'],
                summary: 'Désactiver la clé API commerçant',
                description: 'Désactive l’accès API commerçant du compte authentifié.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final class CurrentUserView
{
    public int $id;

    public string $email;

    public string $firstName;

    public string $lastName;

    /**
     * @var list<string>
     */
    public array $roles = [];

    public bool $apiAccessEnabled = false;

    public ?string $apiKeyPrefix = null;

    /**
     * Construit la vue publique du compte à partir de l'utilisateur authentifié.
     */
    public static function fromUser(User $user): self
    {
        $view = new self();
        $view->id = $user->getId() ?? 0;
        $view->email = $user->getEmail();
        $view->firstName = $user->getFirstName();
        $view->lastName = $user->getLastName();
        $view->roles = $user->getRoles();
        $view->apiAccessEnabled = $user->isApiAccessEnabled();
        $view->apiKeyPrefix = $user->getApiKey()?->getKeyPrefix();

        return $view;
    }
}
