<?php

namespace App\Services\Tax\Result\Rate;

use App\Services\Tax\Result\Support\PayloadAccessor;

final class BunriRateService
{
    private const SHORT_TERM_RATE = 0.59370;
    private const OTHER_RATE = 0.74685;

    /**
     * @var string[]
     */
    private const OTHER_KEYS = [
        'bunri_kazeishotoku_choki_jumin_%s',
        'bunri_kazeishotoku_joto_jumin_%s',
        'bunri_kazeishotoku_haito_jumin_%s',
        'bunri_kazeishotoku_sakimono_jumin_%s',
    ];

    public function minRateForSeparatedTaxes(array $payload, string $period): ?float
    {
        $shortTermKey = sprintf('bunri_kazeishotoku_tanki_jumin_%s', $period);
        $shortTerm = PayloadAccessor::intOrNull($payload, $shortTermKey);
        if ($shortTerm !== null && PayloadAccessor::nonNegativeFloat($shortTerm) > 0.0) {
            return self::SHORT_TERM_RATE;
        }

        foreach (self::OTHER_KEYS as $pattern) {
            $key = sprintf($pattern, $period);
            $value = PayloadAccessor::intOrNull($payload, $key);
            if ($value !== null && PayloadAccessor::nonNegativeFloat($value) > 0.0) {
                return self::OTHER_RATE;
            }
        }

        return null;
    }
}