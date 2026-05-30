<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_description', null);
        $this->migrator->add('general.default_locale', 'en');
        $this->migrator->add('general.date_format', 'Y-m-d');
        $this->migrator->add('general.first_day_of_week', 'monday');
        $this->migrator->add('general.session_timeout_days', 365);
        $this->migrator->add('general.auto_assign_reporter', true);
    }
};
