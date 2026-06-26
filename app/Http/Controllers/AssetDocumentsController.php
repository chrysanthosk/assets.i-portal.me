<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetDocumentsController extends Controller
{
    public function store(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'notes' => ['nullable','string','max:500'],
            // Whitelist by real content type (Laravel inspects the file itself,
            // not the client-supplied extension/MIME) and cap at 20MB.
            'file'  => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,webp,gif,doc,docx,xls,xlsx,csv,txt',
            ],
        ]);

        $file = $request->file('file');

        $disk = 'local'; // private disk (storage/app/private); never web-served
        $dir = "assets/{$asset->id}";
        $storedPath = $file->store($dir, $disk);

        // Server-detected MIME (cannot be spoofed by the client) and a sanitised
        // display name (strip any path components / null bytes).
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $originalName = mb_substr(basename(str_replace('\\', '/', (string) $file->getClientOriginalName())), 0, 255);
        if ($originalName === '') {
            $originalName = 'document';
        }

        $doc = AssetDocument::create([
            'asset_id' => $asset->id,
            'uploaded_by' => auth()->id(),

            'title' => null,
            'notes' => $data['notes'] ?? null,

            'original_name' => $originalName,

            'disk' => $disk,

            // new schema
            'path' => $storedPath,
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),

            // legacy schema compatibility
            'file_path' => $storedPath,
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ]);

        Audit::log('asset_document.uploaded', $doc, null, $doc->toArray());

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Asset $asset, AssetDocument $document)
    {
        if ((int)$document->asset_id !== (int)$asset->id) {
            abort(404);
        }

        $disk = $document->disk ?: 'local';

        $path = $document->path ?: $document->file_path;

        if (!$path) {
            return back()->with('error', 'Document path is missing.');
        }

        if (!Storage::disk($disk)->exists($path)) {
            return back()->with('error', 'File not found on storage.');
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $downloadName = $document->original_name ?: 'document';

        return response()->download($absolutePath, $downloadName);
    }

    public function destroy(Asset $asset, AssetDocument $document)
    {
        if ((int)$document->asset_id !== (int)$asset->id) {
            abort(404);
        }

        $old = $document->toArray();

        $disk = $document->disk ?: 'local';
        $path = $document->path ?: $document->file_path;

        if ($path) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $document->delete();

        Audit::log('asset_document.deleted', $document, $old, null);

        return back()->with('success', 'Document deleted.');
    }
}
