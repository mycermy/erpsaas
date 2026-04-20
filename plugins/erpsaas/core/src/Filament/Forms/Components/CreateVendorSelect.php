<?php

namespace Erpsaas\Core\Filament\Forms\Components;

use Erpsaas\Purchases\Filament\Company\Resources\Purchases\VendorResource;
use Erpsaas\Core\Models\Common\Vendor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

class CreateVendorSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->searchable()
            ->preload()
            ->createOptionForm(fn(Schema $form) => $this->createVendorForm($form))
            ->createOptionAction(fn(Action $action) => $this->createVendorAction($action));

        $this->relationship('vendor', 'name');

        $this->createOptionUsing(static function (array $data) {
            return DB::transaction(static function () use ($data) {
                $vendor = Vendor::createWithRelations($data);

                return $vendor->getKey();
            });
        });
    }

    protected function createVendorForm(Schema $form): Schema
    {
        return VendorResource::form($form);
    }

    protected function createVendorAction(Action $action): Action
    {
        return $action
            ->label('Create vendor')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Create a new vendor');
    }
}
