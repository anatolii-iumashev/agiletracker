<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'General';

    protected static ?int $navigationSort = 99;

    protected static string $settings = GeneralSettings::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && static::canAccess();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Site name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('site_description')
                            ->label('Site description')
                            ->maxLength(255)
                            ->helperText('Shown on the login page.'),
                    ])
                    ->columns(1),
                Section::make('Display')
                    ->schema([
                        Select::make('default_locale')
                            ->label('Default language')
                            ->required()
                            ->options([
                                'en' => 'English',
                                'ru' => 'Русский',
                            ]),
                        Select::make('date_format')
                            ->label('Date format')
                            ->required()
                            ->options([
                                'Y-m-d' => 'ISO (2026-05-30)',
                                'd.m.Y' => 'dd.mm.yyyy (30.05.2026)',
                                'm/d/Y' => 'mm/dd/yyyy (05/30/2026)',
                            ]),
                        Select::make('first_day_of_week')
                            ->label('First day of week')
                            ->required()
                            ->options([
                                'monday' => 'Monday',
                                'sunday' => 'Sunday',
                            ]),
                    ])
                    ->columns(2),
                Section::make('Behavior')
                    ->schema([
                        TextInput::make('session_timeout_days')
                            ->label('Session timeout (days)')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(3650),
                        Toggle::make('auto_assign_reporter')
                            ->label('Auto-assign reporter as creator')
                            ->helperText('When creating an item, automatically set the reporter to the current user.'),
                        Toggle::make('enable_notifications')
                            ->label('Enable notifications'),
                    ])
                    ->columns(1),
            ]);
    }
}
