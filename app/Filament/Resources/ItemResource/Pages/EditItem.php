<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\Resources\ItemResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('saveAndView')
                ->label('Save and view')
                ->color('success')
                ->action('saveAndView'),
            ...parent::getFormActions(),
        ];
    }

    public function saveAndView(): void
    {
        $this->save();

        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
    }

    public function saveAndDelete(): void
    {
        $this->save();

        $record = $this->getRecord();

        $record->delete();

        $this->redirect($this->getResource()::getUrl('index'));
    }
}
