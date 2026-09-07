<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetType;
use App\Models\DeedImport;
use App\Models\OwnerEntity;
use App\Support\Audit;
use App\Support\Deeds\DeedExtractionException;
use App\Support\Deeds\DeedExtractor;
use App\Support\Deeds\DeedMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Create a property from a scanned title deed:
 *   upload → extract with Claude → review the prefilled form → save asset + attach the PDF.
 */
class AssetImportController extends Controller
{
    public function __construct(private DeedExtractor $extractor) {}

    public function create()
    {
        $recent = DeedImport::query()->with('asset')->latest()->limit(10)->get();

        return view('assets.import', [
            'configured' => $this->extractor->isConfigured(),
            'recent' => $recent,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        if (! $this->extractor->isConfigured()) {
            return back()->with('error', 'No Anthropic API key configured. Add one under Settings → Portal.');
        }

        $file = $request->file('file');
        $disk = 'local';
        $path = $file->store('deed-imports', $disk);
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        $import = DeedImport::create([
            'user_id' => auth()->id(),
            'original_name' => mb_substr(basename(str_replace('\\', '/', (string) $file->getClientOriginalName())), 0, 255) ?: 'deed',
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'status' => DeedImport::STATUS_PENDING,
        ]);

        try {
            $result = $this->extractor->extract(Storage::disk($disk)->path($path), $mime);
        } catch (DeedExtractionException $e) {
            $import->update(['status' => DeedImport::STATUS_FAILED, 'error' => $e->getMessage()]);
            Audit::log('deed_import.failed', $import, null, ['error' => $e->getMessage()]);

            return back()->with('error', 'Could not read the deed: '.$e->getMessage());
        }

        $import->update([
            'status' => DeedImport::STATUS_EXTRACTED,
            'extracted' => $result->data,
            'model' => $result->model,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
        ]);
        Audit::log('deed_import.extracted', $import, null, ['model' => $result->model]);

        return redirect()->route('assets.import.review', $import);
    }

    public function review(DeedImport $import)
    {
        if ($import->status === DeedImport::STATUS_COMPLETED && $import->asset) {
            return redirect()->route('assets.show', $import->asset)->with('success', 'This deed was already imported.');
        }
        if ($import->status !== DeedImport::STATUS_EXTRACTED || ! $import->extracted) {
            return redirect()->route('assets.import.create')->with('error', 'This import has no extracted data. Upload the deed again.');
        }

        return view('assets.import_review', [
            'import' => $import,
            'deed' => $import->extracted,
            'prefill' => DeedMapper::toAssetAttributes($import->extracted),
            'assetTypes' => AssetType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']),
            'ownerEntities' => OwnerEntity::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']),
            'statuses' => ['Vacant', 'Owner-occupied', 'Rented (long-term)', 'Airbnb/Short-term'],
        ]);
    }

    public function confirm(Request $request, DeedImport $import)
    {
        if ($import->status !== DeedImport::STATUS_EXTRACTED) {
            return redirect()->route('assets.import.create')->with('error', 'This import cannot be confirmed.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'asset_type_id' => ['required', 'integer', 'exists:asset_types,id'],
            'owner_entity_id' => ['nullable', 'integer', 'exists:owner_entities,id'],
            'status' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'title_deed_number' => ['nullable', 'string', 'max:255'],
            'title_deed_date' => ['nullable', 'date'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'land_sqm' => ['nullable', 'numeric', 'min:0'],
            'parking' => ['nullable', 'in:0,1'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['title_deed'] = true;
        $data['parking'] = (int) ($data['parking'] ?? 0) === 1;
        $data['ownership_percentage'] = $data['ownership_percentage'] ?? 100;
        $data['title_deed_data'] = $import->extracted;

        $asset = Asset::create($data);
        Audit::log('asset.created', $asset, null, $asset->toArray());

        // Move the scan into the asset's document folder and register it as the title deed
        $newPath = "assets/{$asset->id}/".basename($import->path);
        Storage::disk($import->disk)->move($import->path, $newPath);

        $doc = AssetDocument::create([
            'asset_id' => $asset->id,
            'uploaded_by' => auth()->id(),
            'title' => 'Title deed',
            'doc_type' => 'Title Deed',
            'notes' => 'Imported '.now()->toDateString().($import->extracted['registration_number'] ?? null ? ' — reg. no. '.$import->extracted['registration_number'] : ''),
            'original_name' => $import->original_name,
            'disk' => $import->disk,
            'path' => $newPath,
            'mime_type' => $import->mime_type,
            'size_bytes' => $import->size_bytes,
        ]);
        Audit::log('asset_document.uploaded', $doc, null, $doc->toArray());

        $import->update(['status' => DeedImport::STATUS_COMPLETED, 'asset_id' => $asset->id, 'path' => $newPath]);
        Audit::log('deed_import.completed', $import, null, ['asset_id' => $asset->id]);

        return redirect()->route('assets.show', $asset)->with('success', 'Property created from the title deed. Check the details and add purchase info when ready.');
    }

    public function destroy(DeedImport $import)
    {
        if ($import->status !== DeedImport::STATUS_COMPLETED && $import->path && Storage::disk($import->disk)->exists($import->path)) {
            Storage::disk($import->disk)->delete($import->path);
        }
        $import->delete();

        return redirect()->route('assets.import.create')->with('success', 'Import discarded.');
    }
}
