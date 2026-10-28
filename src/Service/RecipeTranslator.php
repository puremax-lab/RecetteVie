<?php

namespace App\Service;

use App\Entity\Recipe;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fills the English version of a recipe with the DeepL API.
 *
 * Without DEEPL_API_KEY the feature is simply disabled, and any API error
 * is logged without blocking the save: the French text is then shown.
 */
final class RecipeTranslator
{
    // DeepL accepts at most 50 texts per request.
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(DEEPL_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== trim($this->apiKey);
    }

    /**
     * Texts of the recipe before the form is submitted, to compare afterwards.
     *
     * @return array{fr: list<?string>, en: list<?string>}
     */
    public function snapshot(Recipe $recipe): array
    {
        return [
            'fr' => [$recipe->getTitle(), $recipe->getIngredients(), $recipe->getContent()],
            'en' => $recipe->getEnglishFields(),
        ];
    }

    /**
     * Called after the recipe form is submitted.
     *
     * @param array{fr: list<?string>, en: list<?string>} $before result of snapshot() before the form was submitted
     */
    public function updateTranslation(Recipe $recipe, array $before): void
    {
        $after = $this->snapshot($recipe);

        // The author wrote or edited the English text: it is theirs now.
        if ($after['en'] !== $before['en']) {
            $recipe->setAutoTranslated(false);
        }

        $needsTranslation = !$recipe->hasEnglishVersion()
            // A machine translation is refreshed when the French text changed.
            || ($recipe->isAutoTranslated() && $after['fr'] !== $before['fr']);

        if ($needsTranslation) {
            $this->translate($recipe);
        }
    }

    /**
     * @return bool false when disabled or when the API call failed
     */
    public function translate(Recipe $recipe): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // Each line is sent as its own text so line breaks (one ingredient/step per line) are kept.
        $ingredients = $recipe->getIngredientList();
        $steps = $recipe->getSteps();
        $texts = [(string) $recipe->getTitle(), ...$ingredients, ...$steps];

        try {
            $translated = [];
            foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
                array_push($translated, ...$this->callApi($batch));
            }
        } catch (ExceptionInterface|\UnexpectedValueException $e) {
            $this->logger->warning('DeepL translation failed: {message}', ['message' => $e->getMessage()]);

            return false;
        }

        $recipe
            ->setTitleEn(array_shift($translated))
            ->setIngredientsEn(implode("\n", \array_slice($translated, 0, \count($ingredients))))
            ->setContentEn(implode("\n", \array_slice($translated, \count($ingredients))))
            ->setAutoTranslated(true);

        return true;
    }

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    private function callApi(array $texts): array
    {
        $data = $this->httpClient->request('POST', $this->endpoint(), [
            'headers' => ['Authorization' => 'DeepL-Auth-Key '.$this->apiKey],
            'json' => [
                'text' => $texts,
                'source_lang' => 'FR',
                'target_lang' => 'EN-GB',
            ],
            'timeout' => 10,
        ])->toArray();

        $translations = array_column($data['translations'] ?? [], 'text');
        if (\count($translations) !== \count($texts)) {
            throw new \UnexpectedValueException('Unexpected DeepL response.');
        }

        return $translations;
    }

    private function endpoint(): string
    {
        // Free API keys end with ":fx" and use a different host.
        return str_ends_with($this->apiKey, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';
    }
}
