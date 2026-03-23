<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\LoginFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Affiche le formulaire de connexion du front.
 */
final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'front_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, FormFactoryInterface $formFactory): Response
    {
        // Un utilisateur déjà connecté côté front n'a pas besoin de repasser par le formulaire.
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('front_home');
        }

        $form = $formFactory->createNamed('', LoginFormType::class, [
            'email' => $authenticationUtils->getLastUsername(),
        ]);

        return $this->render('security/login.html.twig', [
            'loginForm' => $form,
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }
}
