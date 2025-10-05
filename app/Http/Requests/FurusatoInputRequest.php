<?php

namespace App\Http\Requests;

use App\Domain\Tax\DTO\FurusatoInput;
use Illuminate\Foundation\Http\FormRequest;

final class FurusatoInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $integerRange = ['bail', 'nullable', 'integer', 'min:0'];
        $requiredIntegerRange = ['bail', 'required', 'integer', 'min:0'];
        $flagRule = ['bail', 'required', 'integer', 'in:0,1'];

        return [
            'data_id' => ['bail', 'nullable', 'integer'],
            'w17' => $requiredIntegerRange,
            'w18' => $requiredIntegerRange,
            'ab6' => $requiredIntegerRange,
            'ab56' => ['bail', 'required', 'integer', 'min:1'],
            'v6' => ['bail', 'nullable', 'integer', 'in:0,1,2'],
            'w6' => ['bail', 'nullable', 'integer', 'in:0,1,2'],
            'x6' => ['bail', 'nullable', 'integer', 'in:0,1,2'],
            'household_composition' => $integerRange,
            'spouse_status' => $integerRange,
            'spouse_income_class' => $integerRange,
            'taxpayer_age_category' => $integerRange,
            'spouse_age_category' => $integerRange,
            'num_dependents' => $integerRange,
            'num_minor_dependents' => $integerRange,
            'num_elder_dependents' => $integerRange,
            'num_special_dependents' => $integerRange,
            'num_disabled_dependents' => $integerRange,
            'prefecture_code' => $integerRange,
            'municipality_code' => $integerRange,
            'residence_type' => $integerRange,
            'special_collection_flag' => $flagRule,
            'blue_return_flag' => $flagRule,
            'new_resident_flag' => $flagRule,
            'salary_income' => $integerRange,
            'bonus_income' => $integerRange,
            'business_income' => $integerRange,
            'real_estate_income' => $integerRange,
            'pension_income' => $integerRange,
            'dividend_income' => $integerRange,
            'interest_income' => $integerRange,
            'capital_gain_income' => $integerRange,
            'temporary_income' => $integerRange,
            'other_income' => $integerRange,
            'social_insurance_premium' => $integerRange,
            'life_insurance_premium' => $integerRange,
            'earthquake_insurance_premium' => $integerRange,
            'medical_expense_deduction' => $integerRange,
            'small_enterprise_mutual_aid' => $integerRange,
            'spouse_deduction_amount' => $integerRange,
            'special_spouse_deduction_amount' => $integerRange,
            'dependent_deduction_amount' => $integerRange,
            'disability_deduction_amount' => $integerRange,
            'widow_widower_deduction_amount' => $integerRange,
            'single_parent_deduction_amount' => $integerRange,
            'working_student_deduction_amount' => $integerRange,
            'basic_deduction_amount' => $integerRange,
            'donation_deduction_amount' => $integerRange,
            'housing_loan_deduction_amount' => $integerRange,
            'prefectural_income_tax_rate' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'municipal_income_tax_rate' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'prefectural_equal_share' => $integerRange,
            'municipal_equal_share' => $integerRange,
            'taxation_method' => $integerRange,
            'notes' => ['bail', 'nullable', 'string', 'max:1000'],
            'q2' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'q3' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'q4' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
            'q5' => ['bail', 'nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }

    public function toDto(): FurusatoInput
    {
        return new FurusatoInput(
            w17: (int) $this->input('w17'),
            w18: (int) $this->input('w18'),
            ab6: (int) $this->input('ab6'),
            ab56: max(1, (int) $this->input('ab56')),
            v6: (int) $this->input('v6', 0),
            w6: (int) $this->input('w6', 0),
            x6: (int) $this->input('x6', 0),
            household_composition: (int) $this->input('household_composition', 0),
            spouse_status: (int) $this->input('spouse_status', 0),
            spouse_income_class: (int) $this->input('spouse_income_class', 0),
            taxpayer_age_category: (int) $this->input('taxpayer_age_category', 0),
            spouse_age_category: (int) $this->input('spouse_age_category', 0),
            num_dependents: (int) $this->input('num_dependents', 0),
            num_minor_dependents: (int) $this->input('num_minor_dependents', 0),
            num_elder_dependents: (int) $this->input('num_elder_dependents', 0),
            num_special_dependents: (int) $this->input('num_special_dependents', 0),
            num_disabled_dependents: (int) $this->input('num_disabled_dependents', 0),
            prefecture_code: (int) $this->input('prefecture_code', 0),
            municipality_code: (int) $this->input('municipality_code', 0),
            residence_type: (int) $this->input('residence_type', 0),
            special_collection_flag: (int) $this->input('special_collection_flag'),
            blue_return_flag: (int) $this->input('blue_return_flag'),
            new_resident_flag: (int) $this->input('new_resident_flag'),
            salary_income: (int) $this->input('salary_income', 0),
            bonus_income: (int) $this->input('bonus_income', 0),
            business_income: (int) $this->input('business_income', 0),
            real_estate_income: (int) $this->input('real_estate_income', 0),
            pension_income: (int) $this->input('pension_income', 0),
            dividend_income: (int) $this->input('dividend_income', 0),
            interest_income: (int) $this->input('interest_income', 0),
            capital_gain_income: (int) $this->input('capital_gain_income', 0),
            temporary_income: (int) $this->input('temporary_income', 0),
            other_income: (int) $this->input('other_income', 0),
            social_insurance_premium: (int) $this->input('social_insurance_premium', 0),
            life_insurance_premium: (int) $this->input('life_insurance_premium', 0),
            earthquake_insurance_premium: (int) $this->input('earthquake_insurance_premium', 0),
            medical_expense_deduction: (int) $this->input('medical_expense_deduction', 0),
            small_enterprise_mutual_aid: (int) $this->input('small_enterprise_mutual_aid', 0),
            spouse_deduction_amount: (int) $this->input('spouse_deduction_amount', 0),
            special_spouse_deduction_amount: (int) $this->input('special_spouse_deduction_amount', 0),
            dependent_deduction_amount: (int) $this->input('dependent_deduction_amount', 0),
            disability_deduction_amount: (int) $this->input('disability_deduction_amount', 0),
            widow_widower_deduction_amount: (int) $this->input('widow_widower_deduction_amount', 0),
            single_parent_deduction_amount: (int) $this->input('single_parent_deduction_amount', 0),
            working_student_deduction_amount: (int) $this->input('working_student_deduction_amount', 0),
            basic_deduction_amount: (int) $this->input('basic_deduction_amount', 0),
            donation_deduction_amount: (int) $this->input('donation_deduction_amount', 0),
            housing_loan_deduction_amount: (int) $this->input('housing_loan_deduction_amount', 0),
            prefectural_income_tax_rate: (float) $this->input('prefectural_income_tax_rate', 0.0),
            municipal_income_tax_rate: (float) $this->input('municipal_income_tax_rate', 0.0),
            prefectural_equal_share: (int) $this->input('prefectural_equal_share', 0),
            municipal_equal_share: (int) $this->input('municipal_equal_share', 0),
            taxation_method: (int) $this->input('taxation_method', 0),
            notes: (string) $this->input('notes', ''),
            q2: (float) $this->input('q2', 0.0),
            q3: (float) $this->input('q3', 0.0),
            q4: (float) $this->input('q4', 0.0),
            q5: (float) $this->input('q5', 0.0),
        );
    }
}