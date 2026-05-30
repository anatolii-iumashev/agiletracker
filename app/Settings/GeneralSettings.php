<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public ?string $site_description;

    public string $default_locale;

    public string $date_format;

    public string $first_day_of_week;

    public int $session_timeout_days;

    public bool $auto_assign_reporter;

    public bool $enable_notifications;

    public static function group(): string
    {
        return 'general';
    }
}
