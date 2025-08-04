<?php

// Déclare le namespace de ton contrôleur. Doit refléter l'emplacement du fichier.
namespace App\Controller;

// Import des classes dont tu as besoin :
use App\Repository\ArticleRepository;                     // Pour interroger les articles
use App\Entity\Commentaire;                               // L'entité Commentaire
use App\Form\CommentaireType;                             // Le formulaire Symfony lié aux commentaires
use App\Entity\Article;                                   // L'entité Article
use App\Form\ArticleType;                                 // Le formulaire pour créer/modifier un article
use Doctrine\ORM\EntityManagerInterface;                  // Pour gérer les entités avec Doctrine ORM
use Symfony\Component\HttpFoundation\Request;             // Pour accéder aux requêtes HTTP (GET, POST...)
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; // Classe de base pour tous les contrôleurs Symfony
use Symfony\Component\HttpFoundation\Response;            // Pour retourner une réponse HTTP
use Symfony\Component\Routing\Attribute\Route;            // Pour définir les routes avec attributs PHP 8+
use Symfony\Component\HttpFoundation\File\Exception\FileException; // Pour gérer les erreurs d'upload de fichiers
use Symfony\Component\String\Slugger\SluggerInterface; 
   // Pour générer un nom de fichier unique et lisible

// Déclare une classe de contrôleur finale (= non héritée ailleurs)
final class ArticleController extends AbstractController
{
    // Route vers /article (non utilisée dans ton projet actuel, peut servir à lister les articles)
    #[Route('/article', name: 'app_a_article')]
    public function index(): Response
    {
        // Affiche juste un template par défaut
        return $this->render('article/index.html.twig', [
            'controller_name' => 'ArticleController',
        ]);
    }

    // Route pour afficher un article et ses commentaires, + formulaire d'ajout de commentaire
    #[Route('/article/show/{id}', name: 'article_show')]
    public function show(
        Article $article,              // Symfony injecte directement l'article par son id
        Request $request,             // Pour gérer les données envoyées par l'utilisateur
        EntityManagerInterface $em    // Pour interagir avec la base de données
    ): Response {
        // Crée un nouvel objet Commentaire vide
        $commentaire = new Commentaire();

        // Crée le formulaire pour cet objet
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request); // Traite la requête HTTP (soumission ou pas)

        // Si le formulaire a été soumis ET est valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Lie le commentaire à l'article courant
            $commentaire->setArticle($article);
            // Définit l'auteur du commentaire (utilisateur connecté)
            $commentaire->setAuteur($this->getUser());
            // Définit la date de publication du commentaire
            $commentaire->setDatePublication(new \DateTime());

            // Prépare l’enregistrement en base
            $em->persist($commentaire);
            $em->flush(); // Exécute les requêtes

            // Message de confirmation + redirection (Post/Redirect/Get)
            $this->addFlash('success', 'Commentaire publié avec succès !');

