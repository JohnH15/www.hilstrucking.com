<?php
/**
 * HILS Trucking LLC — Configuration File Template
 *
 * Copy this file to `config.php` and set your actual values.
 * Note: `config.php` is ignored by Git to keep your credentials and email safe.
 */

return [
    // -------------------------------------------------------------------------
    // 1. Email Recipients & Sender
    // -------------------------------------------------------------------------

    // The destination email address where submitted driver applications are sent:
    'to_email' => 'info@hilstrucking.com',

    // Optional additional recipient (CC), comma-separated, or leave empty:
    'cc_email' => '',

    // Sender address (From) for outgoing notifications:
    // Recommendation: Use an address on your domain (e.g. no-reply@hilstrucking.com)
    'from_email' => 'no-reply@hilstrucking.com',

    // Sender display name:
    'from_name' => 'HILS Trucking Applications',

    // Subject prefix for application notification emails:
    'subject_prefix' => '[HILS Trucking Application]',

    // -------------------------------------------------------------------------
    // 2. Google reCAPTCHA v2 (Checkbox "I'm not a robot")
    // -------------------------------------------------------------------------
    // Register your domain and get keys here:
    // https://www.google.com/recaptcha/admin
    //
    // The default keys below are official Google test keys:
    // - Always pass verification
    // - Show a "Test mode" badge
    // - Perfect for local testing on localhost / .local domains
    'recaptcha' => [
        // Set to false if you wish to temporarily disable verification (e.g. for offline dev)
        'enabled'    => true,

        // Public Site Key (passed to the frontend widget)
        'site_key'   => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',

        // Secret Key (kept private on server to verify tokens with Google)
        'secret_key' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
    ],

    // -------------------------------------------------------------------------
    // 3. Optional SMTP Configuration
    // -------------------------------------------------------------------------
    // Set 'enabled' => true if you prefer direct SMTP instead of standard PHP mail()
    'smtp' => [
        'enabled'    => false,
        'host'       => 'smtp.example.com',
        'port'       => 587,             // 587 for TLS, 465 for SSL, 25 for unencrypted
        'secure'     => 'tls',           // 'tls' or 'ssl'
        'auth'       => true,
        'username'   => 'recruiting@example.com',
        'password'   => 'your-smtp-password',
    ],

    // -------------------------------------------------------------------------
    // 4. Logging
    // -------------------------------------------------------------------------
    'logging' => [
        'enabled'  => true,
        'log_file' => __DIR__ . '/send-mail.log',
    ],
];
