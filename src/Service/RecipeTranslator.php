<?php

namespace App\Service;

use App\Entity\Recipe;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fills the missing language of a recipe (French -> English or English -> French)
 * with the DeepL API.
 *
 * Without DEEPL_API_KEY, or when the API fails, nothing is translated and the
 * form validation asks the author to write the other language.
 */
final class RecipeTranslator
{
    // DeepL accepts at most 50 texts per request.
    private const BATCH_SIZE = 50;

    private const DEEPL_LANGUAGES = [
        'fr' => ['source' => 'FR', 'target' => 'FR'],
        'en' => ['source' => 'EN', 'target' => 'EN-GB'],
    ];

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
     * @return array{fr: array<string, ?string>, en: array<string, ?string>}
     */
    public function snapshot(Recipe $recipe): array
    {
        return ['fr' => $recipe->getTexts('fr'), 'en' => $recipe->getTexts('en')];
    }

    /**
     * Called when the recipe form is submitted, before validation.
     *
     * @param array{fr: array<string, ?string>, en: array<string, ?string>} $before result of snapshot() before submission
     */
    public function sync(Recipe $recipe, array $before): void
    {
        $after = $this->snapshot($recipe);
        $machine = $recipe->getMachineTranslatedLocale();

        // The author edited the machine-translated version: it is theirs now.
        if (null !== $machine && $after[$machine] !== $before[$machine]) {
            $recipe->setMachineTranslatedLocale(null);
            $machine = null;
        }

        // Translate from the language written by the author (never from a machine translation).
        $directions = 'fr' === $machine ? [['en', 'fr'], ['fr', 'en']] : [['fr', 'en'], ['en', 'fr']];

        foreach ($directions as [$source, $target]) {
            if (!$recipe->isComplete($source)) {
                continue;
            }

            // A machine translation is refreshed entirely when its source text changed.
            $fields = $machine === $target && $after[$source] !== $before[$source]
                ? Recipe::TEXT_FIELDS
                : $recipe->getMissingFields($target);

            if ([] !== $fields) {
                $this->translate($recipe, $source, $target, $fields);
            }

            return;
        }
    }

    /**
     * @param list<string> $fields fields of the target language to fill
     *
     * @return bool false when disabled or when the API call failed
     */
    public function translate(Recipe $recipe, string $source, string $target, array $fields = Recipe::TEXT_FIELDS): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // Each line is sent as its own text so line breaks (one ingredient/step per line) are kept.
        $lines = [];
        foreach ($fields as $field) {
            $lines[$field] = 'title' === $field
                ? [(string) $recipe->getText($field, $source)]
                : Recipe::splitLines($recipe->getText($field, $source));
        }

        try {
            $translated = [];
            foreach (array_chunk(array_merge(...array_values($lines)), self::BATCH_SIZE) as $batch) {
                array_push($translated, ...$this->callApi($batch, $source, $target));
            }
        } catch (ExceptionInterface|\UnexpectedValueException $e) {
            $this->logger->warning('DeepL translation failed: {message}', ['message' => $e->getMessage()]);

            return false;
        }

        foreach ($lines as $field => $fieldLines) {
            $recipe->setText($field, $target, implode("\n", array_splice($translated, 0, \count($fieldLines))));
        }
        $recipe->setMachineTranslatedLocale($target);

        return true;
    }

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    private function callApi(array $texts, string $source, string $target): array
    {
        $data = $this->httpClient->request('POST', $this->endpoint(), [
            'headers' => ['Authorization' => 'DeepL-Auth-Key '.$this->apiKey],
            'json' => [
                'text' => $texts,
                'source_lang' => self::DEEPL_LANGUAGES[$source]['source'],
                'target_lang' => self::DEEPL_LANGUAGES[$target]['target'],
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
