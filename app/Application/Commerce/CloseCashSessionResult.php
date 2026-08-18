<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Models\CashSession;
use App\Models\CommercialDocument;

final readonly class CloseCashSessionResult
{
    public function __construct(
        public CashSession $cashSession,
        public CommercialDocument $document,
    ) {}
}
