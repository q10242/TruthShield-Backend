<?php

return [
    'min_distinct_users' => (int) env('TRUTHSHIELD_COMMUNITY_MIN_USERS', 3),
    'trust_floor' => (float) env('TRUTHSHIELD_COMMUNITY_TRUST_FLOOR', 1.0),
    'thresholds' => [
        'domain_report' => (float) env('TRUTHSHIELD_COMMUNITY_DOMAIN_SCORE', 6.0),
        'url_classification' => (float) env('TRUTHSHIELD_COMMUNITY_URL_RULE_SCORE', 6.0),
        'trusted_source' => (float) env('TRUTHSHIELD_COMMUNITY_SOURCE_SCORE', 8.0),
        'evidence_unhelpful' => (float) env('TRUTHSHIELD_COMMUNITY_EVIDENCE_UNHELPFUL_SCORE', 4.0),
        'controversy_total_weight' => (float) env('TRUTHSHIELD_COMMUNITY_CONTROVERSY_WEIGHT', 4.0),
        'official_response_request' => (float) env('TRUTHSHIELD_COMMUNITY_OFFICIAL_RESPONSE_SCORE', 3.0),
        'fact_check_request' => (float) env('TRUTHSHIELD_COMMUNITY_FACT_CHECK_REQUEST_SCORE', 3.0),
        'event_creation_request' => (float) env('TRUTHSHIELD_COMMUNITY_EVENT_CREATION_REQUEST_SCORE', 3.0),
    ],
    'high_risk_source_types' => ['media', 'political_ad', 'sponsored'],
    'high_risk_domain_keywords' => ['ad', 'ads', 'promo', 'campaign', 'sponsor'],
    'task_stale_days' => 14,
    'completion_trust_bonus' => [
        'domain_candidate' => (float) env('TRUTHSHIELD_COMMUNITY_DOMAIN_BONUS', 0.02),
        'url_rule_candidate' => (float) env('TRUTHSHIELD_COMMUNITY_URL_RULE_BONUS', 0.02),
        'trusted_source_candidate' => (float) env('TRUTHSHIELD_COMMUNITY_TRUSTED_SOURCE_BONUS', 0.03),
        'evidence_quality_review' => (float) env('TRUTHSHIELD_COMMUNITY_EVIDENCE_QUALITY_BONUS', 0.03),
        'fact_check_request' => (float) env('TRUTHSHIELD_COMMUNITY_FACT_CHECK_BONUS', 0.04),
        'event_creation_request' => (float) env('TRUTHSHIELD_COMMUNITY_EVENT_CREATION_BONUS', 0.05),
    ],
];
