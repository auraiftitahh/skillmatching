<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Mail\AccountActionMail;
use Illuminate\Support\Facades\URL;
class AppServiceProvider extends ServiceProvider {
    public function register(): void {}
    public function boot(): void {
        // With a trusted X-Forwarded-Proto set by ngrok, request()->isSecure() is true.
        // Avoid forcing HTTPS on localhost when the ngrok tunnel uses HTTPS externally.
        if (app()->environment('production') && str_starts_with((string)config('app.url'), 'https://')) URL::forceScheme('https');
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new AccountActionMail($notifiable->name, $url, 'reset', $minutes))
                ->to($notifiable->getEmailForPasswordReset());
        });
    }
}
