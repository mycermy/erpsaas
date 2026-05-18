<?php

namespace Erpsaas\Core\Http\Controllers;

use Barryvdh\Snappy\Facades\SnappyPdf;
use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Estimate;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Accounts\Models\Accounting\RecurringInvoice;
use Erpsaas\Core\DTO\DocumentDTO;
use Erpsaas\Core\Enums\Accounting\DocumentType;
use Erpsaas\Core\Enums\Setting\Template;
use Erpsaas\Core\Models\Setting\DocumentDefault;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPrintController extends Controller
{
    protected array $documentModels = [
        'invoice' => Invoice::class,
        'recurring_invoice' => RecurringInvoice::class,
        'estimate' => Estimate::class,
        'bill' => Bill::class,
    ];

    public function show(Request $request, string $documentType, int $id)
    {
        if (! isset($this->documentModels[$documentType])) {
            abort(404, "Invalid document type: {$documentType}");
        }

        $modelClass = $this->documentModels[$documentType];
        $document = $modelClass::findOrFail($id);
        $documentTypeEnum = $document::documentType();

        if ($documentTypeEnum === DocumentType::RecurringInvoice) {
            $documentTypeEnum = DocumentType::Invoice;
        }

        $defaults = DocumentDefault::query()
            ->type($documentTypeEnum)
            ->first();

        $template = $defaults?->template ?? Template::Default;
        $document = DocumentDTO::fromModel($document);

        return view('print-document', [
            'document' => $document,
            'template' => $template,
        ]);
    }

    public function pdf(Request $request, string $documentType, int $id): StreamedResponse
    {
        if (! isset($this->documentModels[$documentType])) {
            abort(404, "Invalid document type: {$documentType}");
        }

        $modelClass = $this->documentModels[$documentType];
        $document = $modelClass::findOrFail($id);
        $documentTypeEnum = $document::documentType();

        if ($documentTypeEnum === DocumentType::RecurringInvoice) {
            $documentTypeEnum = DocumentType::Invoice;
        }

        $defaults = DocumentDefault::query()
            ->type($documentTypeEnum)
            ->first();

        $template = $defaults?->template ?? Template::Default;
        $documentDTO = DocumentDTO::fromModel($document);

        $filename = $documentTypeEnum->getLabel() . ' #' . $document->documentNumber() . '.pdf';

        $pdf = SnappyPdf::loadView('print-document', [
            'document' => $documentDTO,
            'template' => $template,
        ])->setOption('enable-local-file-access', true);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->inline();
        }, $filename);
    }
}
