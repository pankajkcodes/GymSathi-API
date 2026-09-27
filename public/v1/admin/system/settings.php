<?php
// GET or POST /v1/admin/system/settings.php
// Manages platform-wide system settings (maintenance mode, mobile app version gates, URLs)
require __DIR__ . '/../../../../app/bootstrap.php';
requireAdmin();

$defaults = [
    'maintenance_mode' => 'false',
    'maintenance_message' => 'GymSathi is currently undergoing scheduled maintenance. Please check back shortly.',
    'android_min_version' => '1.2.0',
    'android_latest_version' => '1.2.4',
    'ios_min_version' => '1.2.0',
    'ios_latest_version' => '1.2.4',
    'support_email' => 'support@gymsathi.in',
    'play_store_url' => 'https://play.google.com/store/apps/details?id=gymsathi.in.app',
    'app_store_url' => 'https://apps.apple.com/app/gymsathi'
];

// Ensure table exists
dbRun("CREATE TABLE IF NOT EXISTS platform_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $rows = dbAll("SELECT setting_key, setting_value FROM platform_settings");
    $settings = $defaults;
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    // Cast maintenance_mode boolean
    $settings['maintenance_mode'] = ($settings['maintenance_mode'] === 'true' || $settings['maintenance_mode'] === '1' || $settings['maintenance_mode'] === true);
    sendSuccess($settings, "Platform settings loaded");
}

if ($method === 'POST') {
    $data = input();
    
    // Mapping of possible client field names to DB keys
    $map = [
        'maintenance_mode' => 'maintenance_mode',
        'maintenanceMode' => 'maintenance_mode',
        'maintenance_message' => 'maintenance_message',
        'maintenanceMsg' => 'maintenance_message',
        'android_min_version' => 'android_min_version',
        'androidMin' => 'android_min_version',
        'android_latest_version' => 'android_latest_version',
        'androidLatest' => 'android_latest_version',
        'ios_min_version' => 'ios_min_version',
        'iosMin' => 'ios_min_version',
        'ios_latest_version' => 'ios_latest_version',
        'iosLatest' => 'ios_latest_version',
        'support_email' => 'support_email',
        'supportEmail' => 'support_email',
        'play_store_url' => 'play_store_url',
        'playStoreUrl' => 'play_store_url',
        'app_store_url' => 'app_store_url',
        'appStoreUrl' => 'app_store_url',
    ];

    foreach ($map as $clientKey => $dbKey) {
        if (isset($data[$clientKey])) {
            $val = $data[$clientKey];
            if (is_bool($val)) {
                $val = $val ? 'true' : 'false';
            }
            dbRun("INSERT INTO platform_settings (setting_key, setting_value) 
                   VALUES (?, ?) 
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", 
                   [$dbKey, (string)$val]
            );
        }
    }

    sendSuccess(null, "Platform settings saved successfully");
}

throw new MethodNotAllowedException();
