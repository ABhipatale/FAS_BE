<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardIncompleteSmtpConfig();
        $this->brandPasswordResetEmail();
    }

    /**
     * SMTP is selected but no credentials have been filled in yet -> fall back
     * to the log driver. Otherwise every password reset would fail with an
     * authentication error; this way the link still lands in
     * storage/logs/laravel.log until MAIL_USERNAME / MAIL_PASSWORD are set.
     */
    private function guardIncompleteSmtpConfig(): void
    {
        if (Config::get('mail.default') !== 'smtp') {
            return;
        }

        $username = Config::get('mail.mailers.smtp.username');
        $password = Config::get('mail.mailers.smtp.password');

        if (filled($username) && filled($password)) {
            return;
        }

        Config::set('mail.default', 'log');

        Log::warning(
            'MAIL_MAILER=smtp but MAIL_USERNAME/MAIL_PASSWORD are empty - '
            . 'falling back to the log mailer. Fill both in .env to send real email.'
        );
    }

    /**
     * The reset email ships with Laravel's own wording and logo. Rewrite it so
     * it reads as this product, and point the button at the SPA rather than a
     * Blade route this API does not have.
     */
    private function brandPasswordResetEmail(): void
    {
        // FRONTEND_URL may hold a comma-separated list (it is shared with
        // config/cors.php), so the first entry is the one we link to.
        $resetUrl = function ($notifiable, string $token): string {
            $origins = array_filter(array_map(
                'trim',
                explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))
            ));

            $base = rtrim($origins[0] ?? 'http://localhost:5173', '/');

            return $base . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        };

        ResetPassword::createUrlUsing($resetUrl);

        ResetPassword::toMailUsing(function ($notifiable, string $token) use ($resetUrl) {
            $appName = Config::get('app.name');
            $minutes = Config::get('auth.passwords.users.expire', 60);

            return (new MailMessage)
                ->subject("Reset your {$appName} password")
                ->greeting('Password reset requested')
                ->line("We received a request to reset the password for your {$appName} account.")
                ->action('Choose a new password', $resetUrl($notifiable, $token))
                ->line("This link expires in {$minutes} minutes and can only be used once.")
                ->line('If you did not request this, you can safely ignore this email — your password stays as it is.')
                ->salutation("— The {$appName} team");
        });
    }
}
