<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Models\CommercialDocument;
use App\Models\PurchaseOrder;

final readonly class ConfirmPurchaseOrderResult
{
    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public CommercialDocument $document,
    ) {}
}
