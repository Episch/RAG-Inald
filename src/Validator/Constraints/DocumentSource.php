<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that exactly one document source is provided
 * (fileContent, documentUrl, or serverPath)
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DocumentSource extends Constraint
{
    public string $message = 'You must provide exactly one of: fileContent, documentUrl, or serverPath.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

