<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

class MfaService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code) !== false;
    }

    public function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public function decryptSecret(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }
}
