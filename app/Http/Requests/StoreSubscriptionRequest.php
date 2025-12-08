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
            'start_date' => 'nullable|date',
            'amount' => 'nullable|numeric',
        ];
    }
}
