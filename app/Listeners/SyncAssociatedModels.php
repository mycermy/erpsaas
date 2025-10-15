<?php

namespace App\Listeners;

use App\Events\CompanyDefaultUpdated;
use App\Models\Setting\CompanyDefault;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class SyncAssociatedModels
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CompanyDefaultUpdated $event): void
    {
        DB::transaction(function () use ($event) {
            $this->syncAssociatedModels($event);
        }, 5);
    }

    private function syncAssociatedModels(CompanyDefaultUpdated $event): void
    {
        /** @var CompanyDefault $record */
        $record = $event->record;
        $data = $event->data;

        $record_array = array_map('strval', $record->toArray());
        $data = array_map('strval', $data);

        $diff = array_diff_assoc($data, $record_array);

        $keyToMethodMap = [
            'bank_account_id' => 'bankAccount',
        ];

        foreach ($diff as $key => $value) {
            if (! array_key_exists($key, $keyToMethodMap)) {
                continue;
            }

            $method = $keyToMethodMap[$key];

            // Safely attempt to get relation. Some models/methods may not return a relation
            try {
                $relation = $record->$method();
            } catch (\Throwable $e) {
                // If the method exists but throws, skip updating associated models
                continue;
            }

            // Only proceed if we have a BelongsTo relation instance
            if (! $relation instanceof BelongsTo) {
                continue;
            }

            $this->updateEnabledStatus($relation, $value);
        }
    }

    private function updateEnabledStatus(BelongsTo $relation, $newValue): void
    {
        // Ensure relation can be resolved and returns a model
        try {
            if ($relation->exists()) {
                $previousDefault = $relation->getResults();

                if ($previousDefault !== null) {
                    $previousDefault->update(['enabled' => false]);
                }
            }
        } catch (\Throwable $e) {
            // If resolving the relation fails, skip safely
        }

        if ($newValue !== null) {
            try {
                $newDefault = $relation->getRelated()->newQuery()->where($relation->getOwnerKeyName(), $newValue)->first();
                $newDefault?->update(['enabled' => true]);
            } catch (\Throwable $e) {
                // If lookup fails, ignore silently
            }
        }
    }
}
