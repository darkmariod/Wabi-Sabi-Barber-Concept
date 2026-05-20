<?php

namespace Webkul\User;

class Bouncer
{
    /**
     * Checks if user allowed or not for certain action
     *
     * @param  string  $permission
     * @return void
     */
    public function hasPermission($permission)
    {
        if (! auth()->guard('admin')->check()) {
            return false;
        }

        $admin = auth()->guard('admin')->user();

        if (! $admin->role) {
            return false;
        }

        if ($admin->role->permission_type == 'all') {
            return true;
        }

        return $admin->hasPermission($permission);
    }

    /**
     * Checks if user allowed or not for certain action
     *
     * @param  string  $permission
     * @return void
     */
    public static function allow($permission)
    {
        if (
            ! auth()->guard('admin')->check()
            || ! auth()->guard('admin')->user()->hasPermission($permission)
        ) {
            abort(401, 'This action is unauthorized');
        }
    }
}
