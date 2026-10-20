<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class AdminAuthenticationAudit
{
    public function record(Request $request, string $outcome, string $username): void
    {
        try {
            $key = (string) config('app.key');
            Log::channel('security')->notice('ADMIN_AUTHENTICATION', [
                'outcome' => $outcome,
                'subject' => hash_hmac('sha256', $username, $key),
                // Use the actual peer, never a user-supplied forwarded header.
                'source' => hash_hmac('sha256', (string) $request->server('REMOTE_ADDR'), $key),
                'reference' => $request->attributes->get('homya.error_reference'),
            ]);
        } catch (\Throwable) {
            // A log failure must not expose credentials or prevent session revocation.
        }
    }
}
