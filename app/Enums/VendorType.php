<?php

namespace App\Enums;

enum VendorType: string
{
    case Fruits = 'fruits';
    case Vegetables = 'vegetables';
    case Poultry = 'poultry';
    case Seafood = 'seafood';
    case Meat = 'meat';
    case Honey = 'honey';
    case Nuts = 'nuts';
    case Restaurant = 'restaurant';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fruits => 'Fruits',
            self::Vegetables => 'Vegetables',
            self::Poultry => 'Poultry',
            self::Seafood => 'Seafood',
            self::Meat => 'Meat',
            self::Honey => 'Honey',
            self::Nuts => 'Nuts',
            self::Restaurant => 'Restaurant',
            self::Other => 'Other',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Resolve mobile/UI picker values (labels, plurals, loose aliases) to a stored enum value.
     */
    public static function resolve(mixed $raw): ?string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        $compact = preg_replace('/[\s\-_]+/', '', $value) ?? $value;

        foreach (self::cases() as $case) {
            if ($compact === preg_replace('/[\s\-_]+/', '', $case->value)
                || $compact === preg_replace('/[\s\-_]+/', '', strtolower($case->label()))) {
                return $case->value;
            }
        }

        $aliases = [
            'fruit' => self::Fruits->value,
            'veg' => self::Vegetables->value,
            'veggie' => self::Vegetables->value,
            'vegetable' => self::Vegetables->value,
            'veggies' => self::Vegetables->value,
            'fish' => self::Seafood->value,
            'seafood' => self::Seafood->value,
            'chicken' => self::Poultry->value,
            'bird' => self::Poultry->value,
            'beef' => self::Meat->value,
            'lamb' => self::Meat->value,
            'cafe' => self::Restaurant->value,
            'café' => self::Restaurant->value,
            'resturant' => self::Restaurant->value, // common typo
            'grocery' => self::Other->value,
            'groceries' => self::Other->value,
            'supplier' => self::Other->value,
            'general' => self::Other->value,
            'food' => self::Other->value,
            'store' => self::Other->value,
            'shop' => self::Other->value,
        ];

        return $aliases[$compact] ?? null;
    }
}
