<?php

namespace Tests\Feature;

use Tests\TestCase;

final class FurusatoInputAllFieldsTest extends TestCase
{
    /** @test */
    public function it_accepts_full_input_payload_and_renders_old_values(): void
    {
        $payload = [
            'w17' => 2_000_000,
            'w18' => 3_000_000,
            'ab6' => 300_000,
            'ab56' => 10_000,
            'v6' => 1,
            'w6' => 2,
            'x6' => 0,
            'household_composition' => 2,
            'spouse_status' => 1,
            'spouse_income_class' => 2,
            'taxpayer_age_category' => 2,
            'spouse_age_category' => 1,
            'num_dependents' => 2,
            'num_minor_dependents' => 1,
            'num_elder_dependents' => 1,
            'num_special_dependents' => 0,
            'num_disabled_dependents' => 0,
            'prefecture_code' => 13,
            'municipality_code' => 13101,
            'residence_type' => 2,
            'is_special_collection' => 1,
            'is_blue_return' => 1,
            'is_new_resident' => 0,
            'salary_income' => 4_500_000,
            'bonus_income' => 500_000,
            'business_income' => 300_000,
            'real_estate_income' => 120_000,
            'pension_income' => 0,
            'dividend_income' => 50_000,
            'interest_income' => 5_000,
            'capital_gain_income' => 80_000,
            'temporary_income' => 20_000,
            'other_income' => 15_000,
            'social_insurance_premium' => 600_000,
            'life_insurance_premium' => 120_000,
            'earthquake_insurance_premium' => 30_000,
            'medical_expense_deduction' => 100_000,
            'small_enterprise_mutual_aid' => 240_000,
            'spouse_deduction_amount' => 380_000,
            'special_spouse_deduction_amount' => 110_000,
            'dependent_deduction_amount' => 630_000,
            'disability_deduction_amount' => 270_000,
            'widow_widower_deduction_amount' => 270_000,
            'single_parent_deduction_amount' => 350_000,
            'working_student_deduction_amount' => 270_000,
            'basic_deduction_amount' => 480_000,
            'donation_deduction_amount' => 200_000,
            'housing_loan_deduction_amount' => 120_000,
            'furusato_donation_total' => 150_000,
            'other_donation_total' => 25_000,
            'prefectural_income_tax_rate' => 0.045,
            'municipal_income_tax_rate' => 0.055,
            'prefectural_equal_share' => 1_500,
            'municipal_equal_share' => 3_000,
            'taxation_method' => 2,
            'notes' => '将来メモをここに記入',
            'q2' => 0.30,
            'q3' => 0.25,
            'q4' => 0.20,
            'q5' => 0.15,
        ];

        $response = $this->post('/furusato/calc', $payload);

        $response->assertStatus(200);
        $response->assertViewHas('out');
        $response->assertViewHas('donation');

        $this->assertNotEmpty(session()->getOldInput());
        $this->assertSame('将来メモをここに記入', session()->getOldInput()['notes'] ?? null);

        $response->assertSee('value="4500000"', false);
        $response->assertSee('将来メモをここに記入', false);
        $response->assertSee('特別徴収変更', false);

        $donation = $response->viewData('donation');
        $this->assertIsArray($donation);
        $this->assertArrayHasKey('rows', $donation);
        $this->assertCount(4, $donation['rows']);

        $out = $response->viewData('out');
        $this->assertSame([
            'v6' => $payload['v6'],
            'w6' => $payload['w6'],
            'x6' => $payload['x6'],
        ], $out['flags']);
    }
}