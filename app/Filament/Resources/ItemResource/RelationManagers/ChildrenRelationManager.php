<?php

namespace App\Filament\Resources\ItemResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';
    protected static ?string $title = 'Sub-items';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->options([
                    'task' => 'Task',
                    'epic' => 'Epic',
                    'case' => 'Case',
                ])
                ->required()
                ->default('task'),

            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('status')
                ->options([
                    'todo'        => 'To Do',
                    'in_progress' => 'In Progress',
                    'review'      => 'Review',
                    'done'        => 'Done',
                ])
                ->default('todo'),

            Forms\Components\Select::make('priority')
                ->options([
                    'low'      => 'Low',
                    'medium'   => 'Medium',
                    'high'     => 'High',
                    'critical' => 'Critical',
                ])
                ->default('medium'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'project',
                        'warning' => 'epic',
                        'success' => 'task',
                        'gray'    => 'case',
                    ]),

                Tables\Columns\TextColumn::make('title')->searchable()->limit(50),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'todo',
                        'warning' => 'in_progress',
                        'info'    => 'review',
                        'success' => 'done',
                    ]),

                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'gray'    => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger'  => 'critical',
                    ]),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
