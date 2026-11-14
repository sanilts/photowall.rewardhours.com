<?php
// Photowall settings. The installer writes the real file to /etc/photowall/config.php (never into git).
return [
    'db' => ['host' => 'localhost', 'name' => 'photowall', 'user' => 'pw_app', 'pass' => 'CHANGE_ME'],

    // Which address opens which part of the app.
    'hosts' => [
        'respondent' => 'photowall.rewardhours.com',
        'admin'      => 'console.photowall.rewardhours.com',
        'client'     => 'client.photowall.researchandmetric.com',
    ],
    'https' => true,

    // Photos live here, outside the web folder. Originals are never changed.
    'storage' => '/var/lib/photowall',

    // Local tools. Nothing is sent to an outside service.
    'python'   => '/opt/photowall-venv/bin/python',
    'exiftool' => '/usr/bin/exiftool',
    'models'   => '/var/lib/photowall/models',

    // Email for one-time codes. driver: log (writes to storage/logs/mail.log), smtp, or postmark.
    'mail' => [
        'driver'     => 'log',
        'from_email' => 'no-reply@example.com',
        'from_name'  => 'Photowall',
        'smtp'       => ['host' => '', 'port' => 587, 'user' => '', 'pass' => '', 'secure' => 'tls'],
        'postmark_token' => '',
    ],

    'timezone'      => 'Asia/Kolkata',
    'secret'        => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
    'max_upload_mb' => 60,
    'admin_otp'     => true,

    'company'       => 'Research and Metric Corporation',
    'privacy_email' => 'privacy@researchandmetric.com',
];
