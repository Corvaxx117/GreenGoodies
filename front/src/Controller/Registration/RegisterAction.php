<?php

declare(strict_types=1);

namespace App\Controller\Registration;

use App\Exception\ApiRequestException;
use App\Form\RegistrationFormType;
use App\Service\Api\GreenGoodiesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gère le scénario d'inscription en déléguant la création du compte à l'API.
 */
final class RegisterAction extends AbstractController
{
    #[Route('/inscription', name: 'front_register', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, GreenGoodiesApiClient $apiClient, FormFactoryInterface $formFactory): Response
    {
        $form = $formFactory->createNamed('', RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                // Le front transmet uniquement les données validées du formulaire à la route API dédiée.
                $apiClient->register([
                    'firstName' => $data['firstName'],
                    'lastName' => $data['lastName'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'acceptTerms' => $data['acceptTerms'],
                ]);

                $this->addFlash('success', 'Compte créé. Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('front_login');
            } catch (ApiRequestException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
