<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'app_url',
        'timezone',
        'items_per_page',
        'default_item_priority',
    ];

    protected function casts(): array
    {
        return [
            'items_per_page' => 'integer',
        ];
    }
}
