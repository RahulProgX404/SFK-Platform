<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use Closure;
use Livewire\ComponentHook;
use Modules\Core\Http\HtmlIndentation;

/**
 * Every Livewire component's HTML without its indentation (HtmlIndentation) — on the page and in each update (sorting,
 * paging, searching a list sends its whole table again), so what the browser compares stays alike.
 */
final class StripsIndentation extends ComponentHook
{
    /**
     * @param  mixed  $view
     * @param  mixed  $data
     */
    public function render($view, $data): Closure
    {
        return function (string $html, callable $replaceHtml): void {
            $replaceHtml(HtmlIndentation::strip($html));
        };
    }
}
