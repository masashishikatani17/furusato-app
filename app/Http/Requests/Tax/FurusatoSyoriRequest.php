<?php

namespace App\Http\Requests\Tax;

use Illuminate\Validation\Rules;
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
            'pref_applied_rate_prev' => ['bail', 'nullable', 'numeric', 'min:0', 'max:100'],
            'pref_applied_rate_curr' => ['bail', 'nullable', 'numeric', 'min:0', 'max:100'],
            'muni_applied_rate_prev' => ['bail', 'nullable', 'numeric', 'min:0', 'max:100'],
            'muni_applied_rate_curr' => ['bail', 'nullable', 'numeric', 'min:0', 'max:100'],
            'pref_equal_share_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'pref_equal_share_curr' => ['bail', 'nullable', 'integer', 'min:0'],
            'muni_equal_share_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'muni_equal_share_curr' => ['bail', 'nullable', 'integer', 'min:0'],
            'other_taxes_amount_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'other_taxes_amount_curr' => ['bail', 'nullable', 'integer', 'min:0'],       
            'data_id' => ['bail', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'pref_applied_rate_prev.max' => '都道府県（適用）は 0〜100 の範囲で入力してください（%）。',
            'pref_applied_rate_curr.max' => '都道府県（適用）は 0〜100 の範囲で入力してください（%）。',
            'muni_applied_rate_prev.max' => '市区町村（適用）は 0〜100 の範囲で入力してください（%）。',
            'muni_applied_rate_curr.max' => '市区町村（適用）は 0〜100 の範囲で入力してください（%）。',
        ];
    }
}