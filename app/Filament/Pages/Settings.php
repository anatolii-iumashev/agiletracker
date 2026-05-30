<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AppSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static bool $isDiscovered = false;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'General';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament-panels::pages.page';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill($this->getFormDefaults());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->schema([
                        TextInput::make('app_name')
                            ->label('Application name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('app_url')
                            ->label('Application URL')
                            ->url()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('timezone')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->columns(2),
                Section::make('Item defaults')
                    ->schema([
                        Select::make('default_item_priority')
                            ->required()
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ]),
                        TextInput::make('items_per_page')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(10)
                            ->maxValue(100),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save settings')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }

    public function save(): void
    {
        $settings = AppSetting::query()->firstOrNew();
        $settings->fill($this->form->getState());
        $settings->save();

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFormDefaults(): array
    {
        $settings = AppSetting::query()->first();

        if ($settings !== null) {
            return Arr::only($settings->attributesToArray(), [
                'app_name',
                'app_url',
                'timezone',
                'items_per_page',
                'default_item_priority',
            ]);
        }

        return [
            'app_name' => config('app.name'),
            'app_url' => (string) config('app.url'),
            'timezone' => (string) config('app.timezone', 'UTC'),
            'items_per_page' => 25,
            'default_item_priority' => 'medium',
        ];
    }
}
