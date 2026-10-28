<?php

namespace App\Tests\Validator;

use App\Validator\InappropriateWords;
use App\Validator\InappropriateWordsValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<InappropriateWordsValidator>
 */
final class InappropriateWordsValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): InappropriateWordsValidator
    {
        return new InappropriateWordsValidator();
    }

    #[DataProvider('cleanTexts')]
    public function testCleanTextIsValid(?string $text): void
    {
        $this->validator->validate($text, new InappropriateWords());

        $this->assertNoViolation();
    }

    public static function cleanTexts(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'normal title' => ['Tarte aux pommes'];
        yield 'forbidden word inside another word' => ['Une recette shitake'];
    }

    public function testForbiddenWordIsRejectedCaseInsensitively(): void
    {
        $constraint = new InappropriateWords();
        $this->validator->validate('Une tarte MERDE', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ word }}', 'merde')
            ->assertRaised();
    }

    public function testCustomWordList(): void
    {
        $constraint = new InappropriateWords(words: ['brocoli']);
        $this->validator->validate('Gratin de brocoli', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ word }}', 'brocoli')
            ->assertRaised();
    }
}
