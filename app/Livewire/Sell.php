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
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Le parcours vente — benchmark UX du produit (docs/implementation/LOT-001-APP-SHELL-COMMERCE-
 * SLICE.md §18) : rechercher, ajouter, ajuster, encaisser, reçu, en quelques gestes.
 *
 * saleId/idempotencyKey sont #[Locked] (aucune mise à jour cliente possible), mais ce n'est pas
 * la seule frontière : sale() revalide systématiquement que la vente appartient au contexte
 * commercial ACTUELLEMENT actif de l'acteur (qui peut avoir changé depuis mount(), p. ex. bascule
 * de contexte dans un autre onglet) — échec fermé sinon. SaleDraftService/ConfirmCashSale
 * protègent en plus leurs propres invariants indépendamment de ce composant.
 */
final class Sell extends Component
{
    public string $search = '';

    #[Locked]
    public string $saleId;

    #[Locked]
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
        $sale = $this->sale();
        $line = $this->line($sale, $lineId);
        app(SaleDraftService::class)->setQuantity($sale, $line, Quantity::add((string) $line->quantity, '1'));
    }

    public function decrementLine(string $lineId): void
    {
        $sale = $this->sale();
        $line = $this->line($sale, $lineId);
        app(SaleDraftService::class)->setQuantity($sale, $line, Quantity::subtract((string) $line->quantity, '1'));
    }

    public function removeLine(string $lineId): void
    {
        $sale = $this->sale();
        app(SaleDraftService::class)->removeLine($sale, $this->line($sale, $lineId));
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

    /**
     * Frontière de sécurité réelle : #[Locked] empêche une mise à jour cliente de saleId, mais ne
     * garantit pas à elle seule que la vente reste dans le contexte actif de l'acteur (qui peut
     * changer entre deux requêtes). Échec fermé (404) en cas de désaccord.
     */
    private function sale(): Sale
    {
        $actor = $this->actor();
        $sale = Sale::query()->findOrFail($this->saleId);

        abort_unless($actor->hasActiveContext() && $sale->context_id === $actor->activeContext()->id, 404);

        return $sale;
    }

    private function line(Sale $sale, string $lineId): SaleLine
    {
        return SaleLine::query()->where('sale_id', $sale->id)->findOrFail($lineId);
    }
}
