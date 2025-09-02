<?php

namespace App\Dto\Base;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Base DTO with common functionality
 */
abstract class AbstractDto
{
    /**
     * Validate this DTO and return validation errors
     */
    public function validate(): array
    {
        // This could be extended with custom validation logic
        return [];
    }

    /**
     * Convert DTO to array for API responses
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $data = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $data[$name] = $property->getValue($this);
        }
        
        return $data;
    }

    /**
     * Create DTO from array data
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();
        
        $reflection = new \ReflectionClass($instance);
        foreach ($data as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                if ($property->isPublic()) {
                    $property->setValue($instance, $value);
                }
            }
        }
        
        return $instance;
    }
}
