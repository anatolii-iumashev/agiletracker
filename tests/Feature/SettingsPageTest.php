<?php

declare(strict_types=1);

use App\Filament\Pages\ManageSettings;
use App\Models\User;
use App\Settings\GeneralSettings;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('admin can access settings page', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/manage-settings')
        ->assertOk();
});

test('non-admin cannot access settings page', function () {
    $user = User::factory()->create();
    Role::findOrCreate('manager');
    $user->assignRole('manager');

    $this->actingAs($user)
        ->get('/manage-settings')
        ->assertForbidden();
});

test('settings page prefills default values', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    $this->actingAs($user);

    Livewire::test(ManageSettings::class)
        ->assertSet('data.site_name', 'AgileTracker')
        ->assertSet('data.session_timeout_days', 365);
});

test('settings page saves application settings', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    $this->actingAs($user);

    Livewire::test(ManageSettings::class)
        ->fillForm([
            'site_name' => 'AgileTracker Cloud',
            'site_description' => 'Project & task management',
            'default_locale' => 'ru',
            'date_format' => 'd.m.Y',
            'first_day_of_week' => 'monday',
            'session_timeout_days' => 90,
            'auto_assign_reporter' => false,
            'enable_notifications' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = app(GeneralSettings::class);

    expect($settings->site_name)->toBe('AgileTracker Cloud')
        ->and($settings->site_description)->toBe('Project & task management')
        ->and($settings->default_locale)->toBe('ru')
        ->and($settings->date_format)->toBe('d.m.Y')
        ->and($settings->first_day_of_week)->toBe('monday')
        ->and($settings->session_timeout_days)->toBe(90)
        ->and($settings->auto_assign_reporter)->toBeFalse()
        ->and($settings->enable_notifications)->toBeFalse();
});
