<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $prefix
 * @property int $padding
 * @property int $next_value
 */
final class DocumentSequence extends Model
{
    protected $fillable = ['key', 'prefix', 'padding', 'next_value'];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'next_value' => 'integer',
        ];
    }

    public function format(int $value): string
    {
        return $this->prefix.str_pad((string) $value, $this->padding, '0', STR_PAD_LEFT);
    }
}
