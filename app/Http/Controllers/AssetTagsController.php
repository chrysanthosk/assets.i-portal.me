<?php

namespace App\Http\Controllers;

use App\Models\AssetTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetTagsController extends Controller
{
    public function index()
    {
        $tags = AssetTag::orderBy('name')->paginate(20);
        return view('assets.tags', compact('tags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:50', Rule::unique('asset_tags','name')],
        ]);

        AssetTag::create([
            'name' => trim($data['name']),
        ]);

        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, AssetTag $tag)
    {
        $data = $request->validate([
            'name' => ['required','string','max:50', Rule::unique('asset_tags','name')->ignore($tag->id)],
        ]);

        $tag->update([
            'name' => trim($data['name']),
        ]);

        return back()->with('success', 'Tag updated.');
    }

    public function destroy(AssetTag $tag)
    {
        // detach from assets first (safety)
        $tag->assets()->detach();
        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }
}
