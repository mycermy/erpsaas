<?php

namespace Erpsaas\Core\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;

use Filament\Forms\Components\TextInput;

class DocumentHeaderSection extends Section
{
    protected string | Closure | null $defaultHeader = null;

    protected string | Closure | null $defaultSubheader = null;

    public function defaultHeader(string | Closure | null $header): static
    {
        $this->defaultHeader = $header;

        return $this;
    }

    public function defaultSubheader(string | Closure | null $subheader): static
    {
        $this->defaultSubheader = $subheader;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->collapsible();
        $this->collapsed();

        $this->schema([
            \Filament\Schemas\Components\Grid::make(['md' => 2])->schema([
                Group::make([
                    FileUpload::make('logo')
                        ->maxSize(1024)
                        ->localizeLabel()
                        ->openable()
                        ->directory('logos/document')
                        ->image()
                        ->imageCropAspectRatio('3:2')
                        ->panelAspectRatio('3:2')
                        ->panelLayout('compact')
                        ->extraAttributes([
                            'class' => 'es-file-upload document-logo',
                        ])
                        ->loadingIndicatorPosition('left')
                        ->removeUploadedFileButtonPosition('right'),
                ]),
                Group::make([
                    TextInput::make('header')
                        ->default(fn () => $this->getDefaultHeader()),
                    TextInput::make('subheader')
                        ->default(fn () => $this->getDefaultSubheader()),
                ])->grow(true),
            ]),
        ]);
    }

    public function getDefaultHeader(): ?string
    {
        return $this->evaluate($this->defaultHeader);
    }

    public function getDefaultSubheader(): ?string
    {
        return $this->evaluate($this->defaultSubheader);
    }
}
