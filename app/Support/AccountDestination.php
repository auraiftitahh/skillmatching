<?php

namespace App\Support;

use App\Models\CertificationBody;
use App\Models\User;

class AccountDestination
{
    public static function forUser(User $user): string
    {
        if (! isset(WorkspaceNavigation::PAGES[$user->role])) {
            return route('login');
        }
        if ($user->role === User::ROLE_INSTITUTION && $user->certificationBody?->status !== CertificationBody::STATUS_ACTIVE) {
            return route('institution.status');
        }

        return route('workspace', ['role' => $user->role, 'page' => 'dashboard']);
    }
}
