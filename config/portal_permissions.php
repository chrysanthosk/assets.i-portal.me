<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Registry
    |--------------------------------------------------------------------------
    | Add new permissions here whenever you add a new module/page.
    | These are automatically synced into the database by the seeder/command.
    |
    | Key = permission name saved in DB
    | label = display name in UI
    | group = section in permission-sets UI
    | default_roles = roles that should get it automatically (e.g. Admin)
    */

    'permissions' => [

        // Dashboard
        'view_dashboard' => [
            'label' => 'View Dashboard',
            'group' => 'Dashboard',
            'default_roles' => ['Admin', 'User'],
        ],

        // Settings
        'manage_portal_settings' => [
            'label' => 'Manage Portal Settings',
            'group' => 'Settings',
            'default_roles' => ['Admin'],
        ],

        'manage_users' => [
            'label' => 'Manage Users',
            'group' => 'Settings',
            'default_roles' => ['Admin'],
        ],

        'manage_permission_sets' => [
            'label' => 'Manage Permission Sets',
            'group' => 'Settings',
            'default_roles' => ['Admin'],
        ],

        'manage_smtp_settings' => [
            'label' => 'Manage SMTP Settings',
            'group' => 'Settings',
            'default_roles' => ['Admin'],
        ],
    ],
];
