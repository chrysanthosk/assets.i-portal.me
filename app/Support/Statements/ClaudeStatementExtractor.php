<?php

namespace App\Support\Statements;

use App\Support\Deeds\ClaudeDocumentReader;

class ClaudeStatementExtractor implements StatementExtractor
{
    private const SYSTEM = <<<'TXT'
You extract the numbers from a monthly owner statement issued by a property-management company (short-let operators such
as Airbnb managers, or letting agents) for a property portfolio manager.

Typical layout: a period (From/To dates), the property reference, the owner, then lines such as Accommodation / Rental income,
Host contributions, Management fee (+ VAT/tax), Host income, Expenses, and "Payable to host / owner".
- gross_income: the accommodation or rental income before any fee (add host contributions if they are income).
- management_fee: the manager's fee INCLUDING its tax/VAT (use the "Total" column when there is one).
- expenses: other deductions (cleaning, maintenance, supplies) not already inside the management fee.
- net_payable: the final amount payable to the owner. If the document states it, copy it exactly; do not recompute.
- Numbers as plain digits in strings without thousands separators ("4119.65"). Dates as YYYY-MM-DD. Empty string if absent.
- List anything unclear in warnings.
TXT;

    public function isConfigured(): bool
    {
        return ClaudeDocumentReader::isConfigured();
    }

    public function extract(string $absolutePath, string $mimeType): array
    {
        $r = ClaudeDocumentReader::read($absolutePath, $mimeType, self::SYSTEM, 'Extract the statement figures.', StatementSchema::schema(), 'low');

        return StatementSchema::normalize($r['data']);
    }
}
