<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that exactly one document source is provided
 */
class DocumentSourceValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DocumentSource) {
            throw new UnexpectedTypeException($constraint, DocumentSource::class);
        }

        // Check if object has the required properties
        if (!is_object($value)) {
            return;
        }

        $fileContent = property_exists($value, 'fileContent') ? $value->fileContent : null;
        $documentUrl = property_exists($value, 'documentUrl') ? $value->documentUrl : null;
        $serverPath = property_exists($value, 'serverPath') ? $value->serverPath : null;

        // Count how many sources are provided
        $providedSources = 0;
        if ($fileContent !== null && $fileContent !== '') {
            $providedSources++;
        }
        if ($documentUrl !== null && $documentUrl !== '') {
            $providedSources++;
        }
        if ($serverPath !== null && $serverPath !== '') {
            $providedSources++;
        }

        // Exactly one source must be provided
        if ($providedSources !== 1) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}

