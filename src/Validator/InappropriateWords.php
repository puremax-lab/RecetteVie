<?php

namespace App\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Rejects a text containing one of the forbidden words.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class InappropriateWords extends Constraint
{
    /**
     * @param list<string> $words forbidden words, compared case-insensitively
     */
    #[HasNamedArguments]
    public function __construct(
        public array $words = ['merde', 'putain', 'connard', 'imbécile', 'shit', 'fuck'],
        public string $message = 'recipe.inappropriate_word',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
