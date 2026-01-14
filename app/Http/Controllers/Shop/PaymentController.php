<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Get payment transactions for authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Transaction::whereHasMorph(
            'transactionable',
            [Order::class],
            function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }
        )->with('transactionable')
        ->latest();
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by gateway
        if ($request->has('gateway')) {
            $query->where('gateway', $request->gateway);
        }
        
        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);
        
        return response()->json([
            'status' => true,
            'message' => 'Transactions retrieved successfully',
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ]
        ]);
    }

    /**
     * Get single transaction details.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $transaction = Transaction::with('transactionable')
            ->find($id);
        
        if (!$transaction) {
            return response()->json([
                'status' => false,
                'message' => 'Transaction not found'
            ], 404);
        }
        
        // Check if transaction belongs to user's order
        if ($transaction->transactionable_type === Order::class) {
            $order = $transaction->transactionable;
            if ($order && $order->user_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Forbidden'
                ], 403);
            }
        }
        
        return response()->json([
            'status' => true,
            'message' => 'Transaction retrieved successfully',
            'data' => $transaction
        ]);
    }
}

