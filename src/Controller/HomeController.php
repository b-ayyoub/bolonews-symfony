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
        // Récupère tous les articles, triés du plus récent au plus ancien
        $articles = $articleRepository->findBy([], ['date_creation' => 'DESC']);

        return $this->render('home/index.html.twig', [
            'articles' => $articles,
        ]);
    }
}
