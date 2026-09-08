<?php

namespace App\Support\Agreements;

use App\Support\Deeds\DeedExtractionException;

interface AgreementExtractor
{
    /**
     * @return array<string, mixed> normalized AgreementSchema fields
     *
     * @throws DeedExtractionException
     */
    public function extract(string $absolutePath, string $mimeType): array;

    public function isConfigured(): bool;
}
