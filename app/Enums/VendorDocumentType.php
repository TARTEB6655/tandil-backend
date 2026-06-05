<?php

namespace App\Enums;

enum VendorDocumentType: string
{
    case TradeLicense = 'trade_license';
    case EmiratesId = 'emirates_id';
    case BusinessLicense = 'business_license';
    case RegistrationCertificate = 'registration_certificate';
    case GovernmentId = 'government_id';
    case VendorAgreement = 'vendor_agreement';
    case TaxCertificate = 'tax_certificate';
    case BusinessProof = 'business_proof';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TradeLicense => 'Trade License',
            self::EmiratesId => 'Emirates ID (Authorized Person)',
            self::BusinessLicense => 'Business License',
            self::RegistrationCertificate => 'Registration Certificate',
            self::GovernmentId => 'Government ID',
            self::VendorAgreement => 'Vendor Agreement',
            self::TaxCertificate => 'Tax Certificate',
            self::BusinessProof => 'Business Proof',
            self::Other => 'Other Document',
        };
    }

    public function isRequiredForOnboarding(): bool
    {
        return in_array($this, self::requiredForOnboarding(), true);
    }

    /** @return list<self> */
    public static function requiredForOnboarding(): array
    {
        return [
            self::TradeLicense,
            self::EmiratesId,
        ];
    }

    /** @return list<string> */
    public static function requiredValues(): array
    {
        return array_map(fn (self $t) => $t->value, self::requiredForOnboarding());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
