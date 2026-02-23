<?php

namespace App\DTO;

use App\Enums\Accounting\DocumentType;
use App\Enums\Setting\Font;
use App\Models\Accounting\Document;
use App\Models\Setting\DocumentDefault;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\FontProviders\BunnyFontProvider;
use Illuminate\Contracts\Support\Htmlable;

readonly class DocumentDTO
{
    /**
     * @param  LineItemDTO[]  $lineItems
     */
    public function __construct(
        public ?string $header,
        public ?string $subheader,
        public ?string $footer,
        public ?string $terms,
        public ?string $logo,
        public string $number,
        public ?string $referenceNumber,
        public string $date,
        public string $dueDate,
        public string $currencyCode,
        public ?string $subtotal,
        public ?string $discount,
        public ?string $tax,
        public string $total,
        public ?string $amountDue,
        public CompanyDTO $company,
        public ?ClientDTO $client,
        public iterable $lineItems,
        public DocumentLabelDTO $label,
        public DocumentColumnLabelDTO $columnLabel,
        public string $accentColor = '#000000',
        public bool $showLogo = true,
        public Font $font = Font::Inter,
    ) {}

    public static function fromModel(Document $document): self
    {
        /** @var DocumentDefault $settings */
        $settings = $document->company->documentDefaults()
            ->type($document::documentType())
            ->first() ?? $document->company->defaultInvoice;

        $currencyCode = $document->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        $discount = $document->discount_total > 0
            ? self::formatToMoney($document->discount_total, $currencyCode)
            : null;

        $tax = $document->tax_total > 0
            ? self::formatToMoney($document->tax_total, $currencyCode)
            : null;

        $subtotal = ($discount || $tax)
            ? self::formatToMoney($document->subtotal, $currencyCode)
            : null;

        $amountDue = $document::documentType() !== DocumentType::Estimate ?
            self::formatToMoney($document->amountDue(), $currencyCode) :
            null;


        // Use document fields if they exist (Invoice), otherwise use defaults (Bill)
        $header = $document->header ?? $settings->header;
        $subheader = $document->subheader ?? $settings->subheader;
        $footer = $document->footer ?? $settings->footer;
        $terms = $document->terms ?? $settings->terms;

        // Get logo - try document logo_url first, then settings
        $logo = null;
        if (method_exists($document, 'getLogoUrlAttribute') || isset($document->logo_url)) {
            $logo = $document->logo_url ?? $settings->logo_url;
        } else {
            $logo = $settings->logo_url;
        }

        // Get client - works for Invoice, vendor for Bill
        $client = null;
        if (isset($document->client) && $document->client) {
            $client = ClientDTO::fromModel($document->client);
        } elseif (isset($document->vendor) && $document->vendor) {
            $client = ClientDTO::fromVendor($document->vendor);
        }

        return new self(
            header: $header,
            subheader: $subheader,
            footer: $footer,
            terms: $terms,
            logo: $logo,
            number: $document->documentNumber(),
            referenceNumber: $document->referenceNumber(),
            date: $document->documentDate(),
            dueDate: $document->dueDate(),
            currencyCode: $currencyCode,
            subtotal: $subtotal,
            discount: $discount,
            tax: $tax,
            total: self::formatToMoney($document->total, $currencyCode),
            amountDue: $amountDue,
            company: CompanyDTO::fromModel($document->company),
            client: $client,
            lineItems: $document->lineItems->map(fn($item) => LineItemDTO::fromModel($item)),
            label: $document::documentType()->getLabels(),
            columnLabel: DocumentColumnLabelDTO::fromModel($settings),
            accentColor: $settings->accent_color ?? '#000000',
            showLogo: $settings->show_logo ?? false,
            font: $settings->font ?? Font::Inter,
        );
    }

    protected static function formatToMoney(int $value, ?string $currencyCode): string
    {
        return CurrencyConverter::formatCentsToMoney($value, $currencyCode);
    }

    public function getFontHtml(): Htmlable
    {
        return app(BunnyFontProvider::class)->getHtml($this->font->getLabel());
    }
}
