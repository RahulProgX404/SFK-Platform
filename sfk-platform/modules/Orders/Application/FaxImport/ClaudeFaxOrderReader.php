<?php

declare(strict_types=1);

namespace Modules\Orders\Application\FaxImport;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use Modules\Orders\Domain\FaxImport\FaxImportRules;
use Throwable;

/**
 * Reads a faxed purchase order with the Claude API (Anthropic PHP SDK) on the server — the legacy tool called it from
 * the browser with the key in the page. Model, effort and key: config orders.fax_import (ANTHROPIC_API_KEY). Refused
 * requests go to the API's default fallback model (server-side fallbacks). The answer must be the JSON of
 * FaxOrderPrompt; errors become FaxImportFailed with the legacy messages.
 */
final class ClaudeFaxOrderReader implements FaxOrderReader
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /** @param  array<string, mixed>  $config  orders.fax_import */
    public function __construct(private readonly array $config) {}

    public function read(FaxDocument $document): array
    {
        return $this->ask($document, FaxOrderPrompt::extraction($this->rules(), $this->readerCompany()));
    }

    public function rereadInspectionStamp(FaxDocument $document, array $firstReading, array $candidates): array
    {
        // one new request (no replayed assistant turn): the instructions, the first reading and what to check again
        $prompt = FaxOrderPrompt::extraction($this->rules(), $this->readerCompany())
            ."\n\n## 先ほどの抽出結果\n".json_encode($firstReading, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ."\n\n".FaxOrderPrompt::inspectionStampRetry($this->rules(), $candidates);

        return $this->ask($document, $prompt);
    }

    /** @return array<string, mixed> */
    private function ask(FaxDocument $document, string $prompt): array
    {
        $key = (string) ($this->config['api_key'] ?? '');
        if ($key === '') {
            throw new FaxImportFailed('AIのAPIキーが設定されていません。管理者に .env の ANTHROPIC_API_KEY を設定してもらってください。');
        }

        $source = ['type' => 'base64', 'mediaType' => $document->mediaType, 'data' => $document->base64];

        try {
            $message = (new Client(apiKey: $key))->beta->messages->create(
                maxTokens: (int) ($this->config['max_tokens'] ?? 16000),
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => $document->isPdf() ? 'document' : 'image', 'source' => $source],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
                model: (string) ($this->config['model'] ?? 'claude-opus-5-5'),
                fallbacks: 'default',
                outputConfig: ['effort' => (string) ($this->config['effort'] ?? 'low')],
                thinking: ['type' => 'adaptive'],
                betas: [self::FALLBACK_BETA],
            );
        } catch (AuthenticationException) {
            throw new FaxImportFailed('APIキーが正しくありません。管理者に .env の ANTHROPIC_API_KEY を確認してもらってください。');
        } catch (RateLimitException $e) {
            $wait = (int) ($e->response?->getHeaderLine('retry-after') ?? '');
            throw new FaxImportFailed('APIの利用上限に達しました。'.($wait > 0 ? "約{$wait}秒待ってから" : 'しばらく待ってから').'再実行してください。');
        } catch (BadRequestException $e) {
            throw new FaxImportFailed('リクエストが拒否されました: '.self::apiMessage($e));
        } catch (InternalServerException $e) {
            throw new FaxImportFailed("AI側で一時的なエラーが発生しました（{$e->status}）。再実行してください。");
        } catch (APIStatusException $e) {
            throw new FaxImportFailed(trim("AI APIエラー: {$e->status} ".self::apiMessage($e)));
        } catch (APIConnectionException) {
            throw new FaxImportFailed('AIに接続できませんでした。しばらく待ってから再実行してください。');
        }

        if ($message->stopReason === 'refusal') {
            $category = $message->stopDetails?->category;
            throw new FaxImportFailed('AIが解析を拒否しました（'.($category ?? '理由不明').'）。別の画像でお試しください。');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                $text .= $block->text;
            }
        }

        return self::json(trim($text));
    }

    /**
     * The JSON object of the answer (a code fence or text around it is tolerated).
     *
     * @return array<string, mixed>
     */
    public static function json(string $text): array
    {
        if ($text === '') {
            throw new FaxImportFailed('AIから有効な応答が得られませんでした。');
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/u', $text, $fenced) === 1) {
            $text = trim($fenced[1]);
        }
        if (! str_starts_with($text, '{')) {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new FaxImportFailed('AIの応答をJSONとして解釈できませんでした。');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function rules(): FaxImportRules
    {
        return FaxImportRules::fromConfig($this->config);
    }

    private function readerCompany(): string
    {
        return (string) ($this->config['reader_company'] ?? '');
    }

    private static function apiMessage(Throwable $e): string
    {
        $body = $e instanceof APIStatusException ? $e->body : null;
        $message = is_array($body) ? (($body['error'] ?? [])['message'] ?? null) : null;

        return is_string($message) ? $message : '';
    }
}
