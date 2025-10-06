<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

final class FurusatoSyoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'detail_mode' => ['bail', 'required', 'integer', 'in:0,1'],
            'bunri_flag' => ['bail', 'required', 'integer', 'in:0,1'],
            'one_stop_flag' => ['bail', 'required', 'integer', 'in:0,1'],
            'shitei_toshi_flag' => ['bail', 'required', 'integer', 'in:0,1'],
            'pref_standard_rate' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'muni_standard_rate' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'pref_applied_rate' => ['bail', 'required', 'numeric', 'min:0', 'max:1'],
            'muni_applied_rate' => ['bail', 'required', 'numeric', 'min:0', 'max:1'],
            'pref_equal_share' => ['bail', 'required', 'integer', 'min:0'],
            'muni_equal_share' => ['bail', 'required', 'integer', 'min:0'],
            'other_taxes_amount' => ['bail', 'required', 'integer', 'min:0'],
            'data_id' => ['bail', 'nullable', 'integer', 'min:1'],
        ];
    }
}