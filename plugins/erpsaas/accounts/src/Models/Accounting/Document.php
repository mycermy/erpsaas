<?php

namespace Erpsaas\Accounts\Models\Accounting;

use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Core\Enums\Accounting\DocumentType;
use Erpsaas\Core\Models\Setting\Currency;
use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Livewire\Component;

abstract class Document extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function lineItems(): MorphMany
    {
        return $this->morphMany(DocumentLineItem::class, 'documentable')->orderBy('line_number');
    }

    public function hasLineItems(): bool
    {
        return $this->lineItems()->exists();
    }

    public function hasInactiveAdjustments(): bool
    {
        return $this->lineItems->contains(function (DocumentLineItem $lineItem) {
            return $lineItem->adjustments->contains(function (Adjustment $adjustment) {
                return $adjustment->isInactive();
            });
        });
    }

    /**
     * Returns a Filament action for downloading the document as PDF.
     */
    public static function getPdfDocumentAction(string $action = Action::class): MountableAction
    {
        return $action::make('downloadPdf')
            ->label('PDF')
            ->icon('heroicon-m-arrow-down-tray')
            ->action(function (self $record) {
                $url = route('documents.pdf', [
                    'documentType' => $record::documentType()->value,
                    'id' => $record->id,
                ]);

                // Open PDF in new tab or trigger download
                return redirect()->away($url);
            });
    }

    public static function getPrintDocumentAction(string $action = Action::class): MountableAction
    {
        return $action::make('printPdf')
            ->label('Print')
            ->icon('heroicon-m-printer')
            ->action(function (self $record, Component $livewire) {
                $url = route('documents.print', [
                    'documentType' => $record::documentType()->value,
                    'id' => $record->id,
                ]);

                $livewire->js("window.printPdf('{$url}', '{$record::documentType()->getLabel()} #{$record->documentNumber()}'); ");
            });
    }

    abstract public static function documentType(): DocumentType;

    abstract public function documentNumber(): ?string;

    abstract public function documentDate(): ?string;

    abstract public function dueDate(): ?string;

    abstract public function referenceNumber(): ?string;

    abstract public function amountDue(): ?string;
}
