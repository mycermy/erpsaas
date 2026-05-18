<?php

use Erpsaas\Core\Http\Controllers\DocumentPrintController;
use Erpsaas\Core\Http\Middleware\AllowSameOriginFrame;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(Filament::getDefaultPanel()->getUrl());
});

Route::middleware(['auth'])->group(function () {
    Route::get('documents/{documentType}/{id}/print', [DocumentPrintController::class, 'show'])
        ->middleware(AllowSameOriginFrame::class)
        ->name('documents.print');

    Route::get('documents/{documentType}/{id}/pdf', [DocumentPrintController::class, 'pdf'])
        ->name('documents.pdf');
});
