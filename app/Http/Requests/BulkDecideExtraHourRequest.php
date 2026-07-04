<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDecideExtraHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'payroll_user_ids' => 'required|array|min:1',
            'payroll_user_ids.*' => 'integer|exists:payroll_user,id',
            'status' => 'required|in:approved,rejected',
            'approved_extra_hours' => 'nullable|integer|min:0',
            'approved_extra_minutes' => 'nullable|integer|min:0|max:59',
            'comments' => 'nullable|string|max:500',
        ];
    }
}
