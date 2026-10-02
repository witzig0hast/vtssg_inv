<?php

return [
    // Zwei-Faktor-Authentifizierung für alle Konten verpflichtend
    'require_2fa' => (bool) env('INV_REQUIRE_2FA', false),

    // Anmeldeschutz
    'max_login_attempts' => (int) env('INV_MAX_LOGIN_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('INV_LOCKOUT_MINUTES', 15),

    // Passwort-Mindestlänge
    'password_min' => (int) env('INV_PASSWORD_MIN', 12),

    // Upload-Limits (KB) und erlaubte Typen
    'upload_max_kb' => (int) env('INV_UPLOAD_MAX_KB', 10240),
    'upload_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'csv', 'xlsx'],
];
