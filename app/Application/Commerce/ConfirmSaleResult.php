<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Models\CommercialDocument;
use App\Models\Payment;
use App\Models\Sale;

final readonly class ConfirmSaleResult
{
    public function __construct(
        public Sale $sale,
        public Payment $payment,
        public CommercialDocument $document,
    ) {}
}
