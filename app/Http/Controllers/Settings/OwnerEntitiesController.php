<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OwnerEntity;
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
            'name' => ['required','string','max:120','unique:owner_entities,name'],
            'is_active' => ['nullable','in:0,1'],
            'sort_order' => ['nullable','integer','min:0','max:100000'],
        ]);

        OwnerEntity::create([
            'name' => $data['name'],
            'is_active' => (int)($data['is_active'] ?? 1) === 1,
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Owner entity created.');
    }

    public function update(Request $request, OwnerEntity $ownerEntity)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120','unique:owner_entities,name,'.$ownerEntity->id],
            'is_active' => ['nullable','in:0,1'],
            'sort_order' => ['nullable','integer','min:0','max:100000'],
        ]);

        $ownerEntity->update([
            'name' => $data['name'],
            'is_active' => (int)($data['is_active'] ?? 1) === 1,
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Owner entity updated.');
    }

    public function destroy(OwnerEntity $ownerEntity)
    {
        $ownerEntity->delete();
        return back()->with('success', 'Owner entity deleted.');
    }
}
