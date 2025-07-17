<?php

namespace App\Controller;

use App\Repository\RecipeRepository;
use App\Entity\Recipe;
use App\Form\RecipeForm;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RecipeController extends AbstractController
{
    #[Route("/recette", name: "app_recipe_index")]
    public function index(Request $request, RecipeRepository $repository, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        if ($this->getUser()){
            /**
             * @var User $user
             */
            $user = $this->getUser();
            if (!$user->isVerified()) {
                $this->addFlash("infos", $translator->trans("recipeController.emailNotVerified"));
            }
        }
        // return new Response
        // ("Bienvenue dans la page des recettes !");

        $recipes = $repository->findAll();
        // $recipes = $repository->findRecipeDurationLowerThan(60);

        // $recipe = new Recipe();
        // $recipe->setTitle("Pizza 4 fromages")
        //     ->setSlug("pizza-4-fromages")
        //     ->setContent("Préparation
        //         pour Pizza 4 fromages : la vraie recette traditionnelle italienne
        //         Préparation : 15 min
        //         Cuisson : 12 min
        //         1. Râpez le parmesan et la fontina et coupez en morceaux la mozzarella et le gorgonzola.

        //         2. Étalez la pâte à pizza sur une plaque allant au four et recouverte de papier sulfurisé. Ajoutez le parmesan, la fontina, la mozzarella et le gorgonzola.

        //         3. Faites cuire dans le bas du four à 230 degrés pendant 12 à 14 minutes.")
        //     ->setDuration(27)
        //     ->setCreatedAt(new \DateTimeImmutable())
        //     ->setUpdatedAt(new \DateTimeImmutable());
        //     $em->persist($recipe);
        //     $em->flush();

        // $recipes[3]->setTitle("Omelette au Fromage")
        //     ->setSlug("omelette-au-fromage");
        // $em->flush();

        // $em->remove($recipes[5]);
        // $em->flush();
        
        // dd($recipes);
        return $this->render("recipe/index.html.twig", [
            "recipes" => $recipes
        ]);
    }

    #[Route('/recette/{slug}-{id}', name: 'app_recipe_show', requirements: ["id"=> "\d+", "slug"=> "[a-z0-9\-]+"])]
    public function show(Request $request, string $slug, int $id, RecipeRepository $repository): Response
    {
        // dd($request);
        // dd($request->attributes->get('slug') ,$request->attributes->get('id'));
        // dd($slug, $id);

        // return new Response
        // ("Bienvenue dans la page ".$request->query->get ("recette","des recettes !"));

        // ("Recette numéro ". $id . " : " . $slug);

        // return new JsonResponse([
        //     'id' => $id,
        //     'slug' => $slug,
        // ]);
        // return $this->json([
        //     'id' => $id,
        //     'slug' => $slug,
        // ]);

        $recipe = $repository->find($id);
        // dd($recipe);
        if ($recipe->getSlug() !== $slug) {
            return $this->redirectToRoute("app_recipe_show", [
                "slug" => $recipe->getSlug(), 
                "id" => $recipe->getId()]);
        }
        return $this->render("recipe/show.html.twig", [
            "recipe" => $recipe
            // "user" => [
            //     "firstname" => "Maxime",
            //     "lastname" => "Youta"
            // ]
        ]);
    }
    
    #[Route(path: "/recette/{id}/edit", name: "app_recipe_edit")]
    public function edit(Recipe $recipe, Request $request, EntityManagerInterface $em) : Response{
        if ($this->getUser()) {
            if (!$this->getUser()->isVerified()) {
                $this->addFlash("error", "You must confirm your email to edit Recipe !");
                return $this->redirectToRoute("app_recipe_index");
            }else if ($recipe->getUser()->getEmail() !== $this->getUser()->getEmail()) {
                $this->addFlash("error", "You must be the user". $recipe->getUser()->getEmail() . " to edit this Recipe !");
                return $this->redirectToRoute("app_recipe_index");
            }
        } else {
            $this->addFlash("error", "You must login to edit a Recipe !");
            return $this->redirectToRoute("app_login");
        }
        // dd($recipe);
        // cette méthode est appelée si l'id de la recette existe
        $form = $this->createForm(RecipeForm::class, $recipe);
        $form->handleRequest($request);
        // dd($recipe);
        if ($form->isSubmitted() && $form->isValid()) {
            $recipe->setUpdatedAt(new DateTimeImmutable());
            $em->flush();
            $this->addFlash("success", "La recette a bien été modifiée");
            return $this->redirectToRoute("app_recipe_index");
        }
        return $this->render("recipe/edit.html.twig", [
            "recipe" => $recipe,
            "monForm" => $form
        ]);
    }

    #[Route(path: "/recette/create", name: "app_recipe_create")]
    public function create(Request $request, EntityManagerInterface $em) : Response{
        if ($this->getUser()) {
            if (!$this->getUser()->isVerified()) {
                $this->addFlash("error", "You must confirm your email to create Recipe !");
                return $this->redirectToRoute("app_recipe_index");
            }
        } else {
            $this->addFlash("error", "You must login to create a Recipe !");
            return $this->redirectToRoute("app_login");
        }
        $recipe = new Recipe;
        $form = $this->createForm(RecipeForm::class, $recipe);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $recipe->setUser($this->getUser());
            $recipe->setCreatedAt(new DateTimeImmutable());
            $recipe->setUpdatedAt(new DateTimeImmutable());
            $em->persist($recipe);
            $em->flush();
            $this->addFlash("success", "La recette ". $recipe->getTitle() . " a bien été crée");
            return $this->redirectToRoute("app_recipe_index");
        }
        return $this->render("recipe/create.html.twig", [
            "form" => $form
        ]);
    }
        #[Route(path: "/recette/{id}/delete", name: "app_recipe_delete")]
        public function delete(Recipe $recipe, EntityManagerInterface $em) : Response{
        if ($this->getUser()) {
            if (!$this->getUser()->isVerified()) {
                $this->addFlash("error", "You must confirm your email to delete Recipe !");
                return $this->redirectToRoute("app_recipe_index");
            }else if ($recipe->getUser()->getEmail() !== $this->getUser()->getEmail()) {
                $this->addFlash("error", "You must be the user". $recipe->getUser()->getEmail() . " to edit this Recipe !");
                return $this->redirectToRoute("app_recipe_index");
            }
        } else {
            $this->addFlash("error", "You must login to delete a Recipe !");
            return $this->redirectToRoute("app_login");
        }
            $titre = $recipe->getTitle();
            $em->remove($recipe);
            $em->flush();
            $this->addFlash("info", "La recette ". $titre . " a bien été supprimée");
            return $this->redirectToRoute("app_recipe_index");
        }
    }