<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Require 2FA for privileged users
    |--------------------------------------------------------------------------
    |
    | When enabled, users with the admin role (or the manage_users permission)
    | who have not set up two-factor authentication are redirected to their
    | profile to enable it before they can use the rest of the app.
    |
    */

    'require_2fa_for_admins' => env('REQUIRE_2FA_FOR_ADMINS', false),

    'admin_role' => env('PORTAL_ADMIN_ROLE', 'Admin'),

];
