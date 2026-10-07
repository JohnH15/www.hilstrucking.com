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
    // 2. Google reCAPTCHA (v3 Invisible or v2 Checkbox)
    // -------------------------------------------------------------------------
    // Register your domain and get keys here:
    // https://www.google.com/recaptcha/admin
    //
    // Supports both:
    // - 'v3': Invisible background verification (no user checkbox, frictionless)
    // - 'v2': "I'm not a robot" checkbox
    'recaptcha' => [
        // Set to false if you wish to temporarily disable verification (e.g. for offline dev)
        'enabled'    => true,

        // Version: 'v3' (recommended) or 'v2'
        'version'    => 'v3',

        // Public Site Key (passed to the frontend)
        'site_key'   => 'YOUR_RECAPTCHA_SITE_KEY',

        // Secret Key (kept private on server to verify tokens with Google)
        'secret_key' => 'YOUR_RECAPTCHA_SECRET_KEY',

        // Minimum score required for v3 (between 0.0 and 1.0; 0.5 is standard)
        'min_score'  => 0.5,
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
    // 4. Meta WhatsApp Business Cloud API
    // -------------------------------------------------------------------------
    // Official WhatsApp Business Platform / Cloud API (via developers.facebook.com)
    'whatsapp' => [
        // Set to true to send instant lead notifications to WhatsApp
        'enabled'         => false,

        // Phone Number ID from Meta WhatsApp API dashboard
        'phone_number_id' => 'YOUR_PHONE_NUMBER_ID',

        // System User Access Token (with whatsapp_business_messaging permission)
        'access_token'    => 'YOUR_META_ACCESS_TOKEN',

        // Recipient phone number (digits only with country code, e.g. 14454440204)
        'recipient_phone' => '14454440204',

        // Meta Graph API version
        'api_version'     => 'v21.0',

        // Optional: use approved WhatsApp template instead of direct text message
        'use_template'    => false,
        'template_name'   => '',
        'template_lang'   => 'en_US',
    ],

    // -------------------------------------------------------------------------
    // 5. Logging
    // -------------------------------------------------------------------------
    'logging' => [
        'enabled'  => true,
        'log_file' => __DIR__ . '/send-mail.log',
    ],
];
