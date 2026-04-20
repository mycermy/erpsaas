@php
    $document = \Erpsaas\Core\DTO\DocumentDTO::fromModel($getRecord());
    $template = $getTemplate();
    $preview = $isPreview();
@endphp

{!! $document->getFontHtml() !!}

<style>
    .doc-template-paper {
        font-family: '{{ $document->font->getLabel() }}', sans-serif;
    }
</style>

<div {{ $attributes->class(['min-w-0']) }}>
    @include("filament.company.components.document-templates.{$template->value}", [
        'document' => $document,
        'preview' => $preview,
    ])
</div>
