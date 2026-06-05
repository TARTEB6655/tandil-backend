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
}
