<?php

namespace App\Filament\Resources\Section\CategoryResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\CategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Nexus\Database\NexusDB;

class EditCategory extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ((int) ($data['mode'] ?? -1) === 0 && empty($data['icon_id'])) {
            $data['icon_id'] = 0;
        }

        return $data;
    }

    /**
     * @see functions.php::get_category_row()
     */
    protected function afterSave()
    {
        clear_category_cache();
    }
}
