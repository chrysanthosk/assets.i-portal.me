<?php

namespace App\Support\Agreements;

use App\Support\Deeds\ClaudeDocumentReader;

class ClaudeAgreementExtractor implements AgreementExtractor
{
    private const SYSTEM = <<<'TXT'
You extract the commercial terms from a property rental or management agreement for a portfolio manager.

Two shapes are common:
1. A tenancy: a tenant pays a fixed rent every month → schedule_type = "monthly", monthly_amount.
2. A holiday-villa / management contract: the company guarantees an annual amount paid in dated instalments
   (e.g. "15/04/2024 15 % 2,700 + VAT", "31/05/2024 15 % 2,700 + VAT" …) → schedule_type = "installments",
   annual_amount = the guaranteed yearly total, installments = the payments of the FIRST contract year in order.
   A "commission terms" row (e.g. "31/12/2024 100 % COMMISSION TERMS") is an instalment with amount "0" and label
   "Year-end commission"; put the revenue-share clause in commission_terms.
- Documents may be in English or Greek (ΙΔΙΩΤΙΚΟ ΣΥΜΦΩΝΗΤΙΚΟ ΜΙΣΘΩΣΗΣ: εκμισθωτής = landlord, μισθωτής = tenant,
  ΜΙΣΘΩΜΑ = rent, ΔΙΑΡΚΕΙΑ = duration, ΕΓΓΥΗΣΗ = deposit, "έως 19 κάθε μήνα" → payment_due_day "19", ΚΑΕΚ = cadastral
  number, Α.Φ.Μ. = tax number). Write names, streets and places in Latin transliteration followed by the original in
  parentheses, e.g. "Andreas Giakoumakis (Ανδρέας Γιακουμάκης)", "Drakontos 18, Kaisariani (Δράκοντος 18)".
- Dates: DD/MM/YYYY → YYYY-MM-DD. Amounts as digits ("2700"). If amounts are stated "+ VAT", amounts_exclude_vat = "yes".
- The counterparty is the OTHER party, not the owner/supplier. If two tenants sign jointly, put both names in counterparty
  ("A & B") and the first person's email/phone/ID in the contact fields.
- auto_renews = "yes" if the period spans several years or renewal is stated; "unknown" otherwise.
- Empty string for anything not present. List anything unclear in warnings.
TXT;

    public function isConfigured(): bool
    {
        return ClaudeDocumentReader::isConfigured();
    }

    public function extract(string $absolutePath, string $mimeType): array
    {
        $r = ClaudeDocumentReader::read($absolutePath, $mimeType, self::SYSTEM, 'Extract the agreement terms.', AgreementSchema::schema());

        return AgreementSchema::normalize($r['data']);
    }
}
