<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Language: string implements HasLabel
{
    case Hebrew = 'he';
    case English = 'en';

    public function dir(): string
    {
        return $this === self::Hebrew ? 'rtl' : 'ltr';
    }

    public function getLabel(): string
    {
        return $this === self::Hebrew ? 'Hebrew' : 'English';
    }
}
