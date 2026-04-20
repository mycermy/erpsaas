<?php

namespace Awcodes\TableRepeater\Components;

use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Support\Enums\Width;

class TableRepeater extends Repeater
{
    protected array | Closure | null $headers = null;

    protected Width | string | Closure | null $stackAt = null;

    protected bool | Closure $streamlined = false;

    public function headers(array | Closure | null $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->evaluate($this->headers) ?? [];
    }

    public function shouldRenderHeader(): bool
    {
        return count($this->getHeaders()) > 0;
    }

    public function stackAt(Width | string | Closure | null $breakpoint): static
    {
        $this->stackAt = $breakpoint;

        return $this;
    }

    public function getStackAt(): Width | string | null
    {
        return $this->evaluate($this->stackAt) ?? Width::Medium;
    }

    public function streamlined(bool | Closure $condition = true): static
    {
        $this->streamlined = $condition;

        return $this;
    }

    public function isStreamlined(): bool
    {
        return (bool) $this->evaluate($this->streamlined);
    }
}
