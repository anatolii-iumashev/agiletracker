<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppSetting>
 */
class AppSettingFactory extends Factory
{
    protected $model = AppSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_name' => 'AgileTracker',
            'app_url' => 'http://localhost',
            'timezone' => 'UTC',
            'items_per_page' => 25,
            'default_item_priority' => 'medium',
        ];
    }
}
