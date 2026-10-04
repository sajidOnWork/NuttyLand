<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductResource::approveAllergensAction(),
            DeleteAction::make()->modalDescription('Products with sales or orders cannot be deleted – set them to Inactive instead.'),
        ];
    }
}
