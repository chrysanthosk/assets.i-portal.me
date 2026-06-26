<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetExpense;
use App\Support\Audit;
use Illuminate\Http\Request;

class AssetExpensesController extends Controller
{
    public function index(Request $request)
    {
        $assetId = $request->integer('asset_id') ?: null;
        $category = $request->get('category') ?: null;
        $year = $request->integer('year') ?: null;

        $base = AssetExpense::query()
            ->when($assetId, fn ($q) => $q->where('asset_id', $assetId))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($year, fn ($q) => $q->whereYear('spent_on', $year));

        $expenses = (clone $base)
            ->with('asset')
            ->orderByDesc('spent_on')
            ->paginate(20)
            ->withQueryString();

        // Totals by currency for the current filter.
        $totalsByCurrency = (clone $base)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $assets = Asset::orderBy('name')->get();
        $categories = AssetExpense::CATEGORIES;

        return view('expenses.index', compact(
            'expenses', 'totalsByCurrency', 'assets', 'categories', 'assetId', 'category', 'year'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'spent_on' => ['required', 'date'],
            'category' => ['required', 'string', 'in:'.implode(',', AssetExpense::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $expense = AssetExpense::create($data);

        Audit::log('asset_expense.created', $expense, null, $expense->toArray());

        return back()->with('success', 'Expense recorded.');
    }

    public function destroy(AssetExpense $expense)
    {
        $old = $expense->toArray();
        $expense->delete();

        Audit::log('asset_expense.deleted', $expense, $old, null);

        return back()->with('success', 'Expense deleted.');
    }
}
