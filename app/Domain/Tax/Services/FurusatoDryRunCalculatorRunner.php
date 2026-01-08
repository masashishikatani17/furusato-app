<?php

namespace App\Domain\Tax\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * tax.furusato.calculators（tagged calculators）をDB保存なしでdry-run実行する。
 *
 * - RecalculateFurusatoPayload と同様に、各Calculatorの ::ID / ::ORDER / ::AFTER / ::BEFORE を解釈して
 *   トポロジカルソートを行い、安定した順序で compute() を実行する。
 * - ふるさと納税上限探索（yを何度も試す）に使うため、処理は副作用（DB/Session）なし。
 */
final class FurusatoDryRunCalculatorRunner
{
    /** @var array<int, object> */
    private array $sorted;

    /**
     * AppServiceProvider で tag された Calculator 群（tax.furusato.calculators）を取得して並べ替える。
     */
    public function __construct(
        Container $container,
    ) {
        // NOTE:
        //   環境によっては PHP Attributes（#[Tagged]）が有効にならず、
        //   コンテナが iterable $calculators を解決できないことがある。
        //   そのため、確実に動く tagged() で取得する。
        $calculators = $container->tagged('tax.furusato.calculators');
        $this->sorted = $this->sortCalculators($calculators);
    }

    /**
     * @param  array<string,mixed> $payload
     * @param  array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    public function run(array $payload, array $ctx): array
    {
        foreach ($this->sorted as $calculator) {
            if (method_exists($calculator, 'compute')) {
                /** @var callable $fn */
                $fn = [$calculator, 'compute'];
                $payload = $fn($payload, $ctx);
            }
        }
        return $payload;
    }

    /**
     * @param  iterable<int, object> $calculators
     * @return array<int, object>
     */
    private function sortCalculators(iterable $calculators): array
    {
        $nodes = [];

        foreach ($calculators as $calculator) {
            $class = $calculator::class;
            $id = \defined($class . '::ID') ? $class::ID : $class;
            $order = \defined($class . '::ORDER') ? (int) $class::ORDER : 1000;
            $before = \defined($class . '::BEFORE') ? (array) $class::BEFORE : [];
            $after  = \defined($class . '::AFTER')  ? (array) $class::AFTER  : [];

            if (isset($nodes[$id])) {
                // ID重複は「先勝ち」で落とす（本番と同じ思想）
                continue;
            }

            $nodes[$id] = [
                'calculator' => $calculator,
                'order' => $order,
                'before' => array_values(array_filter($before, 'is_string')),
                'after'  => array_values(array_filter($after,  'is_string')),
            ];
        }

        $edges = [];
        $inDegree = [];
        foreach ($nodes as $id => $_) {
            $edges[$id] = [];
            $inDegree[$id] = 0;
        }

        // AFTER: afterId -> id
        // BEFORE: id -> beforeId
        foreach ($nodes as $id => $meta) {
            foreach ($meta['after'] as $afterId) {
                if (!isset($nodes[$afterId])) {
                    continue;
                }
                $edges[$afterId][] = $id;
                $inDegree[$id]++;
            }
            foreach ($meta['before'] as $beforeId) {
                if (!isset($nodes[$beforeId])) {
                    continue;
                }
                $edges[$id][] = $beforeId;
                $inDegree[$beforeId]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) $queue[] = $id;
        }

        $sortedIds = [];
        while ($queue !== []) {
            usort($queue, function ($a, $b) use ($nodes) {
                $oa = $nodes[$a]['order'];
                $ob = $nodes[$b]['order'];
                if ($oa === $ob) return $a <=> $b;
                return $oa <=> $ob;
            });
            $cur = array_shift($queue);
            $sortedIds[] = $cur;
            foreach ($edges[$cur] ?? [] as $next) {
                $inDegree[$next]--;
                if ($inDegree[$next] === 0) $queue[] = $next;
            }
            unset($edges[$cur]);
        }

        // cycle safety
        if (count($sortedIds) !== count($nodes)) {
            Log::warning('[dryrun] calculator ordering contains a cycle; fallback to ORDER sort');
            $remaining = array_diff(array_keys($nodes), $sortedIds);
            usort($remaining, function ($a, $b) use ($nodes) {
                $oa = $nodes[$a]['order'];
                $ob = $nodes[$b]['order'];
                if ($oa === $ob) return $a <=> $b;
                return $oa <=> $ob;
            });
            $sortedIds = array_merge($sortedIds, $remaining);
        }

        return array_map(static fn($id) => $nodes[$id]['calculator'], $sortedIds);
    }
}
