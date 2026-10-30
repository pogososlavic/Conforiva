<?php

namespace App\Console\Commands;

use App\Services\Admin\Messages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

final class MailDiagnostics extends Command
{
    protected $signature = 'homya:mail-diagnostics {--connect : Test TLS and SMTP handshake, including AUTH when offered, without sending a message}';

    protected $description = 'Secret-free mail checks; no database access and no email submission';

    public function handle(): int
    {
        $checks = [
            'smtp_selected' => config('mail.default') === 'smtp',
            'support_smtp_configuration_ready' => app(Messages::class)->emailReady(),
            'smtp_url_override_present' => filled(config('mail.mailers.smtp.url')),
        ];
        $result = ['checks' => $checks, 'connection' => 'not_tested', 'email_sent' => false];
        $transport = null;
        try {
            if (! $checks['smtp_selected'] || ! $checks['support_smtp_configuration_ready']) {
                $result['connection'] = 'configuration_incomplete';

                return $this->report($result, self::FAILURE);
            }
            $transport = Mail::mailer('smtp')->getSymfonyTransport();
            if (! $transport instanceof EsmtpTransport) {
                $result['connection'] = 'unsupported_transport';

                return $this->report($result, self::FAILURE);
            }
            // Inspect the resolved transport: MAIL_URL can override separate env values.
            $result['checks']['smtp_credentials_present'] = $transport->getUsername() !== '' && $transport->getPassword() !== '';
            if (! $result['checks']['smtp_credentials_present']) {
                $result['connection'] = 'credentials_missing';

                return $this->report($result, self::FAILURE);
            }
            if ($this->option('connect')) {
                $transport->getStream()->setTimeout(10);
                $transport->setRequireTls(true);
                // start() performs greeting, TLS and AUTH only. No MAIL FROM / RCPT / DATA.
                $transport->start();
                $result['connection'] = 'smtp_handshake_passed';
                $result['authentication'] = 'checked_if_advertised_by_server';
            }

            return $this->report($result, self::SUCCESS);
        } catch (Throwable $error) {
            // Never print provider replies/debug: they may contain credentials or customer data.
            $message = strtolower($error->getMessage());
            $result['connection'] = match (true) {
                (bool) preg_match('/\b(?:535|534)\b|authenticat|credentials|password/', $message) => 'authentication_failed',
                (bool) preg_match('/certificate|ssl|tls|crypto/', $message) => 'tls_failed',
                (bool) preg_match('/timed? ?out|timeout/', $message) => 'connection_timeout',
                (bool) preg_match('/resolve|network|connection|connect|refused|socket/', $message) => 'connection_failed',
                default => 'smtp_check_failed',
            };

            return $this->report($result, self::FAILURE);
        } finally {
            if ($transport instanceof EsmtpTransport && $this->option('connect')) {
                try {
                    $transport->stop();
                } catch (Throwable) {
                    // A QUIT error cannot turn this no-message test into an email submission.
                }
            }
        }
    }

    private function report(array $result, int $status): int
    {
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $status;
    }
}
