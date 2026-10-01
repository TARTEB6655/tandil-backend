<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates dummy payment/transaction data.
     */
    public function run()
    {
        $this->command->info('💳 Creating payment and transaction data...');

        // Get orders that don't have transactions yet
        $orders = Order::where('payment_status', 'paid')
            ->whereDoesntHave('transactions')
            ->get();

        if ($orders->isEmpty()) {
            $this->command->warn('No paid orders found. Creating transactions for existing orders...');
            $orders = Order::take(10)->get();
        }

        $gateways = ['paypal', 'stripe', 'bank_transfer', 'cash'];
        $statuses = ['completed', 'pending', 'failed', 'refunded'];
        $paymentMethods = ['credit_card', 'debit_card', 'paypal', 'bank_transfer', 'cash'];

        $transactionsCreated = 0;

        foreach ($orders as $order) {
            // Create transaction for paid orders
            if ($order->payment_status === 'paid' || rand(0, 1)) {
                $transaction = Transaction::create([
                    'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
                    'transactionable_type' => Order::class,
                    'transactionable_id' => $order->id,
                    'type' => 'payment',
                    'gateway' => $gateways[array_rand($gateways)],
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'amount' => $order->total_amount,
                    'currency' => 'USD',
                    'status' => $statuses[array_rand($statuses)],
                    'gateway_transaction_id' => 'GW-' . strtoupper(Str::random(15)),
                    'gateway_response' => [
                        'status' => 'success',
                        'message' => 'Payment processed successfully',
                        'transaction_id' => 'GW-' . strtoupper(Str::random(15)),
                        'timestamp' => Carbon::now()->toIso8601String(),
                    ],
                    'notes' => 'Payment processed via ' . $gateways[array_rand($gateways)],
                    'processed_at' => $order->paid_at ?? Carbon::now()->subDays(rand(1, 30)),
                ]);

                $transactionsCreated++;
            }
        }

        $this->command->info("✅ Created {$transactionsCreated} payment transactions!");
    }
}

