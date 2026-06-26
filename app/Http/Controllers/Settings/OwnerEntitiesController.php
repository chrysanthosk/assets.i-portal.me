<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\OwnerEntity;
use App\Support\Audit;
use Illuminate\Http\Request;

class OwnerEntitiesController extends Controller
{
    public function index()
    {
        $entities = OwnerEntity::orderBy('sort_order')->orderBy('name')->get();

        return view('settings.owner_entities', compact('entities'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:owner_entities,name'],
            'is_active' => ['nullable', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $entity = OwnerEntity::create([
            'name' => $data['name'],
            'is_active' => (int) ($data['is_active'] ?? 1) === 1,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        Audit::log('owner_entity.created', $entity, null, $entity->toArray());

        return back()->with('success', 'Owner entity created.');
    }

    public function update(Request $request, OwnerEntity $ownerEntity)
    {
        $old = $ownerEntity->toArray();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:owner_entities,name,'.$ownerEntity->id],
            'is_active' => ['nullable', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $ownerEntity->update([
            'name' => $data['name'],
            'is_active' => (int) ($data['is_active'] ?? 1) === 1,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        Audit::log('owner_entity.updated', $ownerEntity, $old, $ownerEntity->fresh()->toArray());

        return back()->with('success', 'Owner entity updated.');
    }

    public function destroy(OwnerEntity $ownerEntity)
    {
        // Option 1: block if referenced
        $isUsed = Asset::where('owner_entity_id', $ownerEntity->id)->exists()
            || Asset::where('owner_entity', $ownerEntity->name)->exists(); // legacy fallback

        if ($isUsed) {
            return back()->with('error', 'Cannot delete this Owner Entity because it is used by one or more assets.');
        }

        $old = $ownerEntity->toArray();
        $ownerEntity->delete();

        Audit::log('owner_entity.deleted', $ownerEntity, $old, null);

        return back()->with('success', 'Owner entity deleted.');
    }
}
