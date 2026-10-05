<?php

namespace App\Models\Concerns;

trait StoresUtcDates
{
    // Laravel serializes date casts as UTC. Normalize offset-bearing inputs before SQL storage too.
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->copy()->utc()->format($this->getDateFormat());
    }
}
