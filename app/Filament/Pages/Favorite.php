<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\ItemResource;
use App\Models\Favorite as FavoriteModel;
use App\Models\Item;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Favorite extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static UnitEnum|string|null $navigationGroup = 'My';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament-panels::pages.page';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => FavoriteModel::query()
                ->where('user_id', auth()->id())
                ->latest(),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                Tables\Columns\TextColumn::make('type_badge')
                    ->label('Type')
                    ->badge()
                    ->state(fn (FavoriteModel $record): string => match (true) {
                        $record->url !== null => 'Link',
                        $record->favoritable_type !== null => class_basename($record->favoritable_type),
                        default => 'Unknown',
                    })
                    ->colors([
                        'success' => 'Link',
                        'primary' => 'Item',
                        'warning' => 'User',
                    ]),

                Tables\Columns\TextColumn::make('details')
                    ->label('Details')
                    ->state(fn (FavoriteModel $record): string => $record->url
                        ? parse_url($record->url, PHP_URL_HOST) ?? $record->url
                        : (string) ($record->favoritable?->title ?? $record->favoritable?->name ?? '—'))
                    ->url(fn (FavoriteModel $record): ?string => match (true) {
                        $record->url !== null => $record->url,
                        $record->favoritable_type === Item::class => ItemResource::getUrl('view', ['record' => $record->favoritable_id]),
                        $record->favoritable_type === User::class => null,
                        default => null,
                    })
                    ->openUrlInNewTab(fn (FavoriteModel $record): bool => $record->url !== null),
            ])
            ->actions([
                DeleteAction::make(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addFavorite')
                ->label('Add to favorites')
                ->icon('heroicon-o-plus')
                ->schema([
                    Select::make('kind')
                        ->label('Type')
                        ->options([
                            'link' => 'External link',
                            'model' => 'Internal object',
                        ])
                        ->required()
                        ->live()
                        ->default('link'),

                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('url')
                        ->label('URL')
                        ->url()
                        ->required()
                        ->visible(fn (Get $get): bool => $get('kind') === 'link'),

                    Select::make('favoritable_type')
                        ->label('Object type')
                        ->options([
                            Item::class => 'Item',
                            User::class => 'User',
                        ])
                        ->required()
                        ->visible(fn (Get $get): bool => $get('kind') === 'model')
                        ->live(),

                    Select::make('favoritable_id')
                        ->label('Object')
                        ->required()
                        ->visible(fn (Get $get): bool => $get('kind') === 'model')
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search, Get $get): array {
                            $type = $get('favoritable_type');

                            if (! $type || ! class_exists($type)) {
                                return [];
                            }

                            $field = $type === Item::class ? 'title' : 'name';

                            return $type::where($field, 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($model): array => [
                                    $model->id => ($model->title ?? $model->name).' ['.class_basename($type).']',
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function (?int $value, Get $get): ?string {
                            if (! $value) {
                                return null;
                            }

                            $type = $get('favoritable_type');

                            if (! $type || ! class_exists($type)) {
                                return null;
                            }

                            $model = $type::find($value);

                            return $model ? (string) ($model->title ?? $model->name) : null;
                        }),
                ])
                ->action(function (array $data): void {
                    $favorite = new FavoriteModel;
                    $favorite->user_id = auth()->id();
                    $favorite->name = $data['name'];

                    if ($data['kind'] === 'link') {
                        $favorite->url = $data['url'];
                    } else {
                        $favorite->favoritable_type = $data['favoritable_type'];
                        $favorite->favoritable_id = $data['favoritable_id'];
                    }

                    $favorite->save();
                })
                ->successNotificationTitle('Added to favorites'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
