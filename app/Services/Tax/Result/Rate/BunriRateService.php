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
        'bunri_kazeishotoku_choki_shotoku_curr',
        'bunri_kazeishotoku_joto_shotoku_curr',
        'bunri_kazeishotoku_haito_shotoku_curr',
        'bunri_kazeishotoku_sakimono_shotoku_curr',
    ];

    public function determineMinimumRate(PayloadAccessor $accessor): ?float
    {
        if ($accessor->isPositive('bunri_kazeishotoku_tanki_shotoku_curr')) {
            return self::SHORT_TERM_RATE;
        }

        foreach (self::OTHER_KEYS as $key) {
            if ($accessor->isPositive($key)) {
                return self::OTHER_RATE;
            }
        }

        return null;
    }
}