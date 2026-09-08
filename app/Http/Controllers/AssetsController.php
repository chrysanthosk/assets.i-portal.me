<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetRules;
use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetTag;
use App\Models\AssetType;
use App\Models\OwnerEntity;
use App\Models\RentalPayment;
use App\Support\Audit;
use Illuminate\Http\Request;

class AssetsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $assets = Asset::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%")
                        ->orWhere('status', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%")
                        ->orWhereHas('assetType', function ($tq) use ($q) {
                            $tq->where('name', 'like', "%{$q}%");
                        })
                        ->orWhereHas('ownerEntity', function ($oq) use ($q) {
                            $oq->where('name', 'like', "%{$q}%");
                        });
                });
            })
            ->with(['tags', 'assetType', 'ownerEntity'])
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('assets.index', compact('assets', 'q'));
    }

    public function create()
    {
        $tags = AssetTag::orderBy('name')->get();

        $assetTypes = AssetType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']);
        $ownerEntities = OwnerEntity::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']);

        return view('assets.create', compact('tags', 'assetTypes', 'ownerEntities'));
    }

    public function store(Request $request)
    {
        $data = AssetRules::normalize($request->validate(AssetRules::rules()));

        $asset = Asset::create($data);
        $asset->tags()->sync($data['tags'] ?? []);

        Audit::log('asset.created', $asset, null, $asset->toArray());

        return redirect()->route('assets.index')->with('success', 'Asset created.');
    }

    public function show(Asset $asset)
    {
        $asset->load([
            'tags',
            'assetType',
            'ownerEntity',
            'documents' => function ($q) {
                $q->orderBy('id', 'desc');
            },
            'rentals' => function ($q) {
                $q->with('tenant')->orderByDesc('agreement_start_date')->orderByDesc('id')->limit(24);
            },
        ]);

        $today = now()->toDateString();
        $currentRental = $asset->rentals
            ->first(fn ($r) => $r->is_active
                && (! $r->agreement_start_date || $r->agreement_start_date->toDateString() <= $today)
                && (! $r->agreement_end_date || $r->agreement_end_date->toDateString() >= $today))
            ?? $asset->rentals->firstWhere('is_active', true);

        $payments = RentalPayment::query()->with('rental.tenant')
            ->where('asset_id', $asset->id)->orderByDesc('due_date')->limit(24)->get();
        $expenses = AssetExpense::query()
            ->where('asset_id', $asset->id)->orderByDesc('spent_on')->limit(24)->get();

        $yearStart = now()->startOfYear()->toDateString();
        $ytd = [
            'income' => (float) RentalPayment::query()->where('asset_id', $asset->id)
                ->where('status', RentalPayment::STATUS_PAID)->whereDate('paid_date', '>=', $yearStart)->sum('amount'),
            'expenses' => (float) AssetExpense::query()->where('asset_id', $asset->id)
                ->whereDate('spent_on', '>=', $yearStart)->sum('amount'),
            'outstanding' => (float) RentalPayment::query()->where('asset_id', $asset->id)
                ->where('status', '!=', RentalPayment::STATUS_PAID)->sum('amount'),
        ];

        return view('assets.show', compact('asset', 'currentRental', 'payments', 'expenses', 'ytd'));
    }

    public function edit(Asset $asset)
    {
        $tags = AssetTag::orderBy('name')->get();
        $asset->load(['tags', 'assetType', 'ownerEntity']);

        $assetTypes = AssetType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']);
        $ownerEntities = OwnerEntity::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']);

        return view('assets.edit', compact('asset', 'tags', 'assetTypes', 'ownerEntities'));
    }

    public function update(Request $request, Asset $asset)
    {
        $old = $asset->toArray();

        $data = AssetRules::normalize($request->validate(AssetRules::rules()));

        $asset->update($data);
        $asset->tags()->sync($data['tags'] ?? []);

        Audit::log('asset.updated', $asset, $old, $asset->fresh()->toArray());

        return redirect()->route('assets.index')->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset)
    {
        $old = $asset->toArray();

        $asset->tags()->detach();

        // documents deletion will cascade via FK if asset_documents.asset_id has cascadeOnDelete
        $asset->delete();

        Audit::log('asset.deleted', $asset, $old, null);

        return redirect()->route('assets.index')->with('success', 'Asset deleted.');
    }
}
