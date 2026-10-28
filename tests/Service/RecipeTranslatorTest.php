<?php

namespace App\Tests\Service;

use App\Entity\Recipe;
use App\Service\RecipeTranslator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RecipeTranslatorTest extends TestCase
{
    /** @var list<array{url: string, body: array<string, mixed>, headers: list<string>}> */
    private array $requests = [];

    public function testDisabledWithoutApiKey(): void
    {
        $client = new MockHttpClient(static fn () => throw new \LogicException('No request expected.'));
        $translator = new RecipeTranslator($client, new NullLogger(), '');

        self::assertFalse($translator->translate($this->frenchRecipe(), 'fr', 'en'));
    }

    public function testFrenchToEnglishKeepsLineBreaks(): void
    {
        $recipe = $this->frenchRecipe();

        self::assertTrue($this->translator()->translate($recipe, 'fr', 'en'));
        self::assertSame('EN Tarte aux pommes', $recipe->getTitleEn());
        self::assertSame("EN 3 pommes\nEN 1 pâte", $recipe->getIngredientsEn());
        self::assertSame("EN Préchauffez le four.\nEN Enfournez 30 minutes.", $recipe->getContentEn());
        self::assertSame('en', $recipe->getMachineTranslatedLocale());

        // Free key -> free endpoint, one request for the whole recipe, correct languages.
        self::assertCount(1, $this->requests);
        self::assertSame('https://api-free.deepl.com/v2/translate', $this->requests[0]['url']);
        self::assertContains('Authorization: DeepL-Auth-Key secret:fx', $this->requests[0]['headers']);
        self::assertSame(['FR', 'EN-GB'], [$this->requests[0]['body']['source_lang'], $this->requests[0]['body']['target_lang']]);
    }

    public function testEnglishToFrench(): void
    {
        $recipe = (new Recipe())
            ->setTitleEn('Apple pie')
            ->setIngredientsEn("3 apples\n1 pastry")
            ->setContentEn("Preheat the oven.\nBake for 30 minutes.");
        $translator = $this->translator();

        $translator->sync($recipe, $translator->snapshot(new Recipe()));

        self::assertSame('FR Apple pie', $recipe->getTitle());
        self::assertSame('fr', $recipe->getMachineTranslatedLocale());
        self::assertSame(['EN', 'FR'], [$this->requests[0]['body']['source_lang'], $this->requests[0]['body']['target_lang']]);
    }

    public function testApiErrorDoesNotThrow(): void
    {
        $client = new MockHttpClient(new MockResponse('Quota exceeded', ['http_code' => 456]));
        $translator = new RecipeTranslator($client, new NullLogger(), 'secret:fx');
        $recipe = $this->frenchRecipe();

        self::assertFalse($translator->translate($recipe, 'fr', 'en'));
        self::assertNull($recipe->getTitleEn());
        self::assertNull($recipe->getMachineTranslatedLocale());
    }

    public function testBothLanguagesWrittenByTheAuthorAreKept(): void
    {
        $translator = $this->translator();
        $recipe = $this->frenchRecipe()
            ->setTitleEn('My apple pie')
            ->setIngredientsEn("3 apples\n1 pastry")
            ->setContentEn('Bake it for half an hour.');

        $translator->sync($recipe, $translator->snapshot(new Recipe()));

        self::assertCount(0, $this->requests);
        self::assertSame('My apple pie', $recipe->getTitleEn());
        self::assertNull($recipe->getMachineTranslatedLocale());
    }

    public function testOnlyMissingFieldsAreTranslated(): void
    {
        $translator = $this->translator();
        $recipe = $this->frenchRecipe()->setTitleEn('My apple pie');

        $translator->sync($recipe, $translator->snapshot(new Recipe()));

        self::assertSame('My apple pie', $recipe->getTitleEn());
        self::assertSame("EN 3 pommes\nEN 1 pâte", $recipe->getIngredientsEn());
    }

    public function testMachineTranslationIsRefreshedOnlyWhenTheSourceChanges(): void
    {
        $translator = $this->translator();
        $recipe = $this->frenchRecipe();
        $translator->sync($recipe, $translator->snapshot(new Recipe()));
        self::assertCount(1, $this->requests);

        // Saved again without changes: no new API call.
        $translator->sync($recipe, $translator->snapshot($recipe));
        self::assertCount(1, $this->requests);

        // French title changed: the whole English version is refreshed.
        $before = $translator->snapshot($recipe);
        $recipe->setTitle('Tarte fine aux pommes');
        $translator->sync($recipe, $before);
        self::assertCount(2, $this->requests);
        self::assertSame('EN Tarte fine aux pommes', $recipe->getTitleEn());
    }

    public function testEditingTheMachineTranslationMakesItManual(): void
    {
        $translator = $this->translator();
        $recipe = $this->frenchRecipe();
        $translator->sync($recipe, $translator->snapshot(new Recipe()));

        $before = $translator->snapshot($recipe);
        $recipe->setTitleEn('Grandma\'s apple pie');
        $translator->sync($recipe, $before);

        self::assertNull($recipe->getMachineTranslatedLocale());
        self::assertSame('Grandma\'s apple pie', $recipe->getTitleEn());
        self::assertCount(1, $this->requests);
    }

    private function translator(): RecipeTranslator
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);
            $this->requests[] = ['url' => $url, 'body' => $body, 'headers' => $options['headers']];

            // Fake translation: prefix every text with the target language.
            $prefix = 'FR' === $body['target_lang'] ? 'FR ' : 'EN ';

            return new JsonMockResponse(['translations' => array_map(static fn ($t) => ['text' => $prefix.$t], $body['text'])]);
        });

        return new RecipeTranslator($client, new NullLogger(), 'secret:fx');
    }

    private function frenchRecipe(): Recipe
    {
        return (new Recipe())
            ->setTitle('Tarte aux pommes')
            ->setIngredients("3 pommes\n1 pâte")
            ->setContent("Préchauffez le four.\nEnfournez 30 minutes.");
    }
}
