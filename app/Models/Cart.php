<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Cart extends Model
{
    protected $fillable = ['user_id', 'product_id', 'quantity', 'selected_options', 'unit_price', 'required_area', 'booking_date', 'booking_slot'];

    protected $casts = [
        'selected_options' => 'array',
        'unit_price' => 'float',
        'required_area' => 'float',
        'booking_date' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  array<int|string>|null  $raw
     * @return array<int>
     */
    public static function normalizeSelectedOptionIds(?array $raw): array
    {
        return collect($raw ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Resolved per-unit price for checkout (stored unit_price or base + option modifiers).
     */
    public function lineUnitPrice(): float
    {
        if ($this->unit_price !== null && $this->unit_price >= 0) {
            return round((float) $this->unit_price, 2);
        }

        $product = $this->product;
        if (! $product) {
            return 0.0;
        }

        return self::calculateUnitPrice($product, $this->selected_options ?? []);
    }

    /**
     * @param  array<int|string>|Collection<int, int>|null  $selectedOptionIds
     */
    public static function calculateUnitPrice(Product $product, array|Collection|null $selectedOptionIds = null): float
    {
        $base = round((float) $product->price, 2);
        if (($product->product_type ?? 'simple') !== 'variable') {
            return $base;
        }

        $ids = $selectedOptionIds instanceof Collection
            ? $selectedOptionIds->map(fn ($v) => (int) $v)->all()
            : self::normalizeSelectedOptionIds(is_array($selectedOptionIds) ? $selectedOptionIds : []);

        if ($ids === []) {
            return $base;
        }

        $modifier = (float) ProductOption::whereIn('id', $ids)->sum('price_modifier');

        return max(0, round($base + $modifier, 2));
    }

    /**
     * Whether this cart line has all required variant selections (for variable products).
     */
    public static function cartLineIsComplete(Cart $item): bool
    {
        $product = $item->product;
        if (! $product) {
            return false;
        }

        if (($product->product_type ?? 'simple') !== 'variable') {
            return true;
        }

        if (! $product->relationLoaded('optionGroups')) {
            $product->load(['optionGroups.options']);
        }

        if ($product->optionGroups->isEmpty()) {
            return true;
        }

        return self::validateSelectedOptionsMessage($product, $item->selected_options ?? []) === null;
    }

    /**
     * Validate selected options for a variable product. Returns error message or null if OK.
     *
     * @param  array<int|string>|Collection<int, int>|null  $selectedOptionIds
     */
    public static function validateSelectedOptionsMessage(Product $product, array|Collection|null $selectedOptionIds = null): ?string
    {
        if (($product->product_type ?? 'simple') !== 'variable') {
            return null;
        }

        if (! $product->relationLoaded('optionGroups')) {
            $product->load('optionGroups.options');
        }

        $selected = $selectedOptionIds instanceof Collection
            ? $selectedOptionIds->map(fn ($v) => (int) $v)->unique()->values()
            : collect(self::normalizeSelectedOptionIds(is_array($selectedOptionIds) ? $selectedOptionIds : []));

        $productOptionIds = $product->optionGroups
            ->flatMap(fn ($g) => $g->options->pluck('id'))
            ->map(fn ($id) => (int) $id);

        $invalid = $selected->diff($productOptionIds);
        if ($invalid->isNotEmpty()) {
            return 'Invalid option selected for this product.';
        }

        foreach ($product->optionGroups as $group) {
            $groupOptionIds = $group->options->pluck('id')->map(fn ($id) => (int) $id);
            $selectedInGroup = $selected->intersect($groupOptionIds)->values();

            if ($group->is_required && $selectedInGroup->isEmpty()) {
                return "Please select required option(s) for {$group->name}.";
            }
            if ($group->input_type === 'single' && $selectedInGroup->count() > 1) {
                return "Only one option can be selected for {$group->name}.";
            }
        }

        return null;
    }

    /**
     * Selected options grouped for cart / checkout UI (group name, label, image, modifier).
     *
     * @param  array<int|string>|null  $selectedOptionIds
     * @return list<array{
     *   group_name: string,
     *   option_id: int,
     *   label: string,
     *   subtitle: ?string,
     *   image_url: ?string,
     *   price_modifier: float
     * }>
     */
    public static function resolveSelectedOptionsDisplay(Product $product, ?array $selectedOptionIds = null): array
    {
        $ids = self::normalizeSelectedOptionIds($selectedOptionIds);
        if ($ids === []) {
            return [];
        }

        if (! $product->relationLoaded('optionGroups')) {
            $product->load(['optionGroups.options']);
        }

        $selectedLookup = array_fill_keys($ids, true);
        $lines = [];

        foreach ($product->optionGroups->sortBy('sort_order') as $group) {
            foreach ($group->options->sortBy('sort_order') as $option) {
                if (! isset($selectedLookup[(int) $option->id])) {
                    continue;
                }

                $lines[] = [
                    'group_name' => (string) $group->name,
                    'option_id' => (int) $option->id,
                    'label' => (string) $option->label,
                    'subtitle' => $option->subtitle ? (string) $option->subtitle : null,
                    'image_url' => $option->image_url,
                    'price_modifier' => round((float) $option->price_modifier, 2),
                ];
            }
        }

        return $lines;
    }

    /**
     * Line payload stored on ShopMobileCheckout / Stripe fingerprint.
     *
     * @return array{product_id: int, quantity: int, unit_price: float, required_area: ?float, selected_options: array<int>}
     */
    public function checkoutLinePayload(): array
    {
        return [
            'product_id' => (int) $this->product_id,
            'quantity' => (int) $this->quantity,
            'unit_price' => $this->lineUnitPrice(),
            'required_area' => $this->required_area !== null ? round((float) $this->required_area, 2) : null,
            'selected_options' => self::normalizeSelectedOptionIds($this->selected_options),
            'booking_date' => $this->booking_date?->toDateString(),
            'booking_slot' => $this->booking_slot,
        ];
    }

    /**
     * Money total for this cart line (supports per-m² service pricing).
     */
    public function lineTotalAmount(): float
    {
        $product = $this->product;
        if (! $product) {
            return round((int) $this->quantity * $this->lineUnitPrice(), 2);
        }

        return \App\Support\ServiceAreaPricing::lineTotal(
            $product,
            $this->lineUnitPrice(),
            (int) $this->quantity,
            $this->required_area !== null ? (float) $this->required_area : null
        );
    }
}
