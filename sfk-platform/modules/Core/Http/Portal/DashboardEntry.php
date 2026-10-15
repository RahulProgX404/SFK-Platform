<?php

declare(strict_types=1);

namespace Modules\Core\Http\Portal;

final readonly class DashboardEntry
{
    public function __construct(
        public string $id,
        public string $label,
        public string $icon,
        public string $url,
    ) {}
}
