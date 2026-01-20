<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetTag;
use App\Models\AssetType;
use App\Models\OwnerEntity;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

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

        $assetTypes = AssetType::orderBy('sort_order')->orderBy('name')->get(['id','name','is_active']);
        $ownerEntities = OwnerEntity::orderBy('sort_order')->orderBy('name')->get(['id','name','is_active']);

        return view('assets.create', compact('tags', 'assetTypes', 'ownerEntities'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],

            'asset_type_id' => ['required','integer','exists:asset_types,id'],

            'address' => ['nullable','string'],
            'notes' => ['nullable','string'],

            'purchase_date' => ['nullable','date'],
            'purchase_price' => ['nullable','numeric','min:0'],
            'currency' => ['required','string','max:10'],

            'owner_entity_id' => ['nullable','integer','exists:owner_entities,id'],
            'ownership_percentage' => ['nullable','numeric','min:0','max:100'],

            'title_deed' => ['nullable','in:0,1'],
            'title_deed_number' => ['nullable','string','max:255'],
            'title_deed_date' => ['nullable','date'],
            'lawyer_notary' => ['nullable','string','max:255'],

            'financed' => ['nullable','in:0,1'],
            'lender' => ['nullable','string','max:255'],
            'loan_amount' => ['nullable','numeric','min:0'],
            'interest_rate' => ['nullable','numeric','min:0'],
            'loan_start_date' => ['nullable','date'],
            'loan_end_date' => ['nullable','date'],
            'monthly_payment' => ['nullable','numeric','min:0'],

            'size_sqm' => ['nullable','numeric','min:0'],
            'land_sqm' => ['nullable','numeric','min:0'],
            'bedrooms' => ['nullable','integer','min:0','max:99'],
            'bathrooms' => ['nullable','integer','min:0','max:99'],
            'parking' => ['nullable','in:0,1'],
            'year_built' => ['nullable','integer','min:1800','max:2100'],

            'status' => ['required','string','max:50'],
            'estimated_annual_expenses' => ['nullable','numeric','min:0'],

            'tags' => ['nullable','array'],
            'tags.*' => ['integer','exists:asset_tags,id'],
        ]);

        $data['title_deed'] = (int)($data['title_deed'] ?? 0) === 1;
        $data['financed'] = (int)($data['financed'] ?? 0) === 1;
        $data['parking'] = (int)($data['parking'] ?? 0) === 1;
        $data['ownership_percentage'] = $data['ownership_percentage'] ?? 100;

        // keep legacy string fields in sync (safe if columns exist)
        $type = AssetType::find($data['asset_type_id']);
        $owner = !empty($data['owner_entity_id']) ? OwnerEntity::find($data['owner_entity_id']) : null;

        $data['type'] = $type?->name;
        $data['owner_entity'] = $owner?->name;

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
            'documents' => function ($q) { $q->orderBy('id', 'desc'); },
            'rentals' => function ($q) {
                $q->orderBy('year', 'desc')->orderBy('month', 'desc')->limit(24);
            }
        ]);

        return view('assets.show', compact('asset'));
    }

    public function edit(Asset $asset)
    {
        $tags = AssetTag::orderBy('name')->get();
        $asset->load(['tags', 'assetType', 'ownerEntity']);

        $assetTypes = AssetType::orderBy('sort_order')->orderBy('name')->get(['id','name','is_active']);
        $ownerEntities = OwnerEntity::orderBy('sort_order')->orderBy('name')->get(['id','name','is_active']);

        return view('assets.edit', compact('asset','tags','assetTypes','ownerEntities'));
    }

    public function update(Request $request, Asset $asset)
    {
        $old = $asset->toArray();

        $data = $request->validate([
            'name' => ['required','string','max:255'],

            'asset_type_id' => ['required','integer','exists:asset_types,id'],

            'address' => ['nullable','string'],
            'notes' => ['nullable','string'],

            'purchase_date' => ['nullable','date'],
            'purchase_price' => ['nullable','numeric','min:0'],
            'currency' => ['required','string','max:10'],

            'owner_entity_id' => ['nullable','integer','exists:owner_entities,id'],
            'ownership_percentage' => ['nullable','numeric','min:0','max:100'],

            'title_deed' => ['nullable','in:0,1'],
            'title_deed_number' => ['nullable','string','max:255'],
            'title_deed_date' => ['nullable','date'],
            'lawyer_notary' => ['nullable','string','max:255'],

            'financed' => ['nullable','in:0,1'],
            'lender' => ['nullable','string','max:255'],
            'loan_amount' => ['nullable','numeric','min:0'],
            'interest_rate' => ['nullable','numeric','min:0'],
            'loan_start_date' => ['nullable','date'],
            'loan_end_date' => ['nullable','date'],
            'monthly_payment' => ['nullable','numeric','min:0'],

            'size_sqm' => ['nullable','numeric','min:0'],
            'land_sqm' => ['nullable','numeric','min:0'],
            'bedrooms' => ['nullable','integer','min:0','max:99'],
            'bathrooms' => ['nullable','integer','min:0','max:99'],
            'parking' => ['nullable','in:0,1'],
            'year_built' => ['nullable','integer','min:1800','max:2100'],

            'status' => ['required','string','max:50'],
            'estimated_annual_expenses' => ['nullable','numeric','min:0'],

            'tags' => ['nullable','array'],
            'tags.*' => ['integer','exists:asset_tags,id'],
        ]);

        $data['title_deed'] = (int)($data['title_deed'] ?? 0) === 1;
        $data['financed'] = (int)($data['financed'] ?? 0) === 1;
        $data['parking'] = (int)($data['parking'] ?? 0) === 1;
        $data['ownership_percentage'] = $data['ownership_percentage'] ?? $asset->ownership_percentage ?? 100;

        // keep legacy string fields in sync
        $type = AssetType::find($data['asset_type_id']);
        $owner = !empty($data['owner_entity_id']) ? OwnerEntity::find($data['owner_entity_id']) : null;

        $data['type'] = $type?->name;
        $data['owner_entity'] = $owner?->name;

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
