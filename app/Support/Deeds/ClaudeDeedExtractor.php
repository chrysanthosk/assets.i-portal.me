<?php

namespace App\Support\Deeds;

/**
 * Reads a scanned title deed with Claude (vision + structured outputs).
 */
class ClaudeDeedExtractor implements DeedExtractor
{
    public const SETTING_API_KEY = ClaudeDocumentReader::SETTING_API_KEY;

    private const SYSTEM = <<<'TXT'
You extract structured data from land-registry title deeds for a property portfolio manager. Two registries are common:

1. Cyprus Department of Lands and Surveys (Τμήμα Κτηματολογίου και Χωρομετρίας): "Κτηματική Σελίδα Μονάδας" (unit sheet)
   or "Κτηματική Σελίδα" (plot sheet), usually Greek scans. Fields: Αριθμός Εγγραφής → registration_number, Επαρχία → district,
   Δήμος/Κοινότητα → municipality_community, Ενορία → parish, Τοποθεσία → locality, Διεύθυνση → street_address,
   Όνομα Οικοδομής → building_name, Αρ. Θύρας → unit_number, Φύλλο/Σχέδιο/Τμήμα/Τεμάχιο → sheet/plan/section/plot,
   Αριθμός Φακέλου → file_number, Μερίδιο → share (ΟΛΟ = whole = 100 %), Κλειστός χώρος → enclosed_area_sqm,
   Καλυμμένες/Ακάλυπτες βεράντες → covered/uncovered_veranda_sqm, Μερίδιο στην κοινόκτητη ιδιοκτησία → common_property_share_pct,
   Αξία Γεν. Εκτίμησης → valuations. country = "Cyprus", registry = "Cyprus Department of Lands and Surveys".
2. Dubai: DIFC Registrar of Real Property or Dubai Land Department (DLD) title deeds, in English. Map: "Ref" → registration_number,
   "Folio No" → file_number, "Zone" or "Community" → district (e.g. "Dubai International Financial Centre"),
   municipality_community = "Dubai", "Building Name" → building_name, "Lot (Unit) No" / "Unit No" → unit_number,
   "Area of Principal Lot" / "Area" → enclosed_area_sqm (convert sq ft to m² if needed: ÷ 10.764), "Accessory Lot" car parking
   bays → parking_spaces (count them) and mention the bay number in property_description, "Type: Residential" → apartment,
   the document date → registration_date, "Notifications" → rights_and_encumbrances. Owner share is whole (100) unless stated.
   country = "United Arab Emirates", registry = "DIFC Real Property Register" or "Dubai Land Department".

Rules:
- Fill every field you can read; use an empty string for anything not present. Never invent values.
- Dates: convert to YYYY-MM-DD (02.May.2024 → 2024-05-02; 03/06/2026 in Cyprus documents is DD/MM/YYYY).
- Numbers as plain digits in strings ("80.60", "99800"). Areas in square metres.
- Owner names and building names: keep as written (original script). Place and street names: standard English/transliterated form.
- property_type: apartment | house | maisonette | land | commercial | office | other | unknown.
- List anything unclear or partially legible in warnings.
TXT;

    public static function apiKey(): ?string
    {
        return ClaudeDocumentReader::apiKey();
    }

    public static function model(): string
    {
        return ClaudeDocumentReader::model();
    }

    public function isConfigured(): bool
    {
        return ClaudeDocumentReader::isConfigured();
    }

    public function extract(string $absolutePath, string $mimeType): DeedExtraction
    {
        $r = ClaudeDocumentReader::read($absolutePath, $mimeType, self::SYSTEM, 'Extract the title deed data from this document.', DeedSchema::schema());

        return new DeedExtraction(DeedSchema::normalize($r['data']), $r['model'], $r['input_tokens'], $r['output_tokens']);
    }
}
