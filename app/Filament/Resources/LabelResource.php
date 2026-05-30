<?php

namespace App\Filament\Resources;

use App\Models\Label;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LabelResource extends Resource
{
    protected static ?string $model = Label::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(50),

            Forms\Components\ColorPicker::make('color')
                ->default('#6366f1'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color'),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Resources\LabelResource\Pages\ListLabels::route('/'),
            'create' => \App\Filament\Resources\LabelResource\Pages\CreateLabel::route('/create'),
            'edit'   => \App\Filament\Resources\LabelResource\Pages\EditLabel::route('/{record}/edit'),
        ];
    }
}
