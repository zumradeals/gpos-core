<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Models\CommercialDocument;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;

final readonly class ReceivePurchaseOrderResult
{
    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public PurchaseReceipt $purchaseReceipt,
        public CommercialDocument $document,
    ) {}
}
