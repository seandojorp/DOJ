<?php
/**
 * DOJ Region 02 Employees Management System — configuration template.
 *
 * Copy this file to config/config.php and edit the values for your server.
 * config/config.php is never served to the browser (see .htaccess) and must not
 * be committed to version control.
 */
return [
    'app' => [
        'name'      => 'Department of Justice Region 02 Employees Management System',
        'short'     => 'DOJ Region 02 EMS',
        // 'production' hides error details from users. Use 'development' only on a private machine.
        'env'       => 'production',
        'timezone'  => 'Asia/Manila',
        // Leave null to auto-detect (e.g. "/doj-ems" when installed at http://localhost/doj-ems/).
        // Set explicitly if auto-detection is wrong behind a reverse proxy, e.g. '/doj-ems'.
        'base_url'  => null,
        // Secret used to fingerprint sessions. Generate one with:
        //   php -r "echo bin2hex(random_bytes(32));"
        'key'       => 'CHANGE_ME_TO_A_64_CHARACTER_RANDOM_HEX_STRING',
    ],

    'db' => [
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'name'      => 'doj-employee',
        // Use a least-privilege account (see INSTALL.md §3). XAMPP's root account works but is not recommended.
        'user'      => 'doj_ems_app',
        'pass'      => '',
        'charset'   => 'utf8mb4',
        // Session time zone for database-managed timestamps (Created_At, Updated_At, Last_Login).
        'time_zone' => '+08:00',
    ],

    'session' => [
        'name'             => 'DOJEMS_SID',
        'idle_timeout'     => 1800,   // seconds of inactivity before automatic logout (30 min)
        'absolute_timeout' => 28800,  // maximum session length regardless of activity (8 h)
        // 'auto' sets the Secure cookie flag whenever the request arrives over HTTPS.
        // Set true to require HTTPS (recommended in production), false only for plain-HTTP intranet testing.
        'secure'           => 'auto',
    ],

    'security' => [
        'login_window_minutes'   => 15, // sliding window for failed-login counting
        'login_max_per_username' => 5,  // failed attempts per username within the window
        'login_max_per_ip'       => 20, // failed attempts per client IP within the window
        'register_max_per_ip'    => 10, // failed registration attempts per IP within the window
        // Only set true when the app sits behind a trusted reverse proxy that sets X-Forwarded-For.
        'trust_proxy'            => false,
    ],

    'upload' => [
        'photo_max_upload_bytes' => 5 * 1024 * 1024, // largest file a user may upload (before processing)
        'photo_max_dimension'    => 600,             // photos are resized to fit within this box (px)
        // Upper bound for the stored image. XAMPP ships with max_allowed_packet = 1M; keep this below it.
        'photo_max_store_bytes'  => 900 * 1024,
    ],

    /*
     * learning_development.Type and learning_development.Conducted_Sponsored are INT columns in the
     * authoritative schema, so the application stores a numeric code and shows the label below.
     * Edit the labels to match your office's coding standard. Do not renumber codes that are already in use.
     * (Optional migration 002 converts these columns to text if your office prefers free text.)
     */
    'lookups' => [
        'ld_type' => [
            1 => 'Managerial',
            2 => 'Supervisory',
            3 => 'Technical',
            4 => 'Foundation',
            5 => 'Others',
        ],
        'ld_conducted' => [
            1 => 'Department of Justice (DOJ)',
            2 => 'National Prosecution Service (NPS)',
            3 => 'Civil Service Commission (CSC)',
            4 => 'Other Government Agency',
            5 => 'Private / Non-Government Organization',
            6 => 'International / Foreign Organization',
            9 => 'Others',
        ],
    ],
];
