<?php

namespace App\Http\Requests;

class StoreSubscriptionRequest extends BaseFormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'plan' => 'required|string|in:1_month,3_month,6_month,12_month',
            'plan_name' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'picture' => 'nullable',
            'image' => 'nullable',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|string|in:pending,paid,failed,refunded,cancelled',
            'payment_reference' => 'nullable|string|max:255',
            'client_id' => 'nullable|exists:users,id', // Only admins can set this
            'apply_to_all' => 'nullable|boolean',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'nullable|exists:users,id',
            'completed_visits' => 'nullable|integer|min:0',
        ];
    }
}
