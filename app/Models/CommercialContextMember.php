<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommercialContextMember extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_SUSPENDED = 'SUSPENDED';

    protected $table = 'commercial_context_members';

    protected $fillable = ['context_id', 'core_identity_reference', 'permissions', 'status'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }
}
