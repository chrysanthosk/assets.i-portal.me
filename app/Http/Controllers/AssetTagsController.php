<?php

namespace App\Http\Controllers;

use App\Models\AssetTag;
use App\Support\Audit;
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

        $tag = AssetTag::create([
            'name' => trim($data['name']),
        ]);

        Audit::log('asset_tag.created', $tag, null, $tag->toArray());

        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, AssetTag $tag)
    {
        $data = $request->validate([
            'name' => ['required','string','max:50', Rule::unique('asset_tags','name')->ignore($tag->id)],
        ]);

        $old = $tag->toArray();

        $tag->update([
            'name' => trim($data['name']),
        ]);

        Audit::log('asset_tag.updated', $tag, $old, $tag->fresh()->toArray());

        return back()->with('success', 'Tag updated.');
    }

    public function destroy(AssetTag $tag)
    {
        $old = $tag->toArray();

        // detach from assets first (safety)
        $tag->assets()->detach();
        $tag->delete();

        Audit::log('asset_tag.deleted', $tag, $old, null);

        return back()->with('success', 'Tag deleted.');
    }
}
