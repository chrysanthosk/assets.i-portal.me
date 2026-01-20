<?php

return [
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

        // Assets
        'manage_assets' => [
            'label' => 'Manage Assets',
            'group' => 'Assets',
            'default_roles' => ['Admin'],
        ],

        'manage_asset_tags' => [
            'label' => 'Manage Asset Tags',
            'group' => 'Assets',
            'default_roles' => ['Admin'],
        ],

        'manage_asset_rentals' => [
            'label' => 'Manage Rental Income',
            'group' => 'Assets',
            'default_roles' => ['Admin'],
        ],

        // New: Asset Config
        'manage_asset_types' => [
            'label' => 'Manage Asset Types',
            'group' => 'Settings',
            'default_roles' => ['Admin'],
        ],

        'manage_owner_entities' => [
            'label' => 'Manage Owner Entities',
            'group' => 'Settings',
            'default_roles' => ['Admin'],
        ],
    ],
];
