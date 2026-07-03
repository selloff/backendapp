<?php

namespace App\LegacyImport;

class LegacyImportVerificationResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    public function passed(): bool
    {
        return $this->errors === [];
    }
}
