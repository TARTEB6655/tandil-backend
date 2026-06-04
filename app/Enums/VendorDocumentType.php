<?php

namespace App\Enums;

enum VendorDocumentType: string
{
    case TradeLicense = 'trade_license';
    case BusinessProof = 'business_proof';
    case TaxCertificate = 'tax_certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TradeLicense => 'Trade License',
            self::BusinessProof => 'Business Proof',
            self::TaxCertificate => 'Tax Certificate',
            self::Other => 'Other Document',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
