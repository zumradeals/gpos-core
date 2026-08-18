<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\CurrentActor;
use App\Models\CommercialDocument;
use Illuminate\View\View;

/**
 * Reçus commerciaux (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §13) — le rendu lit
 * uniquement le snapshot figé, jamais l'état courant du produit.
 */
final class DocumentController extends Controller
{
    public function index(): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        $documents = CommercialDocument::query()
            ->where('context_id', $actor->activeContext()->id)
            ->latest('issued_at')
            ->limit(100)
            ->get();

        return view('documents.index', ['documents' => $documents]);
    }

    public function show(CommercialDocument $document): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($document->context_id === $actor->activeContext()->id, 404);

        return view('documents.show', ['document' => $document]);
    }
}
