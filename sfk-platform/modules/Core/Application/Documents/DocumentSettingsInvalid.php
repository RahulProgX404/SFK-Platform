<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use RuntimeException;

/** A 帳票テンプレート or 出力設定 could not be saved; `errors` are the messages by input (name, background, …). */
final class DocumentSettingsInvalid extends RuntimeException
{
    /** @param  array<string, string>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    public static function of(string $input, string $message): self
    {
        return new self([$input => $message]);
    }
}
