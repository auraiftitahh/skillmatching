<?php

namespace App\Support;

use Illuminate\Http\Request;

final class WorkspacePage
{
    public static function data(Request $r, string $role, string $page, string $title, ?string $back = null): array
    {
        return ['pageTitle' => $title, 'roleLabel' => WorkspaceNavigation::label($role), 'navigation' => WorkspaceNavigation::items($role, $page), 'homeUrl' => route('workspace', ['role' => $role, 'page' => 'dashboard']), 'backUrl' => $back, 'userName' => $r->user()->name];
    }
}
