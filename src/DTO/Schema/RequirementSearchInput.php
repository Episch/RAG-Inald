<?php

declare(strict_types=1);

namespace App\DTO\Schema;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input DTO for Semantic Requirements Search
 * 
 * Allows natural language queries to find similar requirements
 * using embeddings and vector similarity search
 */
class RequirementSearchInput
{
    #[ApiProperty(
        description: 'Natural language search query to find similar requirements. 🔍',
        example: 'Show me all authentication and security requirements'
    )]
    #[Assert\NotBlank(message: 'Search query is required.')]
    #[Assert\Type('string')]
    #[Assert\Length(
        min: 3,
        max: 500,
        minMessage: 'Query must be at least {{ limit }} characters long.',
        maxMessage: 'Query cannot be longer than {{ limit }} characters.'
    )]
    public ?string $query = null;

    #[ApiProperty(
        description: 'Maximum number of similar requirements to return (1-100). 📊',
        example: 10
    )]
    #[Assert\Type('int')]
    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: 'Limit must be between {{ min }} and {{ max }}.'
    )]
    public int $limit = 10;

    #[ApiProperty(
        description: 'Minimum similarity threshold (0.0-1.0). Only return results with similarity >= this value. 🎯',
        example: 0.7
    )]
    #[Assert\Type('float')]
    #[Assert\Range(
        min: 0.0,
        max: 1.0,
        notInRangeMessage: 'Threshold must be between {{ min }} and {{ max }}.'
    )]
    public float $minSimilarity = 0.0;

    #[ApiProperty(
        description: 'Filter by requirement type (functional, non-functional, security, performance, etc.). 🏷️',
        example: 'security'
    )]
    #[Assert\Type('string')]
    #[Assert\Choice(
        choices: ['functional', 'non-functional', 'technical', 'business', 'security', 'performance', 'usability', 'other'],
        message: 'Invalid requirement type.'
    )]
    public ?string $requirementType = null;

    #[ApiProperty(
        description: 'Filter by priority level (must, should, could, wont). 🚦',
        example: 'must'
    )]
    #[Assert\Type('string')]
    #[Assert\Choice(
        choices: ['must', 'should', 'could', 'wont'],
        message: 'Invalid priority level.'
    )]
    public ?string $priority = null;

    #[ApiProperty(
        description: 'Filter by requirement status (draft, approved, implemented, verified, rejected, obsolete). 🔄',
        example: 'approved'
    )]
    #[Assert\Type('string')]
    #[Assert\Choice(
        choices: ['draft', 'approved', 'implemented', 'verified', 'rejected', 'obsolete'],
        message: 'Invalid status.'
    )]
    public ?string $status = null;
}

