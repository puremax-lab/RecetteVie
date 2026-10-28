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
    public function testDisabledWithoutApiKey(): void
    {
        $client = new MockHttpClient(static fn () => throw new \LogicException('No request expected.'));
        $translator = new RecipeTranslator($client, new NullLogger(), '');

        self::assertFalse($translator->translate($this->recipe()));
    }

    public function testTranslatesEachLineAndKeepsLineBreaks(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['url' => $url, 'options' => $options];
            $texts = json_decode($options['body'], true)['text'];

            // Fake translation: prefix every text with "EN ".
            return new JsonMockResponse(['translations' => array_map(static fn ($t) => ['text' => 'EN '.$t], $texts)]);
        });
        $translator = new RecipeTranslator($client, new NullLogger(), 'secret:fx');
        $recipe = $this->recipe();

        self::assertTrue($translator->translate($recipe));
        self::assertSame('EN Tarte aux pommes', $recipe->getTitleEn());
        self::assertSame("EN 3 pommes\nEN 1 pâte", $recipe->getIngredientsEn());
        self::assertSame("EN Préchauffez le four.\nEN Enfournez.", $recipe->getContentEn());
        self::assertTrue($recipe->isAutoTranslated());

        // Free API key -> free endpoint, a single request for the whole recipe.
        self::assertCount(1, $requests);
        self::assertSame('https://api-free.deepl.com/v2/translate', $requests[0]['url']);
        self::assertContains('Authorization: DeepL-Auth-Key secret:fx', $requests[0]['options']['headers']);
    }

    public function testApiErrorDoesNotBlockTheSave(): void
    {
        $client = new MockHttpClient(new MockResponse('Quota exceeded', ['http_code' => 456]));
        $translator = new RecipeTranslator($client, new NullLogger(), 'secret:fx');
        $recipe = $this->recipe();

        self::assertFalse($translator->translate($recipe));
        self::assertNull($recipe->getTitleEn());
        self::assertFalse($recipe->isAutoTranslated());
    }

    public function testEnglishWrittenByTheAuthorIsKept(): void
    {
        $translator = $this->fakeTranslator($calls);
        $recipe = $this->recipe();
        $before = $translator->snapshot($recipe);

        $recipe->setTitleEn('My apple pie');
        $translator->updateTranslation($recipe, $before);

        self::assertSame(0, $calls);
        self::assertSame('My apple pie', $recipe->getTitleEn());
        self::assertFalse($recipe->isAutoTranslated());
    }

    public function testMachineTranslationIsRefreshedOnlyWhenFrenchChanges(): void
    {
        $translator = $this->fakeTranslator($calls);
        $recipe = $this->recipe();

        $translator->updateTranslation($recipe, $translator->snapshot((new Recipe())));
        self::assertSame(1, $calls);

        // Saved again without changes: no new API call.
        $translator->updateTranslation($recipe, $translator->snapshot($recipe));
        self::assertSame(1, $calls);

        // French title changed: the machine translation is refreshed.
        $before = $translator->snapshot($recipe);
        $recipe->setTitle('Tarte fine aux pommes');
        $translator->updateTranslation($recipe, $before);
        self::assertSame(2, $calls);
        self::assertSame('EN Tarte fine aux pommes', $recipe->getTitleEn());
    }

    private function fakeTranslator(?int &$calls): RecipeTranslator
    {
        $calls = 0;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls) {
            ++$calls;
            $texts = json_decode($options['body'], true)['text'];

            return new JsonMockResponse(['translations' => array_map(static fn ($t) => ['text' => 'EN '.$t], $texts)]);
        });

        return new RecipeTranslator($client, new NullLogger(), 'secret:fx');
    }

    private function recipe(): Recipe
    {
        return (new Recipe())
            ->setTitle('Tarte aux pommes')
            ->setIngredients("3 pommes\n1 pâte")
            ->setContent("Préchauffez le four.\nEnfournez.");
    }
}
