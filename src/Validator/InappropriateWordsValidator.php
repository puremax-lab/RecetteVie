<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class InappropriateWordsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof InappropriateWords) {
            throw new UnexpectedTypeException($constraint, InappropriateWords::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $text = mb_strtolower($value);
        foreach ($constraint->words as $word) {
            // Whole words only, so "Scunthorpe"-style false positives are avoided.
            if (preg_match('/(?<!\p{L})'.preg_quote(mb_strtolower($word), '/').'(?!\p{L})/u', $text)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ word }}', $word)
                    ->addViolation();

                return;
            }
        }
    }
}
