<?php

return [
    'enabled' => (bool) env('TRUTHSHIELD_TRAFFIC_ENABLED', true),
    'status_sample_rate' => (float) env('TRUTHSHIELD_TRAFFIC_STATUS_SAMPLE_RATE', 1.0),
    'raw_retention_days' => (int) env('TRUTHSHIELD_TRAFFIC_RAW_RETENTION_DAYS', 14),
    'summary_retention_days' => (int) env('TRUTHSHIELD_TRAFFIC_SUMMARY_RETENTION_DAYS', 400),
    'record_api_requests' => (bool) env('TRUTHSHIELD_TRAFFIC_RECORD_API_REQUESTS', true),
];
