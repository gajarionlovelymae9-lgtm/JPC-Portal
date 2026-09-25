<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;

interface DataQualityRuleInterface
{
    public function getCode(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getSeverity(): DataQualityIssueSeverity;

    public function supports(string $entityType): bool;

    /**
     * Executes the validation rule on the entity.
     *
     * @param mixed $entity
     * @return array|null Returns array of issue details if violation found, null if clean.
     */
    public function check(mixed $entity): ?array;
}
