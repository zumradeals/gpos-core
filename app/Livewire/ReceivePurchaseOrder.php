<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Application\Commerce\ReceivePurchaseOrder as ReceivePurchaseOrderService;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\OverReceiptException;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotReceivableException;
use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CurrentActor;
use App\Models\PurchaseOrder;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * « Que venez-vous de recevoir ? » (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §23).
 * purchaseOrderId/idempotencyKey sont #[Locked] ; purchaseOrder() revalide en plus le contexte
 * actif de l'acteur à chaque rendu/soumission (symétrique à App\Livewire\Sell et
 * App\Livewire\PurchaseOrderBuilder).
 */
final class ReceivePurchaseOrder extends Component
{
    #[Locked]
    public string $purchaseOrderId;

    #[Locked]
    public string $idempotencyKey;

    /** @var array<string, string> */
    public array $receiveNow = [];

    public ?string $errorMessage = null;

    public function mount(string $purchaseOrderId): void
    {
        $this->purchaseOrderId = $purchaseOrderId;
        $this->idempotencyKey = (string) Str::uuid();

        $order = $this->purchaseOrder();

        foreach ($order->lines as $line) {
            $remaining = Quantity::subtract((string) $line->ordered_quantity, (string) $line->received_quantity);
            $this->receiveNow[$line->id] = Quantity::isPositive($remaining) ? $remaining : '0';
        }
    }

    public function confirmReceipt()
    {
        $this->errorMessage = null;
        $actor = $this->actor();
        $order = $this->purchaseOrder();

        $requested = collect($this->receiveNow)
            ->filter(fn ($quantity) => is_string($quantity) && $quantity !== '' && Quantity::isPositive($quantity))
            ->all();

        try {
            $result = app(ReceivePurchaseOrderService::class)->handle($order, $actor, $requested, $this->idempotencyKey);
        } catch (OverReceiptException|PurchaseOrderNotReceivableException|CommercialContextSuspendedException $e) {
            $this->errorMessage = $e->getMessage();

            return null;
        }

        return $this->redirect(route('purchases.show', $result->purchaseOrder), navigate: false);
    }

    public function render()
    {
        $actor = $this->actor();
        $order = $this->purchaseOrder();
        $lines = $order->lines()->orderBy('created_at')->get();

        return view('livewire.receive-purchase-order', [
            'order' => $order,
            'lines' => $lines,
            'canReceive' => $actor->can(CommercialPermission::RECEIVE_PURCHASES),
        ]);
    }

    private function actor(): CurrentActor
    {
        return app(CurrentActor::class);
    }

    private function purchaseOrder(): PurchaseOrder
    {
        $actor = $this->actor();
        $order = PurchaseOrder::query()->findOrFail($this->purchaseOrderId);

        abort_unless($actor->hasActiveContext() && $order->context_id === $actor->activeContext()->id, 404);
        abort_unless(in_array($order->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true), 404);

        return $order;
    }
}
