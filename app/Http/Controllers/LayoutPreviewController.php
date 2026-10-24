<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceNavigation;
use Illuminate\Contracts\View\View;

class LayoutPreviewController extends Controller
{
    public function index(): View
    {
        $this->ensureLocalPreview();

        return view('preview.index');
    }

    public function workspace(string $role, string $page = 'dashboard'): View
    {
        $this->ensureLocalPreview();
        abort_unless(isset(WorkspaceNavigation::PAGES[$role][$page]), 404);
        $roleLabel = WorkspaceNavigation::label($role);
        $navigation = WorkspaceNavigation::items($role, $page, true);

        return view('preview.workspace', [
            'roleLabel' => $roleLabel,
            'pageTitle' => WorkspaceNavigation::PAGES[$role][$page][0],
            'navigation' => $navigation,
            'homeUrl' => route('preview.workspace', ['role' => $role, 'page' => 'dashboard']),
        ]);
    }

    public function authentication(string $page): View
    {
        $this->ensureLocalPreview();
        abort_unless(in_array($page, ['login', 'register', 'forgot-password'], true), 404);

        return view('preview.authentication', [
            'mode' => $page,
            'pageTitle' => match ($page) {
                'login' => 'Selamat datang kembali',
                'register' => 'Mulai perjalanan karier Anda',
                'forgot-password' => 'Lupa kata sandi?',
            },
        ]);
    }

    public function components(): View
    {
        $this->ensureLocalPreview();

        return view('preview.components');
    }

    private function ensureLocalPreview(): void
    {
        abort_unless(app()->environment('local', 'testing'), 404);
    }
}
