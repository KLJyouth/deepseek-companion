<?php
namespace Traits;

trait InputValidation 
{
    protected function validateRequired(array $data): void
    {
        foreach ($this->required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Field {$field} is required");
            }
        }
    }

    protected function validateRules(array $data): void
    {
        foreach ($data as $field => $value) {
            if (!isset($this->rules[$field])) {
                continue;
            }

            $rule = $this->rules[$field];
            $this->validateType($field, $value, $rule);
            $this->validateLength($field, $value, $rule);
            $this->validateEnum($field, $value, $rule);
        }
    }

    private function validateType(string $field, mixed $value, array $rule): void
    {
        if (!isset($rule['type'])) {
            return;
        }

        $valid = match($rule['type']) {
            'string' => is_string($value),
            'int' => is_int($value),
            'array' => is_array($value),
            default => true
        };

        if (!$valid) {
            throw new \InvalidArgumentException("Field {$field} must be type {$rule['type']}");
        }
    }

    private function validateLength(string $field, mixed $value, array $rule): void
    {
        if (isset($rule['max']) && strlen((string)$value) > $rule['max']) {
            throw new \InvalidArgumentException("Field {$field} exceeds max length");
        }
        if (isset($rule['min']) && strlen((string)$value) < $rule['min']) {
            throw new \InvalidArgumentException("Field {$field} below min length"); 
        }
    }

    private function validateEnum(string $field, mixed $value, array $rule): void
    {
        if (isset($rule['enum']) && !in_array($value, $rule['enum'], true)) {
            throw new \InvalidArgumentException("Invalid value for field {$field}");
        }
    }
}
