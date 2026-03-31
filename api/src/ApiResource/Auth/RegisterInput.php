<?php

declare(strict_types=1);

namespace App\ApiResource\Auth;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Porte le payload d'inscription brut reçu du front avant transformation en entité User.
 */
final class RegisterInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['customer', 'merchant'])]
    public string $accountType = 'customer';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $lastName = '';

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 255)]
    public string $password = '';

    #[Assert\IdenticalTo(true)]
    public bool $acceptTerms = false;
}
