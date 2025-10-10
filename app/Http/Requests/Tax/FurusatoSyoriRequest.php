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
            'detail_mode_prev' => ['bail', 'required', 'integer', 'in:0,1'],
            'detail_mode_curr' => ['bail', 'required', 'integer', 'in:0,1'],
            'bunri_flag_prev' => ['bail', 'required', 'integer', 'in:0,1'],
            'bunri_flag_curr' => ['bail', 'required', 'integer', 'in:0,1'],
            'one_stop_flag_prev' => ['bail', 'required', 'integer', 'in:0,1'],
            'one_stop_flag_curr' => ['bail', 'required', 'integer', 'in:0,1'],
            'shitei_toshi_flag_prev' => ['bail', 'required', 'integer', 'in:0,1'],
            'shitei_toshi_flag_curr' => ['bail', 'required', 'integer', 'in:0,1'],
            'pref_standard_rate' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'muni_standard_rate' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'pref_applied_rate_prev' => ['bail', 'required', 'numeric', 'min:0', 'max:1'],
            'pref_applied_rate_curr' => ['bail', 'required', 'numeric', 'min:0', 'max:1'],
            'muni_applied_rate_prev' => ['bail', 'required', 'numeric', 'min:0', 'max:1'],
            'muni_applied_rate_curr' => ['bail', 'required', 'numeric', 'min:0', 'max:1'],
            'pref_equal_share_prev' => ['bail', 'required', 'integer', 'min:0'],
            'pref_equal_share_curr' => ['bail', 'required', 'integer', 'min:0'],
            'muni_equal_share_prev' => ['bail', 'required', 'integer', 'min:0'],
            'muni_equal_share_curr' => ['bail', 'required', 'integer', 'min:0'],
            'other_taxes_amount_prev' => ['bail', 'required', 'integer', 'min:0'],
            'other_taxes_amount_curr' => ['bail', 'required', 'integer', 'min:0'],          
            'data_id' => ['bail', 'nullable', 'integer', 'min:1'],
        ];
    }
}