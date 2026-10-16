<?php

declare(strict_types=1);

namespace Modules\Core\Application\Views;

use RuntimeException;

/** A 一覧 could not be saved; `errors` are the messages by input (name, columns, dateField, titleField). */
final class ListViewInvalid extends RuntimeException
{
    /** @param  array<string, string>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
