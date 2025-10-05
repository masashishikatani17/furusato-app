<?php
namespace App\Domain\Tax\DTO;

final class FurusatoInput
{
    public function __construct(
        public int $w17,   // 計算結果!W17
        public int $w18,   // 計算結果!W18
        public int $ab6,   // 計算結果!AB6
        public int $ab56,  // 計算結果!AB56（vol23）
        public int $v6 = 0, // 計算結果!V6（モードA）
        public int $w6 = 0, // 計算結果!W6（モードB）
        public int $x6 = 0, // 計算結果!X6（モードC）
        public int $householdComposition = 0,
        public int $spouseStatus = 0,
        public int $spouseIncomeClass = 0,
        public int $taxpayerAgeCategory = 0,
        public int $spouseAgeCategory = 0,
        public int $numDependents = 0,
        public int $numMinorDependents = 0,
        public int $numElderDependents = 0,
        public int $numSpecialDependents = 0,
        public int $numDisabledDependents = 0,
        public int $prefectureCode = 0,
        public int $municipalityCode = 0,
        public int $residenceType = 0,
        public bool $isSpecialCollection = false,
        public bool $isBlueReturn = false,
        public bool $isNewResident = false,
        public int $salaryIncome = 0,
        public int $bonusIncome = 0,
        public int $businessIncome = 0,
        public int $realEstateIncome = 0,
        public int $pensionIncome = 0,
        public int $dividendIncome = 0,
        public int $interestIncome = 0,
        public int $capitalGainIncome = 0,
        public int $temporaryIncome = 0,
        public int $otherIncome = 0,
        public int $socialInsurancePremium = 0,
        public int $lifeInsurancePremium = 0,
        public int $earthquakeInsurancePremium = 0,
        public int $medicalExpenseDeduction = 0,
        public int $smallEnterpriseMutualAid = 0,
        public int $spouseDeductionAmount = 0,
        public int $specialSpouseDeductionAmount = 0,
        public int $dependentDeductionAmount = 0,
        public int $disabilityDeductionAmount = 0,
        public int $widowWidowerDeductionAmount = 0,
        public int $singleParentDeductionAmount = 0,
        public int $workingStudentDeductionAmount = 0,
        public int $basicDeductionAmount = 0,
        public int $donationDeductionAmount = 0,
        public int $housingLoanDeductionAmount = 0,
        public int $furusatoDonationTotal = 0,
        public int $otherDonationTotal = 0,
        public float $prefecturalIncomeTaxRate = 0.0,
        public float $municipalIncomeTaxRate = 0.0,
        public int $prefecturalEqualShare = 0,
        public int $municipalEqualShare = 0,
        public int $taxationMethod = 0,
        public string $notes = '',
        public float $q2 = 0.0,
        public float $q3 = 0.0,
        public float $q4 = 0.0,
        public float $q5 = 0.0,
    ) {}
}