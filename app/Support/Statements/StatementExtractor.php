<?php

namespace App\Support\Statements;

use App\Support\Deeds\DeedExtractionException;

/**
 * Reads a property-management / rental statement (PDF or image) into the
 * structured fields of StatementSchema. Bound in AppServiceProvider; tests
 * swap in a fake.
 */
interface StatementExtractor
{
    /**
     * @return array<string, mixed> normalized StatementSchema fields
     *
     * @throws DeedExtractionException
     */
    public function extract(string $absolutePath, string $mimeType): array;

    public function isConfigured(): bool;
}
