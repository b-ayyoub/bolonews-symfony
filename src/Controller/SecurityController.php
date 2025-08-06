<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Récupère l'erreur de connexion, s'il y en a une (ex: mauvais mot de passe)
        $error = $authenticationUtils->getLastAuthenticationError();
         // Récupère le dernier nom d'utilisateur saisi (pour le pré-remplissage du champ)
        $lastUsername = $authenticationUtils->getLastUsername();
        // Affiche la page de connexion en passant les infos au template Twig
        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    
    // Cette méthode ne sera jamais exécutée.
    // Symfony intercepte cette route automatiquement selon la config du firewall (security.yaml).
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
