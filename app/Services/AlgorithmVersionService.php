<?php

namespace App\Services;

use App\Models\AlgorithmVersion;

class AlgorithmVersionService
{
    public function ensureCurrent(): AlgorithmVersion
    {
        $version = config('truthshield.algorithm_version', 'truthshield-v1');

        return AlgorithmVersion::query()->firstOrCreate(
            ['version' => $version],
            [
                'status' => 'active',
                'summary' => 'Weighted consensus using trust score, identity multiplier, abuse multiplier, evidence helpfulness, and 72-hour finalization.',
                'rules' => [
                    'vote_weight' => 'trust_score * identity_multiplier * abuse_multiplier',
                    'finalization_window_hours' => 72,
                    'evidence_bonus' => true,
                    'minority_bonus' => true,
                ],
                'activated_at' => now(),
            ],
        );
    }
}
