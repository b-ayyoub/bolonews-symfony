<?php

namespace App\Controller;


use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Security\LoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        // Crée une nouvelle instance de l'entité User
        $user = new User();
        // Crée le formulaire d'inscription basé sur RegistrationFormType et lie l'objet $user
        $form = $this->createForm(RegistrationFormType::class, $user);
        // Gère la soumission du formulaire à partir de la requête HTTP
        $form->handleRequest($request);
        // Vérifie si le formulaire a été soumis et s'il est valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer le mot de passe saisi NON mappé
            $plainPassword = $form->get('plainPassword')->getData();
            // Hache le mot de passe avec l’algorithme sécurisé de Symfony
            $hashedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
            // Assigne le mot de passe haché à l'utilisateur
            $user->setPassword($hashedPassword);
            $user->setRoles([]);
            // Dit à Doctrine de préparer l'enregistrement de l'utilisateur
            $entityManager->persist($user);
            // Exécute la requête SQL pour enregistrer l'utilisateur en base de données
            $entityManager->flush();
            // Affiche un message flash de succès à l'utilisateur
            $this->addFlash('success', 'Votre compte a bien été créé, vous pouvez vous connecter !');
            return $this->redirectToRoute('app_login'); // Redirige vers la connexion (ou change la route)
        }
        // Si le formulaire n'a pas été soumis ou est invalide, affiche le formulaire d'inscription
        return $this->render('registration/register.html.twig', [
            // Passe la vue du formulaire au template
            'registrationForm' => $form->createView(),
        ]);
    }
}
