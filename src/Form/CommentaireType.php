<?php

namespace App\Form;

use App\Entity\Commentaire;
use App\Entity\article;
use App\Entity\user;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommentaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Le builder permet de construire chaque champ du formulaire
        $builder
        // On ajoute un champ "contenu" → ce sera un champ de type <textarea>
        ->add('contenu', TextareaType::class, [
            // On désactive le label affiché automatiquement (pas de texte "Contenu :" au-dessus du champ)
            'label' => false,
            // On personnalise les attributs HTML du champ <textarea>
            'attr' => [
                // Le placeholder s'affiche à l'intérieur du champ tant que rien n'a été tapé
                'placeholder' => 'Ton commentaire...',
                // Définit le nombre de lignes visibles dans le champ (hauteur)
                'rows' => 5
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commentaire::class,
        ]);
    }
}
