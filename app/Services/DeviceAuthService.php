<?php

namespace App\Services;

use App\Models\DeviceAssociation;
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
     * @return array{0: DeviceAssociation, 1: string} the device and its new device token
     */
    public function activate(string $deviceCode, string $pin): array
    {
        $device = DeviceAssociation::query()
            ->where('device_identifier', Str::upper($deviceCode))
            ->where('status', 'active')
            ->first();

        if (! $device || ! $device->isUsable() || ! $device->checkPin($pin)) {
            throw ValidationException::withMessages(['pin' => 'Código ou PIN inválidos.']);
        }

        $token = Str::random(48);
        $device->activateWithToken($token);
        $device->last_used_at = now();
        $device->failed_attempts = 0;
        $device->save();

        return [$device, $token];
    }

    public function unlock(DeviceAssociation $device, string $deviceToken, string $pin): DeviceAssociation
    {
        if (! $device->isActivated() || ! $device->checkDeviceToken($deviceToken)) {
            throw ValidationException::withMessages(['pin' => 'Dispositivo não reconhecido.']);
        }

        if ($device->locked_until && $device->locked_until->isFuture()) {
            throw ValidationException::withMessages(['pin' => 'Demasiadas tentativas. Tente novamente mais tarde.']);
        }

        if (! $device->isUsable() || ! $device->checkPin($pin)) {
            $device->failed_attempts++;
            if ($device->failed_attempts >= self::MAX_ATTEMPTS) {
                $device->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
            }
            $device->save();

            throw ValidationException::withMessages(['pin' => 'PIN incorreto.']);
        }

        $device->failed_attempts = 0;
        $device->last_used_at = now();
        $device->save();

        return $device;
    }
}
