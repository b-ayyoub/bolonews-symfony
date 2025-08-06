<?php

namespace App\Controller;
use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(ArticleRepository $articleRepository): Response
    {
        // Récupère tous les articles en base de données
        // Classés par date de création, du plus récent au plus ancien (ordre décroissant)
        $articles = $articleRepository->findBy([], ['date_creation' => 'DESC']);
        // Rend le template Twig 'home/index.html.twig' en y passant la liste des articles
        return $this->render('home/index.html.twig', [
            'articles' => $articles,
        ]);
    }
}
