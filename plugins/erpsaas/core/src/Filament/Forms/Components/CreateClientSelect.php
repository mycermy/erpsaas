<?php

namespace Erpsaas\Core\Filament\Forms\Components;

use Erpsaas\Sales\Filament\Company\Resources\Sales\ClientResource;
use Erpsaas\Core\Models\Common\Client;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

class CreateClientSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->searchable()
            ->preload()
            ->createOptionForm(fn(Schema $form) => $this->createClientForm($form))
            ->createOptionAction(fn(Action $action) => $this->createClientAction($action));

        $this->relationship('client', 'name');

        $this->createOptionUsing(static function (array $data) {
            return DB::transaction(static function () use ($data) {
                $client = Client::createWithRelations($data);

                return $client->getKey();
            });
        });
    }

    protected function createClientForm(Schema $form): Schema
    {
        return ClientResource::form($form);
    }

    protected function createClientAction(Action $action): Action
    {
        return $action
            ->label('Create client')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Create a new client');
    }
}
