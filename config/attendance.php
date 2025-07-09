<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attendance Machine Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Solution X304 attendance machine connection
    |
    */

    'machine' => [
        'default_ip' => env('ATTENDANCE_MACHINE_IP', '10.10.10.85'),
        'default_port' => env('ATTENDANCE_MACHINE_PORT', 80),
        'comm_key' => env('ATTENDANCE_MACHINE_COMM_KEY', '0'),
        'timeout' => env('ATTENDANCE_MACHINE_TIMEOUT', 10),
        'full_sync_timeout' => env('ATTENDANCE_MACHINE_FULL_TIMEOUT', 60),
        'max_execution_time' => env('ATTENDANCE_MAX_EXECUTION_TIME', 240),
        'memory_limit' => env('ATTENDANCE_MEMORY_LIMIT', '512M'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Work Schedule Configuration
    |--------------------------------------------------------------------------
    |
    | Define standard work hours and break times
    |
    */

    'schedule' => [
        'work_start_time' => env('ATTENDANCE_WORK_START_TIME', '07:30:00'),
        'work_end_time' => env('ATTENDANCE_WORK_END_TIME', '16:30:00'),
        'lunch_break_duration' => env('ATTENDANCE_LUNCH_BREAK_DURATION', 60), // minutes
        'late_tolerance_minutes' => env('ATTENDANCE_LATE_TOLERANCE_MINUTES', 0),
        'overtime_start_time' => env('ATTENDANCE_OVERTIME_START_TIME', '16:30:00'),
        'min_work_hours' => env('ATTENDANCE_MIN_WORK_HOURS', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automatic data synchronization
    |
    */

    'sync' => [
        'auto_sync_enabled' => env('ATTENDANCE_AUTO_SYNC_ENABLED', true),
        'sync_interval_minutes' => env('ATTENDANCE_SYNC_INTERVAL_MINUTES', 15),
        'process_interval_minutes' => env('ATTENDANCE_PROCESS_INTERVAL_MINUTES', 60),
        'daily_summary_time' => env('ATTENDANCE_DAILY_SUMMARY_TIME', '06:00'),
        'duplicate_detection_minutes' => env('ATTENDANCE_DUPLICATE_DETECTION_MINUTES', 1),
        'max_daily_taps' => env('ATTENDANCE_MAX_DAILY_TAPS', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for attendance system logging
    |
    */

    'logging' => [
        'debug_mode' => env('ATTENDANCE_DEBUG_MODE', false),
        'log_level' => env('ATTENDANCE_LOG_LEVEL', 'info'),
        'keep_logs_days' => env('ATTENDANCE_KEEP_LOGS_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for attendance notifications
    |
    */

    'notifications' => [
        'enabled' => env('ATTENDANCE_NOTIFICATIONS_ENABLED', false),
        'email' => env('ATTENDANCE_NOTIFICATION_EMAIL', 'admin@company.com'),
        'slack_webhook_url' => env('ATTENDANCE_SLACK_WEBHOOK_URL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Labels
    |--------------------------------------------------------------------------
    |
    | Human readable labels for attendance status
    |
    */

    'status_labels' => [
        'present_ontime' => 'Hadir Tepat Waktu',
        'present_late' => 'Hadir Terlambat', 
        'absent' => 'Tidak Hadir',
        'on_leave' => 'Cuti',
        'sick_leave' => 'Sakit',
        'permission' => 'Izin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Methods
    |--------------------------------------------------------------------------
    |
    | Mapping for attendance machine verification codes
    |
    */

    'verification_methods' => [
        1 => 'password',
        4 => 'card',
        15 => 'fingerprint',
        11 => 'face',
    ],

]; 