            return $this->redirectToRoute('article_show', ['id' => $article->getId()]);
        }

        // Affiche l’article, ses commentaires et le formulaire
        return $this->render('article/show.html.twig', [
            'article' => $article,
            'formCommentaire' => $form->createView(),
        ]);
    }

    // Route pour ajouter un nouvel article (formulaire)
    #[Route('/article/new', name: 'article_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        // Création d’un nouvel article vide
        $article = new Article();

        // Génère le formulaire ArticleType
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupère le fichier image du formulaire
            $imageFile = $form->get('image')->getData();

            // S’il y a bien un fichier envoyé
            if ($imageFile) {
                // Génère un nom de fichier unique basé sur le nom original
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    // Déplace le fichier envoyé vers le dossier /public/uploads/images
                    $imageFile->move(
                        $this->getParameter('images_directory'), // Défini dans services.yaml
                        $newFilename
                    );
                } catch (FileException $e) {
                    // À toi de capturer ou afficher un message d'erreur si besoin
                }

                // Enregistre le nom du fichier image dans l’article
                $article->setImage($newFilename);
            }

            // Attribue l’auteur + la date du jour
            $article->setAuteur($this->getUser());
            $article->setDateCreation(new \DateTimeImmutable());

            // Sauvegarde dans la base
            $em->persist($article);
            $em->flush();

            // Redirige vers la page d’accueil après création
            return $this->redirectToRoute('app_home');
        }

        // Affiche le formulaire s’il n’est pas encore soumis
        return $this->render('article/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

        // Dashboard privé : montre les articles de l’utilisateur connecté
        #[Route('/dashboard', name: 'app_dashboard')]
        public function dashboard(ArticleRepository $articleRepository): Response
        {
            // Récupère l'utilisateur actuellement connecté
            $user = $this->getUser();

            // Récupère tous ses articles (findBy auteur)
            $articles = $articleRepository->findBy(['auteur' => $user]);

            // Envoie à la vue dashboard
            return $this->render('user/dashboard.html.twig', [
                'articles' => $articles,
            ]);
    }

        // Système de like (ajoute ou enlève un like pour un article)
        #[Route('/article/{id}/like', name: 'article_like')]
        public function like(Article $article, EntityManagerInterface $em): Response
        {
            // Vérifie si l'utilisateur est connecté
            $user = $this->getUser();
            if (!$user) {
                return $this->redirectToRoute('app_login');
            }

            // Si l'article est déjà liké par l'utilisateur → on retire
            if ($article->getLikedBy()->contains($user)) {
                $article->removeLikedBy($user);
            } else {
                // Sinon on ajoute le like
                $article->addLikedBy($user);
            }

            // Enregistrement en base
            $em->flush();

            // Retourne à l’article
            return $this->redirectToRoute('article_show', ['id' => $article->getId()]);
        }

        // Supprimer un commentaire spécifique
        #[Route('/commentaire/{id}/delete', name: 'commentaire_delete', methods: ['POST'])]
        public function delete(Commentaire $commentaire, EntityManagerInterface $em, Request $request): Response
        {
            // Vérifie que l'utilisateur est bien connecté
            $user = $this->getUser();

            // Autorise uniquement l’auteur du commentaire à le supprimer
            if (!$user || $user !== $commentaire->getAuteur()) {
                throw $this->createAccessDeniedException("Tu ne peux pas supprimer ce commentaire.");
            }

            // Vérifie le token CSRF pour autoriser la suppression
            if ($this->isCsrfTokenValid('delete_commentaire_' . $commentaire->getId(), $request->request->get('_token'))) {
                // Supprime le commentaire de la base
                $em->remove($commentaire);
                $em->flush();
                $this->addFlash('success', 'Commentaire supprimé.');
            }

            // Redirige vers la page de l’article
            return $this->redirectToRoute('article_show', ['id' => $commentaire->getArticle()->getId()]);
        }

    // Page d'accueil avec moteur de recherche intégré
        #[Route('/', name: 'app_home')]
        public function home(ArticleRepository $repo, Request $request): Response
        {
            // Récupère ce que l’utilisateur a tapé dans le champ de recherche (GET ?q=)
            $q = $request->query->get('q');

            // Si une recherche est en cours
            if ($q) {
                // Création d'une requête personnalisée avec recherche sur titre, catégorie ou pseudo
                $articles = $repo->createQueryBuilder('a')
                // Jointure avec la table User en relation "auteur" aliasée 'u'
                    ->leftJoin('a.auteur', 'u')
                    // Condition où soit le titre, soit la catégorie, soit le pseudo de l'auteur contient la recherche 
                    ->andWhere('LOWER(a.titre) LIKE :search OR LOWER(a.categorie) LIKE :search OR LOWER(u.pseudo) LIKE :search')
                    // Définit la valeur du paramètre "search" en entourant la recherche par des % pour recherche partielle
                    ->setParameter('search', '%' . strtolower($q) . '%')
                    // Trie les résultats par date de création décroissante (du plus récent au plus ancien)
                    ->orderBy('a.dateCreation', 'DESC')
                    // Prépare la requête finale (objet Query)
                    ->getQuery()
                    // Exécute la requête et récupère tous les résultats sous forme de tableau d'objets Article
                    ->getResult();
            // Si aucun mot clé n’est précisé (pas de recherche), récupère tous les articles sauf ceux en brouillon
            } else {
                // Sinon, affiche tous les articles par date descendante
                $articles = $repo->createQueryBuilder('a')
                // Exclut les articles dont la catégorie est 'brouillon' (donc les dépubliés)
                ->andWhere('a.categorie != :brouillon')
                // Définit la valeur du paramètre 'brouillon'
                ->setParameter('brouillon', 'brouillon')
                // Trie les articles par date de création décroissante
                ->orderBy('a.date_creation', 'DESC')
                // Prépare la requête
                ->getQuery()
                // Exécute la requête et récupère les résultats
                ->getResult();
            }

            // Envoie les articles filtrés (ou non) à la page d’accueil
            return $this->render('home/index.html.twig', [
                'articles' => $articles,
                'search' => $q,
            ]);
        }

    #[Route('/article/{id}/delete', name: 'article_delete', methods: ['POST'])]
    public function deleteArticle(int $id, ArticleRepository $repo, EntityManagerInterface $em, Request $request): Response
    // Déclare une route HTTP POST pour l'URL /article/{id}/delete avec le nom 'article_delete'.
    // Cette méthode sera appelée quand un formulaire POST à cette URL sera soumis.

    {
        // Cherche en base l'article correspondant à l'id passé en paramètre
        $article = $repo->find($id);
        // Récupère l'utilisateur actuellement connecté (null si pas connecté)
        $user = $this->getUser();


        // Vérifie que l'article existe ET que l'utilisateur est soit l'auteur, soit un admin
        if (!$article || ($user !== $article->getAuteur() && !in_array('ROLE_ADMIN', $user->getRoles()))) {
            // Sinon, l'accès est refusé avec une exception 403 (forbidden)
            throw $this->createAccessDeniedException();
        }
        
        // Vérifie que le token CSRF envoyé dans le formulaire est valide pour prévenir les attaques CSRF
        if ($this->isCsrfTokenValid('delete_article_' . $article->getId(), $request->request->get('_token'))) {
            
            // Marque l'article pour suppression dans Doctrine
            $em->remove($article);
            // Exécute la suppression en base de données
            $em->flush();
            // Ajoute un message flash de succès qui sera affiché à l'utilisateur
            $this->addFlash('success', 'Article supprimé.');
        }
        // Redirige vers la page d'accueil une fois la suppression réalisée (ou annulée)
        return $this->redirectToRoute('app_home');
    }
    
    
    #[Route('/recherche', name: 'app_search')] 
    // Définit la route URL '/recherche' accessible via le nom 'app_search'
    public function search(ArticleRepository $repo, Request $request): Response
    {
        $q = $request->query->get('q'); 
        // Récupère la valeur du paramètre GET 'q' (le mot-clé de recherche) depuis l’URL
    
        $articles = []; 
        // Initialise une variable $articles en tableau vide pour stocker les résultats
    
        if ($q) { 
            // Vérifie si un mot-clé a été saisi (différent de vide ou null)
            
            $articles = $repo->createQueryBuilder('a') 
                // Crée un constructeur de requête Doctrine pour l’entité Article (alias 'a')
                
                ->leftJoin('a.auteur', 'u') 
                // Effectue une jointure à gauche avec la relation 'auteur' (alias 'u') pour chercher dans la table User
                
                ->andWhere('LOWER(a.titre) LIKE :search OR LOWER(a.categorie) LIKE :search OR LOWER(u.pseudo) LIKE :search')
                // Ajoute une condition où le titre, la catégorie ou le pseudo de l’auteur contiennent la chaîne recherchée,
                // On utilise LOWER() pour rendre la recherche insensible à la casse
                
                ->setParameter('search', '%' . strtolower($q) . '%') 
                // Enregistre la valeur de recherche, avec wildcards % pour rechercher n’importe quelle partie du texte,
                // On met aussi la chaîne en minuscules
                
                ->orderBy('a.date_creation', 'DESC') 
                // Trie les résultats selon la date de création, du plus récent au plus ancien
                
                ->getQuery() 
                // Prépare la requête SQL finale
                
                ->getResult(); 
                // Exécute la requête et retourne les articles correspondants sous forme d’un tableau d’objets Article
        }
    
        return $this->render('article/search.html.twig', [ 
            // Rend la vue Twig 'article/search.html.twig' en lui passant des variables
            'articles' => $articles,  // le tableau des articles trouvés (vide si pas de recherche)
            'search' => $q,           // la chaîne recherchée pour affichage dans la page
        ]);
    }

        #[Route('/article/{id}/edit', name: 'article_edit', methods: ['GET', 'POST'])]
        public function edit(Article $article, Request $request, EntityManagerInterface $em): Response
        {
            // Crée un formulaire lié à l'article existant
            $form = $this->createForm(ArticleType::class, $article);

            // Traite la requête HTTP (submit)
            $form->handleRequest($request);

            // Si le formulaire est soumis et valide
            if ($form->isSubmitted() && $form->isValid()) {
                // Mets à jour la date de modification
                $article->setDateModification(new \DateTimeImmutable());

                // Enregistre les modifications
                $em->flush();

                // Message de confirmation
                $this->addFlash('success', 'Article modifié avec succès.');

                // Redirige vers la page détail de l’article
                return $this->redirectToRoute('article_show', ['id' => $article->getId()]);
            }

            // Affiche le formulaire
            return $this->render('article/edit.html.twig', [
                'form' => $form->createView(),
                'article' => $article,
            ]);
        }


    // Cette annotation définit la route HTTP pour accéder à cette méthode :
    // URL avec un paramètre `id` qui correspond à l'article,
    // nom de la route = "article_toggle_publish",
    // et la méthode HTTP acceptée est POST uniquement.
    // Vérifier que l'utilisateur est connecté
    #[Route('/article/{id}/toggle-publish', name: 'article_toggle_publish', methods: ['POST'])]
    public function togglePublish(Article $article, EntityManagerInterface $em, Request $request): Response
    {
        // Vérification que l'utilisateur est connecté// Vérifie si un utilisateur est connecté / authentifié
        if (!$this->getUser()) {
            // Si aucune session utilisateur, redirige vers la page de connexion 'app_login'
            return $this->redirectToRoute('app_login');
        }

        // Si le token est invalide, stoppe l'exécution et génère une erreur d’accès refusé
        if (!$this->isCsrfTokenValid('toggle_publish_' . $article->getId(), $request->request->get('_token'))) {
                // Bascule le statut de publication de l'article
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Bascule du statut de publication
        // Si la catégorie est 'brouillon', on passe à 'public' (article publié)
        if ($article->getCategorie() === 'brouillon') {
            $article->setCategorie('public');
        } else {
            // Sinon, on passe à 'brouillon' (article dépublié)
            $article->setCategorie('brouillon');
        }
        // Applique les changements sur la base de données
        $em->flush();

        return $this->redirectToRoute('app_home');
    }


}
