<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CommercialContextSequence extends Model
{
    use HasUuids;

    public const SALE = 'SALE';

    public const RECEIPT = 'RECEIPT';

    public const PURCHASE_ORDER = 'PURCHASE_ORDER';

    public const GOODS_RECEIPT = 'GOODS_RECEIPT';

    public const CASH_SESSION = 'CASH_SESSION';

    public const CASH_CLOSURE = 'CASH_CLOSURE';

    protected $table = 'commercial_context_sequences';

    protected $fillable = ['context_id', 'sequence_type', 'last_value'];
}
