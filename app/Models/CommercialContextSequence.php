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

    protected $table = 'commercial_context_sequences';

    protected $fillable = ['context_id', 'sequence_type', 'last_value'];
}
