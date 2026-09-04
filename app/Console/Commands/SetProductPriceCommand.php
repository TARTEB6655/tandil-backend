<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class SetProductPriceCommand extends Command
{
    protected $signature = 'product:set-price
                            {product : Product id, sku, or handle}
                            {price : New catalog price}
                            {--name= : Optional exact name match (e.g. "service product")}';

    protected $description = 'Update a product catalog price (does not change global service pricing rate)';

    public function handle(): int
    {
        $key = (string) $this->argument('product');
        $price = round((float) $this->argument('price'), 2);
        $name = $this->option('name');

        if ($price < 0) {
            $this->error('Price must be >= 0.');

            return self::FAILURE;
        }

        $query = Product::query();
        if (ctype_digit($key)) {
            $query->where('id', (int) $key);
        } else {
            $query->where(function ($q) use ($key) {
                $q->where('sku', $key)->orWhere('handle', $key);
            });
        }

        if (is_string($name) && $name !== '') {
            $query->where('name', $name);
        }

        $product = $query->first();
        if ($product === null) {
            $this->error('Product not found: '.$key);

            return self::FAILURE;
        }

        $before = (float) $product->price;
        $product->price = $price;
        $product->save();

        $this->info(sprintf(
            'Updated product #%d "%s": %s → %s',
            $product->id,
            $product->name,
            $before,
            $price
        ));

        return self::SUCCESS;
    }
}
