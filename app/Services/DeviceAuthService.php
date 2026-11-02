<?php

namespace App\Services;

use App\Models\DeviceAssociation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One-time device+PIN activation and short-PIN unlock, shared by the web
 * portal (Http\Controllers\Auth\ChildSessionController, cookie-delivered)
 * and the API (Api\ChildDeviceController, token delivered in the JSON body
 * for the future mobile apps to store themselves — e.g. Keychain/Keystore).
 */
class DeviceAuthService
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    /**
     * One-time use: a second call with the same device code + PIN, even a
     * genuinely concurrent one, must never both succeed. The check
     * (DeviceAssociation::isActivationUsable(), which is false once
     * activated_at is set) and the consumption (activateWithToken()) run
     * inside one locked transaction, so two simultaneous requests are
     * serialized on the row lock — the second always sees the first's
     * already-committed activation and is rejected, same generic message
     * as any other invalid code/PIN (never reveals *why* it failed).
     *
     * @return array{0: DeviceAssociation, 1: string} the device and its new device token
     */
    public function activate(string $deviceCode, string $pin): array
    {
        return DB::transaction(function () use ($deviceCode, $pin) {
            $device = DeviceAssociation::query()
                ->where('device_identifier', Str::upper($deviceCode))
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $device) {
                throw ValidationException::withMessages(['pin' => 'Código ou PIN inválidos.']);
            }

            if ($device->locked_until && $device->locked_until->isFuture()) {
                throw ValidationException::withMessages(['pin' => 'Código ou PIN inválidos.']);
            }

            if (! $device->isActivationUsable() || ! $device->checkPin($pin)) {
                $this->registerFailedAttempt($device);

                throw ValidationException::withMessages(['pin' => 'Código ou PIN inválidos.']);
            }

            $token = Str::random(48);
            $device->activateWithToken($token);
            $device->session_expires_at = now()->addDays((int) config('device.session_days', 365));
            $device->last_used_at = now();
            $device->failed_attempts = 0;
            $device->save();

            return [$device, $token];
        });
    }

    public function unlock(DeviceAssociation $device, string $deviceToken, string $pin): DeviceAssociation
    {
        if (! $device->isActivated() || ! $device->checkDeviceToken($deviceToken)) {
            throw ValidationException::withMessages(['pin' => 'Dispositivo não reconhecido.']);
        }

        if ($device->locked_until && $device->locked_until->isFuture()) {
            throw ValidationException::withMessages(['pin' => 'Demasiadas tentativas. Tente novamente mais tarde.']);
        }

        if (! $device->isSessionUsable() || ! $device->checkPin($pin)) {
            $this->registerFailedAttempt($device);

            throw ValidationException::withMessages(['pin' => 'PIN incorreto.']);
        }

        $device->failed_attempts = 0;
        $device->last_used_at = now();
        $device->save();

        return $device;
    }

    private function registerFailedAttempt(DeviceAssociation $device): void
    {
        $device->failed_attempts++;
        if ($device->failed_attempts >= self::MAX_ATTEMPTS) {
            $device->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
        }
        $device->save();
    }
}
