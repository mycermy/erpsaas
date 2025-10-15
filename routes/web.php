<?php

use App\Http\Controllers\DocumentPrintController;
use App\Http\Middleware\AllowSameOriginFrame;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(Filament::getDefaultPanel()->getUrl());
});

Route::middleware(['auth'])->group(function () {
    Route::get('documents/{documentType}/{id}/print', [DocumentPrintController::class, 'show'])
        ->middleware(AllowSameOriginFrame::class)
        ->name('documents.print');

    // Debug route
    Route::get('/debug/inventory', function () {
        $user = Auth::user();
        $tenant = null;

        try {
            $tenant = filament()->getTenant();
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'user_id' => $user?->id,
            'user_current_company_id' => $user?->current_company_id,
            'user_companies' => $user?->companies->pluck('name', 'id'),
            'session_company_id' => session('current_company_id'),
            'filament_tenant_id' => $tenant?->id,
            'filament_tenant_name' => $tenant?->name,
            'inventory_items_without_scope' => \App\Models\Inventory\InventoryItem::withoutGlobalScopes()->count(),
            'inventory_items_with_scope' => \App\Models\Inventory\InventoryItem::count(),
            'inventory_items_list' => \App\Models\Inventory\InventoryItem::with('offering')->get()->map(fn ($item) => [
                'id' => $item->id,
                'company_id' => $item->company_id,
                'offering_name' => $item->offering->name ?? 'N/A',
                'sku' => $item->sku,
            ]),
        ]);
    });
});
