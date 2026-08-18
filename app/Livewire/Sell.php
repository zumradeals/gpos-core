<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Application\Commerce\ConfirmCashSale;
use App\Application\Commerce\SaleDraftService;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\InsufficientStockException;
use App\Domain\Commerce\Exceptions\SaleNotConfirmableException;
use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CurrentActor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Le parcours vente — benchmark UX du produit (docs/implementation/LOT-001-APP-SHELL-COMMERCE-
 * SLICE.md §18) : rechercher, ajouter, ajuster, encaisser, reçu, en quelques gestes.
 */
final class Sell extends Component
{
    public string $search = '';

    public string $saleId;

    public string $idempotencyKey;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $actor = $this->actor();
        $sale = app(SaleDraftService::class)->findOrCreateDraft($actor->activeContext(), $actor->identity);
        $this->saleId = (string) $sale->id;
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function addProduct(string $productId): void
    {
        $actor = $this->actor();
        $product = Product::query()->where('context_id', $actor->activeContext()->id)->where('active', true)->findOrFail($productId);

        app(SaleDraftService::class)->addOrIncrementLine($this->sale(), $product);
        $this->errorMessage = null;
    }

    public function incrementLine(string $lineId): void
    {
        $line = $this->line($lineId);
        app(SaleDraftService::class)->setQuantity($this->sale(), $line, Quantity::add((string) $line->quantity, '1'));
    }

    public function decrementLine(string $lineId): void
    {
        $line = $this->line($lineId);
        app(SaleDraftService::class)->setQuantity($this->sale(), $line, Quantity::subtract((string) $line->quantity, '1'));
    }

    public function removeLine(string $lineId): void
    {
        app(SaleDraftService::class)->removeLine($this->sale(), $this->line($lineId));
    }

    public function confirmCash()
    {
        $this->errorMessage = null;
        $actor = $this->actor();

        try {
            $result = app(ConfirmCashSale::class)->handle($this->sale(), $actor, $this->idempotencyKey);
        } catch (InsufficientStockException|SaleNotConfirmableException|CommercialContextSuspendedException $e) {
            $this->errorMessage = $e->getMessage();

            return null;
        }

        return $this->redirect(route('documents.show', $result->document), navigate: false);
    }

    public function render()
    {
        $actor = $this->actor();
        $context = $actor->activeContext();

        $products = Product::query()
            ->where('context_id', $context->id)
            ->where('active', true)
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('name', 'ilike', '%'.$this->search.'%')
                    ->orWhere('sku', 'ilike', '%'.$this->search.'%')
                    ->orWhere('barcode', $this->search);
            }))
            ->orderBy('name')
            ->limit(24)
            ->get();

        $sale = $this->sale();
        $lines = $sale->lines()->orderBy('created_at')->get();

        return view('livewire.sell', [
            'products' => $products,
            'lines' => $lines,
            'sale' => $sale,
            'canSell' => $actor->can(CommercialPermission::SELL),
        ]);
    }

    private function actor(): CurrentActor
    {
        return app(CurrentActor::class);
    }

    private function sale(): Sale
    {
        return Sale::query()->findOrFail($this->saleId);
    }

    private function line(string $lineId): SaleLine
    {
        return SaleLine::query()->where('sale_id', $this->saleId)->findOrFail($lineId);
    }
}
