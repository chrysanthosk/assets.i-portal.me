<?php

namespace App\Http\Requests;

/**
 * Validation + normalisation for the property form, shared by create, update
 * and the title-deed import confirmation so all three accept the same input.
 */
class AssetRules
{
    public const STATUSES = ['Vacant', 'Owner-occupied', 'Rented (long-term)', 'Airbnb/Short-term'];

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'asset_type_id' => ['required', 'integer', 'exists:asset_types,id'],
            'status' => ['required', 'string', 'max:50'],
            'currency' => ['required', 'string', 'max:10'],

            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],

            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'owner_entity_id' => ['nullable', 'integer', 'exists:owner_entities,id'],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'title_deed' => ['nullable', 'in:0,1'],
            'title_deed_number' => ['nullable', 'string', 'max:255'],
            'title_deed_date' => ['nullable', 'date'],
            'lawyer_notary' => ['nullable', 'string', 'max:255'],

            'financed' => ['nullable', 'in:0,1'],
            'lender' => ['nullable', 'string', 'max:255'],
            'loan_amount' => ['nullable', 'numeric', 'min:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'loan_start_date' => ['nullable', 'date'],
            'loan_end_date' => ['nullable', 'date'],
            'monthly_payment' => ['nullable', 'numeric', 'min:0'],

            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'land_sqm' => ['nullable', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:99'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:99'],
            'parking' => ['nullable', 'in:0,1'],
            'year_built' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'estimated_annual_expenses' => ['nullable', 'numeric', 'min:0'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:asset_tags,id'],
        ];
    }

    /**
     * Checkbox strings → booleans, default share 100 %.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        foreach (['title_deed', 'financed', 'parking'] as $flag) {
            $data[$flag] = (int) ($data[$flag] ?? 0) === 1;
        }
        $data['ownership_percentage'] = $data['ownership_percentage'] ?? 100;

        return $data;
    }
}
