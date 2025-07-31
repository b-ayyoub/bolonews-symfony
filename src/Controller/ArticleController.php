<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Entity\Commentaire;
use App\Form\CommentaireType;
use App\Entity\Article;
use App\Form\ArticleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ArticleController extends AbstractController
{
    #[Route('/article', name: 'app_a_article')]
    public function index(): Response
    {
        return $this->render('article/index.html.twig', [
            'controller_name' => 'ArticleController',
        ]);
    }

    #[Route('/article/{id}', name: 'article_show')]
    public function show(
        Article $article,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $commentaire = new Commentaire();
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $commentaire->setArticle($article);
            $commentaire->setAuteur($this->getUser());
            $commentaire->setDatePublication(new \DateTimeImmutable());
    
            $em->persist($commentaire);
            $em->flush();
    
            $this->addFlash('success', 'Commentaire publié avec succès !');
            return $this->redirectToRoute('article_show', ['id' => $article->getId()]);
        }
    
        return $this->render('article/show.html.twig', [
            'article' => $article,
            'formCommentaire' => $form->createView(),
        ]);
}

    #[Route('/article/new', name: 'article_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
    
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
    
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l’erreur
                }
    
                $article->setImage($newFilename);
            }
    
            $article->setAuteur($this->getUser());
            $article->setDateCreation(new \DateTimeImmutable());
    
            $em->persist($article);
            $em->flush();
    
            return $this->redirectToRoute('app_home');
        }
    
        return $this->render('article/new.html.twig', [
            'form' => $form->createView(),
    ]);

}

#[Route('/dashboard', name: 'app_dashboard')]
public function dashboard(ArticleRepository $articleRepository): Response
{
    $user = $this->getUser();

    $articles = $articleRepository->findBy(['auteur' => $user]);

    return $this->render('user/dashboard.html.twig', [
        'articles' => $articles,
    ]);
}

#[Route('/article/{id}/like', name: 'article_like')]
public function like(Article $article, EntityManagerInterface $em): Response
{
    $user = $this->getUser();

    if (!$user) {
        return $this->redirectToRoute('app_login');
    }

    // Toggle like
    if ($article->getLikedBy()->contains($user)) {
        $article->removeLikedBy($user);
    } else {
        $article->addLikedBy($user);
    }

    $em->flush();

    return $this->redirectToRoute('article_show', ['id' => $article->getId()]);
}




}