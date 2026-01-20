<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use Illuminate\Http\Request;

class AssetTypesController extends Controller
{
    public function index()
    {
        $types = AssetType::orderBy('sort_order')->orderBy('name')->get();
        return view('settings.asset_types', compact('types'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:80','unique:asset_types,name'],
            'is_active' => ['nullable','in:0,1'],
            'sort_order' => ['nullable','integer','min:0','max:100000'],
        ]);

        AssetType::create([
            'name' => $data['name'],
            'is_active' => (int)($data['is_active'] ?? 1) === 1,
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Asset type created.');
    }

    public function update(Request $request, AssetType $assetType)
    {
        $data = $request->validate([
            'name' => ['required','string','max:80','unique:asset_types,name,'.$assetType->id],
            'is_active' => ['nullable','in:0,1'],
            'sort_order' => ['nullable','integer','min:0','max:100000'],
        ]);

        $assetType->update([
            'name' => $data['name'],
            'is_active' => (int)($data['is_active'] ?? 1) === 1,
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Asset type updated.');
    }

    public function destroy(AssetType $assetType)
    {
        $assetType->delete();
        return back()->with('success', 'Asset type deleted.');
    }
}
