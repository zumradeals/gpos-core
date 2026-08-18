<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Application\Commerce\ConfirmPurchaseOrder;
use App\Application\Commerce\PurchaseOrderDraftService;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotConfirmableException;
use App\Domain\Identity\CurrentActor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Construction d'une commande d'achat DRAFT (docs/implementation/LOT-002-PURCHASING-SUPPLY.md
 * §22). purchaseOrderId/idempotencyKey sont #[Locked] (aucune mise à jour cliente possible), mais
 * ce n'est pas la seule frontière : purchaseOrder() revalide systématiquement que la commande
 * appartient au contexte commercial ACTUELLEMENT actif de l'acteur — échec fermé sinon
 * (symétrique à App\Livewire\Sell — docs/implementation/LOT-001 revue G-POS).
 */
final class PurchaseOrderBuilder extends Component
{
    public string $search = '';

    #[Locked]
    public string $purchaseOrderId;

    #[Locked]
    public string $idempotencyKey;

    public string $selectedProductId = '';

    public string $quantity = '1';

    public string $unitCostXof = '';

    public ?string $errorMessage = null;

    public function mount(string $purchaseOrderId, ?string $initialProductId = null): void
    {
        $this->purchaseOrderId = $purchaseOrderId;
        $this->idempotencyKey = (string) Str::uuid();

        // Revalide immédiatement l'appartenance au contexte actif — échoue fermé (404) si l'ID
        // fourni par la route ne correspond pas à une commande de ce contexte.
        $order = $this->purchaseOrder();

        // « Préparer un achat » depuis le stock faible peut préremplir le produit (docs/
        // implementation/LOT-002-PURCHASING-SUPPLY.md §8, §22) — jamais le fournisseur, jamais
        // une confirmation. Un ID hors contexte est simplement ignoré : addLine() revalidera de
        // toute façon l'appartenance au contexte avant toute mutation réelle.
        if ($initialProductId !== null && Product::query()->where('context_id', $order->context_id)->whereKey($initialProductId)->exists()) {
            $this->selectedProductId = $initialProductId;
        }
    }

    public function selectProduct(string $productId): void
    {
        $this->selectedProductId = $productId;
        $this->unitCostXof = '';
        $this->quantity = '1';
    }

    public function addLine(): void
    {
        $order = $this->purchaseOrder();
        $this->errorMessage = null;

        if ($this->selectedProductId === '') {
            $this->errorMessage = 'Choisissez un produit.';

            return;
        }

        $product = Product::query()->where('context_id', $order->context_id)->findOrFail($this->selectedProductId);

        // Coût unitaire = entrée entière brute, jamais une conversion flottante (docs/G-POS-
        // DOCTRINE.md — « Montants monétaires : entiers, jamais float »).
        $unitCostXof = (int) preg_replace('/\D/', '', $this->unitCostXof);

        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $product, $this->quantity, $unitCostXof);

        $this->selectedProductId = '';
        $this->quantity = '1';
        $this->unitCostXof = '';
    }

    public function removeLine(string $lineId): void
    {
        $order = $this->purchaseOrder();
        app(PurchaseOrderDraftService::class)->removeLine($order, $this->line($order, $lineId));
    }

    public function confirmOrder()
    {
        $this->errorMessage = null;
        $actor = $this->actor();

        try {
            $result = app(ConfirmPurchaseOrder::class)->handle($this->purchaseOrder(), $actor, $this->idempotencyKey);
        } catch (PurchaseOrderNotConfirmableException|CommercialContextSuspendedException $e) {
            $this->errorMessage = $e->getMessage();

            return null;
        }

        return $this->redirect(route('purchases.show', $result->purchaseOrder), navigate: false);
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

        $order = $this->purchaseOrder();
        $lines = $order->lines()->orderBy('created_at')->get();

        return view('livewire.purchase-order-builder', [
            'products' => $products,
            'lines' => $lines,
            'order' => $order,
            'supplier' => $order->supplier,
            'canManagePurchases' => $actor->can(CommercialPermission::MANAGE_PURCHASES),
        ]);
    }

    private function actor(): CurrentActor
    {
        return app(CurrentActor::class);
    }

    /**
     * Frontière de sécurité réelle : #[Locked] empêche une mise à jour cliente de purchaseOrderId,
     * mais ne garantit pas à elle seule que la commande reste dans le contexte actif de l'acteur.
     * Échec fermé (404) en cas de désaccord.
     */
    private function purchaseOrder(): PurchaseOrder
    {
        $actor = $this->actor();
        $order = PurchaseOrder::query()->findOrFail($this->purchaseOrderId);

        abort_unless($actor->hasActiveContext() && $order->context_id === $actor->activeContext()->id, 404);

        return $order;
    }

    private function line(PurchaseOrder $order, string $lineId): PurchaseOrderLine
    {
        return PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->findOrFail($lineId);
    }
}
