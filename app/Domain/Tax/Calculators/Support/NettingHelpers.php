<?php

namespace App\Domain\Tax\Calculators\Support;

final class NettingHelpers
{
    /**
     * 山林と（長期→短期→経常）の相互補填（SogoShotokuNettingStages と同一規則）
     * 戻値: array{econ:int, short:int, long:int, forest:int, ichiji:int}
     */
    public static function netWithForest(int $econ, int $short, int $long, int $forest, int $ichiji): array
    {
        $econAfter   = $econ;
        $shortAfter  = $short;
        $longAfter   = $long;
        $forestAfter = $forest;
        $ichijiAfter = $ichiji;

        if ($forestAfter >= 0) {
            $use = min($forestAfter, max(0, -$longAfter));
            $forestAfter -= $use; $longAfter += $use;

            $use = min($forestAfter, max(0, -$shortAfter));
            $forestAfter -= $use; $shortAfter += $use;

            $use = min($forestAfter, max(0, -$econAfter));
            $forestAfter -= $use; $econAfter  += $use;
        } else {
            $need = max(0, -$forestAfter);

            $use = min(max(0, $econAfter), $need);
            $econAfter   -= $use; $forestAfter += $use;
            $need = max(0, -$forestAfter);

            $use = min(max(0, $shortAfter), $need);
            $shortAfter  -= $use; $forestAfter += $use;
            $need = max(0, -$forestAfter);

            $use = min(max(0, $longAfter), $need);
            $longAfter   -= $use; $forestAfter += $use;
            $need = max(0, -$forestAfter);

            $use = min($ichijiAfter, $need);
            $ichijiAfter -= $use; $forestAfter += $use;
        }

        return [$econAfter, $shortAfter, $longAfter, $forestAfter, $ichijiAfter];
    }

    /**
     * 退職 →（長期→短期→経常→山林）への充当（SogoShotokuNettingStages と同一規則）
     * 戻値: array{econ:int, short:int, long:int, forest:int, ichiji:int, retire:int}
     */
    public static function netWithRetirement(int $econ, int $short, int $long, int $forest, int $ichiji, int $retire): array
    {
        $econAfter   = $econ;
        $shortAfter  = $short;
        $longAfter   = $long;
        $forestAfter = $forest;
        $ichijiAfter = $ichiji;
        $retireAfter = $retire;

        $use = min($retireAfter, max(0, -$longAfter));
        $retireAfter -= $use; $longAfter += $use;

        $use = min($retireAfter, max(0, -$shortAfter));
        $retireAfter -= $use; $shortAfter += $use;

        $use = min($retireAfter, max(0, -$econAfter));
        $retireAfter -= $use; $econAfter  += $use;

        $use = min($retireAfter, max(0, -$forestAfter));
        $retireAfter -= $use; $forestAfter += $use;

        return [$econAfter, $shortAfter, $longAfter, $forestAfter, $ichijiAfter, $retireAfter];
    }
}