<?php

namespace Erpsaas\Core\Enums\Concerns;

trait ParsesEnum
{
    public static function parse(string | self | null $value): ?static
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof static) {
            return $value;
        }

        return static::tryFrom($value);
    }
}
