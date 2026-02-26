<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Name
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'name'   => env('APP_NAME', 'laravel-backup'),
        'source' => [
            'files' => [
                'include'                  => [base_path()],
                'exclude'                  => [
                    base_path('vendor'),
                    base_path('node_modules'),
                ],
                'follow_links'             => false,
                'ignore_unreadable_dirs'   => false,
                'relative_path'            => null,
            ],
            'databases' => ['sqlite'],
        ],
        'database_dump_compressor' => null,
        'database_dump_file_extension' => '',
    ],

    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailed::class         => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFound::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailed::class        => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessful::class     => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFound::class   => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessful::class    => [],
        ],
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,
        'mail' => [
            'to' => 'admin@example.com',
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name'    => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],
        'slack' => [
            'webhook_url' => '',
            'channel'     => null,
            'username'    => null,
            'icon'        => null,
        ],
        'discord' => [
            'webhook_url' => '',
            'username'    => '',
            'avatar_url'  => '',
        ],
    ],

    'monitor_backups' => [
        [
            'name'                 => env('APP_NAME', 'laravel-backup'),
            'disks'                => ['local'],
            'health_checks'        => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class  => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
            'destination' => [
                'disks' => [
                    env('BACKUP_DISK', 'local'),
                ],
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days'               => 7,
            'keep_daily_backups_for_days'              => 16,
            'keep_weekly_backups_for_weeks'            => 8,
            'keep_monthly_backups_for_months'          => 4,
            'keep_yearly_backups_for_years'            => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];
