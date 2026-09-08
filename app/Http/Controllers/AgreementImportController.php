<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetRental;
use App\Models\DeedImport;
use App\Models\Tenant;
use App\Support\Agreements\AgreementExtractor;
use App\Support\Agreements\AgreementMapper;
use App\Support\Audit;
use App\Support\Deeds\DeedExtractionException;
use App\Support\RentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Create an agreement from a contract PDF: upload → extract → review → save
 * (+ the contract is filed as a 'Contract' document on the property).
 */
class AgreementImportController extends Controller
{
    public function __construct(private AgreementExtractor $extractor) {}

    public function create()
    {
        return view('assets.rentals_import', [
            'configured' => $this->extractor->isConfigured(),
            'recent' => DeedImport::query()->where('kind', 'agreement')->with('asset')->latest()->limit(10)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp']]);

        if (! $this->extractor->isConfigured()) {
            return back()->with('error', 'No Anthropic API key configured. Add one under Settings → Portal.');
        }

        $file = $request->file('file');
        $path = $file->store('deed-imports', 'local');
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        $import = DeedImport::create([
            'kind' => 'agreement',
            'user_id' => auth()->id(),
            'original_name' => mb_substr(basename(str_replace('\\', '/', (string) $file->getClientOriginalName())), 0, 255) ?: 'contract',
            'disk' => 'local', 'path' => $path, 'mime_type' => $mime, 'size_bytes' => (int) $file->getSize(),
            'status' => DeedImport::STATUS_PENDING,
        ]);

        try {
            $terms = $this->extractor->extract(Storage::disk('local')->path($path), $mime);
        } catch (DeedExtractionException $e) {
            $import->update(['status' => DeedImport::STATUS_FAILED, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not read the contract: '.$e->getMessage());
        }

        $import->update(['status' => DeedImport::STATUS_EXTRACTED, 'extracted' => $terms]);
        Audit::log('agreement_import.extracted', $import, null, null);

        return redirect()->route('assets.rentals.import.review', $import);
    }

    public function review(DeedImport $import)
    {
        abort_unless($import->kind === 'agreement', 404);
        if ($import->status === DeedImport::STATUS_COMPLETED) {
            return redirect()->route('assets.rentals.index')->with('success', 'This contract was already imported.');
        }
        if ($import->status !== DeedImport::STATUS_EXTRACTED || ! $import->extracted) {
            return redirect()->route('assets.rentals.import.create')->with('error', 'This import has no extracted data. Upload the contract again.');
        }

        $prefill = AgreementMapper::toRentalAttributes($import->extracted);
        $rental = new AssetRental($prefill);

        return view('assets.rentals_import_review', [
            'import' => $import,
            'terms' => $import->extracted,
            'prefill' => $prefill,
            'rental' => $rental,
            'assets' => Asset::orderBy('name')->get(['id', 'name']),
            'tenants' => Tenant::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function confirm(Request $request, DeedImport $import, AssetRentalsController $rentals)
    {
        abort_unless($import->kind === 'agreement', 404);
        if ($import->status !== DeedImport::STATUS_EXTRACTED) {
            return redirect()->route('assets.rentals.import.create')->with('error', 'This import cannot be confirmed.');
        }

        $rental = $rentals->persistFromRequest($request, new AssetRental);
        Audit::log('asset_rental.created', $rental, null, $rental->toArray());

        $newPath = "assets/{$rental->asset_id}/".basename($import->path);
        Storage::disk($import->disk)->move($import->path, $newPath);
        $doc = AssetDocument::create([
            'asset_id' => $rental->asset_id, 'uploaded_by' => auth()->id(),
            'title' => 'Contract '.($rental->tenant_name ?: ''), 'doc_type' => 'Contract',
            'expires_at' => $rental->agreement_end_date,
            'notes' => 'Imported '.now()->toDateString(),
            'original_name' => $import->original_name, 'disk' => $import->disk, 'path' => $newPath,
            'mime_type' => $import->mime_type, 'size_bytes' => $import->size_bytes,
        ]);
        Audit::log('asset_document.uploaded', $doc, null, $doc->toArray());

        $import->update(['status' => DeedImport::STATUS_COMPLETED, 'asset_id' => $rental->asset_id, 'path' => $newPath]);
        $n = RentSchedule::backfill($rental);

        return redirect()->route('assets.show', [$rental->asset_id, 'tab' => 'payments'])
            ->with('success', 'Agreement created from the contract.'.($n ? " {$n} payment(s) already due this year were added — confirm the ones you have received." : ' Payments will be generated from its schedule.'));
    }

    public function destroy(DeedImport $import)
    {
        abort_unless($import->kind === 'agreement', 404);
        if ($import->status !== DeedImport::STATUS_COMPLETED && Storage::disk($import->disk)->exists($import->path)) {
            Storage::disk($import->disk)->delete($import->path);
        }
        $import->delete();

        return redirect()->route('assets.rentals.import.create')->with('success', 'Import discarded.');
    }
}
