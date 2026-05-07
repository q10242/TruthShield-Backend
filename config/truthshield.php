<?php

return [
    'status_cache_store' => env('TRUTHSHIELD_STATUS_CACHE_STORE', 'redis'),
    'status_cache_version' => env('TRUTHSHIELD_STATUS_CACHE_VERSION', 'v1'),
    'dev_login_enabled' => (bool) env('TRUTHSHIELD_DEV_LOGIN_ENABLED', env('APP_ENV') !== 'production'),

    'evidence_reaction_min_trust_score' => (float) env('TRUTHSHIELD_EVIDENCE_REACTION_MIN_TRUST_SCORE', 0.5),
    'low_trust_vote_cap' => (float) env('TRUTHSHIELD_LOW_TRUST_VOTE_CAP', 0.25),
    'min_read_seconds_before_vote' => (int) env('TRUTHSHIELD_MIN_READ_SECONDS_BEFORE_VOTE', 15),
    'evidence_snapshot_max_bytes' => (int) env('TRUTHSHIELD_EVIDENCE_SNAPSHOT_MAX_BYTES', 5_242_880),
    'evidence_snapshot_allowed_content_types' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TRUTHSHIELD_EVIDENCE_SNAPSHOT_ALLOWED_CONTENT_TYPES', 'image/png,image/jpeg,image/webp,image/gif,text/html,application/pdf,text/plain')),
    ))),

    'trusted_evidence_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TRUTHSHIELD_TRUSTED_EVIDENCE_HOSTS', 'imgur.com,www.imgur.com,i.imgur.com,archive.ph,web.archive.org,youtube.com,www.youtube.com,m.youtube.com,youtu.be')),
    ))),

    'cloud_drive_evidence_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TRUTHSHIELD_CLOUD_DRIVE_EVIDENCE_HOSTS', implode(',', [
            'drive.google.com',
            'docs.google.com',
            'photos.google.com',
            'lh3.googleusercontent.com',
            'www.dropbox.com',
            'dl.dropboxusercontent.com',
            'onedrive.live.com',
            '1drv.ms',
            'sharepoint.com',
            'icloud.com',
            'www.icloud.com',
            'box.com',
            'app.box.com',
        ]))),
    ))),

    'algorithm_version' => env('TRUTHSHIELD_ALGORITHM_VERSION', 'truthshield-v1'),

    'identity_multipliers' => [
        'dev' => 0.8,
        'oauth' => 1.0,
        'verified_social' => 1.15,
        'trusted_reviewer' => 1.3,
        'restricted' => 0.1,
    ],

    'risk_multipliers' => [
        'normal' => 1.0,
        'watched' => 0.5,
        'limited' => 0.1,
        'suspended_weight' => 0.0,
    ],

    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TRUTHSHIELD_ADMIN_EMAILS', 'admin@truthshield.local')),
    ))),

    'news_domains' => [
        '127.0.0.1',
        'localhost',
        'cna.com.tw',
        'www.cna.com.tw',
        'news.pts.org.tw',
        'www.cw.com.tw',
        'www.businessweekly.com.tw',
        'udn.com',
        'www.chinatimes.com',
        'www.ettoday.net',
        'www.setn.com',
        'news.ltn.com.tw',
        'tw.news.yahoo.com',
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'youtu.be',
    ],

    'donation_amounts' => array_values(array_filter(array_map(
        'intval',
        explode(',', env('TRUTHSHIELD_DONATION_AMOUNTS', '100,300,500,1000,2000,5000')),
    ))),
    'donation_monthly_goal' => (int) env('TRUTHSHIELD_DONATION_MONTHLY_GOAL', 15000),
];
