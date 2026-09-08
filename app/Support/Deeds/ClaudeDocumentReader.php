<?php

namespace App\Support\Deeds;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use App\Models\PortalSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Sends a PDF/image to Claude with a system prompt and a JSON schema and
 * returns the decoded structured output. Shared by the deed and statement
 * extractors.
 */
class ClaudeDocumentReader
{
    public const SETTING_API_KEY = 'anthropic_api_key';

    public static function apiKey(): ?string
    {
        try {
            $stored = PortalSetting::get(self::SETTING_API_KEY);
            if ($stored) {
                return Crypt::decryptString($stored);
            }
        } catch (\Throwable $e) {
            // fall through to env
        }

        $env = config('services.anthropic.key');

        return $env ? (string) $env : null;
    }

    public static function model(): string
    {
        return (string) (config('services.anthropic.model') ?: 'claude-opus-5');
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{data: array<string, mixed>, model: string, input_tokens: int|null, output_tokens: int|null}
     *
     * @throws DeedExtractionException
     */
    public static function read(string $absolutePath, string $mimeType, string $system, string $instruction, array $schema, string $effort = 'medium'): array
    {
        $key = self::apiKey();
        if (! $key) {
            throw new DeedExtractionException('No Anthropic API key configured. Add one under Settings → Portal.');
        }
        if (! is_readable($absolutePath)) {
            throw new DeedExtractionException('Uploaded file could not be read.');
        }

        $data = base64_encode((string) file_get_contents($absolutePath));
        $block = $mimeType === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mimeType, 'data' => $data]];

        $client = new Client(apiKey: $key);

        try {
            $message = $client->messages->create(
                model: self::model(),
                maxTokens: 8000,
                system: [['type' => 'text', 'text' => $system]],
                messages: [['role' => 'user', 'content' => [$block, ['type' => 'text', 'text' => $instruction]]]],
                outputConfig: ['effort' => $effort, 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            );
        } catch (AuthenticationException $e) {
            throw new DeedExtractionException('Anthropic rejected the API key. Check it under Settings → Portal.', 0, $e);
        } catch (APIStatusException $e) {
            throw new DeedExtractionException('Anthropic API error ('.($e->type?->value ?? $e->getCode()).'): '.$e->getMessage(), 0, $e);
        } catch (APIConnectionException $e) {
            throw new DeedExtractionException('Could not reach the Anthropic API: '.$e->getMessage(), 0, $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new DeedExtractionException('The model declined to read this document: '.($message->stopDetails?->explanation ?? 'the request was declined'));
        }
        if ($message->stopReason === 'max_tokens') {
            throw new DeedExtractionException('The extraction was cut off before completion. Try a smaller or clearer scan.');
        }

        $json = null;
        foreach ($message->content as $contentBlock) {
            if ($contentBlock->type === 'text') {
                $json = $contentBlock->text;
                break;
            }
        }
        $decoded = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($decoded)) {
            throw new DeedExtractionException('The model returned no structured data for this document.');
        }

        return [
            'data' => $decoded,
            'model' => $message->model,
            'input_tokens' => $message->usage->inputTokens ?? null,
            'output_tokens' => $message->usage->outputTokens ?? null,
        ];
    }
}
