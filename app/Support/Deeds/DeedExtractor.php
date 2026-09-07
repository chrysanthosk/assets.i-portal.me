<?php

namespace App\Support\Deeds;

/**
 * Turns a scanned title deed (PDF or image) into structured data.
 * Bound in AppServiceProvider; tests swap in a fake.
 */
interface DeedExtractor
{
    /**
     * @return DeedExtraction structured fields matching DeedSchema, plus usage
     *
     * @throws DeedExtractionException when the document cannot be read or the model declines
     */
    public function extract(string $absolutePath, string $mimeType): DeedExtraction;

    /** Whether the extractor has what it needs (e.g. an API key) to run. */
    public function isConfigured(): bool;
}
