<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projection courante — jamais mutée directement hors de App\Domain\Commerce\Stock, toujours
 * depuis un StockMovement source.
 */
final class StockBalance extends Model
{
    use HasUuids;

    protected $table = 'stock_balances';

    protected $fillable = ['context_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
