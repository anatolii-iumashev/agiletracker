<?php

namespace App\Filament\Resources\ItemResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';
    protected static ?string $title = 'Comments';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\MarkdownEditor::make('body')
                ->label('Comment (supports @mentions)')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),

                Tables\Columns\TextColumn::make('body')
                    ->markdown()
                    ->limit(120),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
