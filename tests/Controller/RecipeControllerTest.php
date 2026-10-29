<?php

namespace App\Tests\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use App\Service\RecipeTranslator;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class RecipeControllerTest extends AppWebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHomePageShowsLatestRecipesAndCategories(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cuisinez');
        self::assertSelectorCount(6, 'a.card[href*="categorie="]');
        self::assertSelectorCount(6, 'article.card');
    }

    public function testLegalNoticeIsLinkedFromTheFooter(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->filter('footer')->selectLink('Mentions légales')->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions légales');
        self::assertSelectorTextContains('article', 'alwaysdata');
    }

    public function testListLoadsNextPageOnScroll(): void
    {
        $crawler = $this->client->request('GET', '/recettes');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(9, 'article.card');
        // A lazy Turbo frame at the bottom points to page 2.
        $frame = $crawler->filter('turbo-frame#recipes-page-2');
        self::assertCount(1, $frame);
        self::assertSame('lazy', $frame->attr('loading'));
        self::assertStringEndsWith('/recettes?page=2', $frame->attr('src'));

        // Turbo requests that page with a Turbo-Frame header: only the cards come back.
        $crawler = $this->client->request('GET', '/recettes?page=2', server: ['HTTP_TURBO_FRAME' => 'recipes-page-2']);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('nav.navbar');
        self::assertCount(1, $crawler->filter('turbo-frame#recipes-page-2'));
        self::assertSelectorExists('article.card');
        // Last page: no further frame.
        self::assertSelectorNotExists('turbo-frame#recipes-page-3');
    }

    public function testInfiniteScrollKeepsFilters(): void
    {
        $crawler = $this->client->request('GET', '/recettes?q=a');

        $src = $crawler->filter('turbo-frame#recipes-page-2')->attr('src');
        self::assertStringContainsString('q=a', $src);
    }

    public function testSearchByTitleOrIngredient(): void
    {
        $this->client->request('GET', '/recettes?q=chocolat');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article.card', 'Mousse au chocolat');

        // "lait de coco" only appears in the ingredients of the curry.
        $this->client->request('GET', '/recettes?q=lait de coco');
        self::assertSelectorCount(1, 'article.card');
        self::assertSelectorTextContains('article.card', 'Curry de pois chiches');
    }

    public function testFilterByCategory(): void
    {
        $this->client->request('GET', '/recettes?categorie=boissons');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'article.card');
        self::assertSelectorTextContains('article.card', 'Citronnade');
    }

    public function testShowRecipe(): void
    {
        $recipe = $this->findRecipe('Bœuf bourguignon');
        $this->client->request('GET', \sprintf('/recettes/%s-%d', $recipe->getSlug(), $recipe->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bœuf bourguignon');
        self::assertSelectorCount(\count($recipe->getIngredientList()), 'ul.list-group li');
        // Anonymous visitors don't see the edit button.
        self::assertSelectorNotExists('a[href$="/modifier"]');
    }

    public function testWrongSlugRedirectsToCanonicalUrl(): void
    {
        $recipe = $this->findRecipe('Tarte tatin');
        $this->client->request('GET', '/recettes/mauvais-slug-'.$recipe->getId());

        self::assertResponseRedirects(\sprintf('/recettes/tarte-tatin-%d', $recipe->getId()), 301);
    }

    public function testUnknownRecipeReturns404(): void
    {
        $this->client->request('GET', '/recettes/inconnue-99999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateRequiresLogin(): void
    {
        $this->client->request('GET', '/recettes/nouvelle');

        self::assertResponseRedirects('/connexion');
    }

    public function testUnverifiedUserCannotCreate(): void
    {
        $user = $this->getUser('camille@recettevie.test');
        $user->setIsVerified(false);
        static::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/recettes/nouvelle');

        self::assertResponseRedirects('/compte');

        $user->setIsVerified(true);
        static::getContainer()->get('doctrine')->getManager()->flush();
    }

    public function testCreateRecipeWithPhoto(): void
    {
        $this->client->loginUser($this->getUser('camille@recettevie.test'));
        $crawler = $this->client->request('GET', '/recettes/nouvelle');

        $form = $crawler->selectButton('Enregistrer la recette')->form([
            'recipe_form[title]' => 'Salade niçoise',
            'recipe_form[category]' => (string) $this->findRecipe('Velouté de potimarron')->getCategory()->getId(),
            'recipe_form[duration]' => '20',
            'recipe_form[ingredients]' => "Thon\nTomates\nŒufs durs",
            'recipe_form[content]' => "Coupez les tomates.\nAjoutez le thon et les œufs.",
            // No DeepL key in tests: the author writes both languages.
            'recipe_form[titleEn]' => 'Niçoise salad',
            'recipe_form[ingredientsEn]' => "Tuna\nTomatoes\nHard-boiled eggs",
            'recipe_form[contentEn]' => "Cut the tomatoes.\nAdd the tuna and the eggs.",
        ]);
        $form['recipe_form[imageFile]']->upload($this->createTestImage());
        $this->client->submit($form);

        $recipe = $this->findRecipe('Salade niçoise');
        self::assertResponseRedirects(\sprintf('/recettes/salade-nicoise-%d', $recipe->getId()));
        self::assertSame('camille@recettevie.test', $recipe->getUser()->getEmail());
        self::assertSame('Niçoise salad', $recipe->getTitleEn());
        self::assertNull($recipe->getMachineTranslatedLocale());
        self::assertNotNull($recipe->getImageName());
        self::assertFileExists(static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/recipes/'.$recipe->getImageName());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'publiée');

        // Clean up the uploaded file and the recipe.
        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/compte');
    }

    public function testInvalidRecipeShowsErrors(): void
    {
        $this->client->loginUser($this->getUser('camille@recettevie.test'));
        $crawler = $this->client->request('GET', '/recettes/nouvelle');

        $this->client->submit($crawler->selectButton('Enregistrer la recette')->form([
            'recipe_form[title]' => 'Tarte merde',
            'recipe_form[duration]' => '10',
            'recipe_form[ingredients]' => 'Pommes',
            'recipe_form[content]' => 'Trop court',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.invalid-feedback', 'inapproprié');
    }

    public function testBothLanguagesAreRequiredWithoutAutomaticTranslation(): void
    {
        $this->client->loginUser($this->getUser('camille@recettevie.test'));
        $crawler = $this->client->request('GET', '/recettes/nouvelle');
        self::assertSelectorTextContains('.alert-light', 'Les deux versions sont obligatoires');

        // Only French, and no DeepL key in the test environment.
        $crawler = $this->client->submit($crawler->selectButton('Enregistrer la recette')->form([
            'recipe_form[title]' => 'Salade de fruits',
            'recipe_form[category]' => (string) $this->findRecipe('Mousse au chocolat')->getCategory()->getId(),
            'recipe_form[duration]' => '15',
            'recipe_form[ingredients]' => "Pommes\nPoires",
            'recipe_form[content]' => 'Coupez les fruits et mélangez-les.',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('obligatoire', $crawler->filter('#recipe_form_titleEn')->ancestors()->first()->text());
        self::assertNull($this->findRecipe('Salade de fruits'));
    }

    public function testTranslationPreviewRequiresLogin(): void
    {
        $this->client->jsonRequest('POST', '/recettes/traduction', ['source' => 'fr', 'texts' => ['title' => 'Tarte']]);

        self::assertResponseRedirects('/connexion');
    }

    public function testTranslationPreviewReturnsTextsWithoutSaving(): void
    {
        $this->useFakeDeepL();
        $this->client->loginUser($this->getUser('camille@recettevie.test'));
        $token = $this->client->request('GET', '/recettes/nouvelle')->filter('form')->attr('data-translate-token-value');
        self::assertSelectorTextContains('button[data-translate-target-param="en"]', 'Traduire en anglais');

        $this->client->jsonRequest('POST', '/recettes/traduction', [
            '_token' => $token,
            'source' => 'fr',
            'texts' => ['title' => 'Tarte aux poires', 'ingredients' => "Poires\nSucre", 'content' => ''],
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('en', $data['target']);
        self::assertSame(['title' => 'EN Tarte aux poires', 'ingredients' => "EN Poires\nEN Sucre"], $data['texts']);
        self::assertNull($this->findRecipe('Tarte aux poires'));
    }

    public function testTranslationPreviewChecksCsrfAndLimit(): void
    {
        $this->useFakeDeepL();
        $this->client->loginUser($this->getUser('camille@recettevie.test'));
        $token = $this->client->request('GET', '/recettes/nouvelle')->filter('form')->attr('data-translate-token-value');

        $this->client->jsonRequest('POST', '/recettes/traduction', ['_token' => 'invalid', 'source' => 'fr', 'texts' => ['title' => 'Tarte']]);
        self::assertResponseStatusCodeSame(403);

        // The test limit is 3 previews per hour.
        for ($i = 1; $i <= 4; ++$i) {
            $this->client->jsonRequest('POST', '/recettes/traduction', ['_token' => $token, 'source' => 'fr', 'texts' => ['title' => 'Tarte '.$i]]);
        }
        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('Limite atteinte', $this->client->getResponse()->getContent());
    }

    public function testEnglishOnlyRecipeIsTranslatedIntoFrench(): void
    {
        $this->useFakeDeepL();
        $this->client->loginUser($this->getUser('camille@recettevie.test'));
        $crawler = $this->client->request('GET', '/recettes/nouvelle');
        $this->client->submit($crawler->selectButton('Enregistrer la recette')->form([
            'recipe_form[category]' => (string) $this->findRecipe('Mousse au chocolat')->getCategory()->getId(),
            'recipe_form[duration]' => '15',
            'recipe_form[titleEn]' => 'Fruit salad',
            'recipe_form[ingredientsEn]' => "Apples\nPears",
            'recipe_form[contentEn]' => "Cut the fruit.\nMix everything.",
        ]));

        $recipe = $this->findRecipe('FR Fruit salad');
        self::assertNotNull($recipe);
        self::assertResponseRedirects(\sprintf('/recettes/%s-%d', $recipe->getSlug(), $recipe->getId()));
        self::assertSame("FR Apples\nFR Pears", $recipe->getIngredients());
        self::assertSame('fr', $recipe->getMachineTranslatedLocale());
        self::assertSame('Fruit salad', $recipe->getTitleEn());
    }

    public function testAuthorCanEdit(): void
    {
        $recipe = $this->findRecipe('Tarte tatin');
        $this->client->loginUser($recipe->getUser());

        $crawler = $this->client->request('GET', \sprintf('/recettes/%d/modifier', $recipe->getId()));
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Enregistrer la recette')->form([
            'recipe_form[duration]' => '80',
        ]));

        self::assertResponseRedirects();
        self::assertSame(80, $this->findRecipe('Tarte tatin')->getDuration());
    }

    public function testOtherUserCannotEditOrDelete(): void
    {
        $recipe = $this->findRecipe('Tarte tatin'); // written by Lucas
        $this->client->loginUser($this->getUser('camille@recettevie.test'));

        $this->client->request('GET', \sprintf('/recettes/%d/modifier', $recipe->getId()));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', \sprintf('/recettes/%d/supprimer', $recipe->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanEditAnyRecipe(): void
    {
        $recipe = $this->findRecipe('Tarte tatin');
        $this->client->loginUser($this->getUser('admin@recettevie.test'));

        $this->client->request('GET', \sprintf('/recettes/%d/modifier', $recipe->getId()));
        self::assertResponseIsSuccessful();
    }

    public function testDeleteRequiresValidCsrfToken(): void
    {
        $recipe = $this->findRecipe('Quiche lorraine');
        $this->client->loginUser($recipe->getUser());

        $this->client->request('POST', \sprintf('/recettes/%d/supprimer', $recipe->getId()), ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->findRecipe('Quiche lorraine'));
    }

    /**
     * Replaces the DeepL service by one using a mocked HTTP client: every text
     * comes back prefixed with the target language ("EN ..." or "FR ...").
     */
    private function useFakeDeepL(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);
            $prefix = 'FR' === $body['target_lang'] ? 'FR ' : 'EN ';

            return new JsonMockResponse(['translations' => array_map(static fn ($t) => ['text' => $prefix.$t], $body['text'])]);
        });

        // Keep the same kernel for every request, so the fake service is not lost.
        $this->client->disableReboot();
        static::getContainer()->set(RecipeTranslator::class, new RecipeTranslator($client, new NullLogger(), 'test-key:fx'));

        // The preview limit is stored in the cache: start every test with a fresh counter.
        $user = $this->getUser('camille@recettevie.test');
        static::getContainer()->get('limiter.deepl_preview')->create('user-'.$user->getId())->reset();
    }

    private function findRecipe(string $title): ?Recipe
    {
        $repository = static::getContainer()->get(RecipeRepository::class);
        static::getContainer()->get('doctrine')->getManager()->clear();

        return $repository->findOneBy(['title' => $title]);
    }

    private function getUser(string $email): User
    {
        return static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }

    private function createTestImage(): UploadedFile
    {
        // Smallest valid PNG (1x1 transparent pixel).
        $path = sys_get_temp_dir().'/recettevie-test.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        return new UploadedFile($path, 'salade.png', 'image/png', null, true);
    }
}
