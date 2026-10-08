<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AET-RC01 finding 3: `expires_at` was used for both the one-time
 * activation code's deadline AND, via isUsable(), the ongoing device
 * association's validity — so an already-activated device that a child
 * used daily would stop working exactly 7 days after pairing (the
 * activation code's own short window), and there was no separate clock
 * for "how long can this paired device keep being used". This splits
 * them: `expires_at` stays the activation code's deadline (only checked
 * before activation), `session_expires_at` is set once activation
 * completes and governs the ongoing device — see
 * DeviceAssociation::isActivationUsable() / isSessionUsable().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_associations', function (Blueprint $table) {
            $table->timestamp('session_expires_at')->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('device_associations', function (Blueprint $table) {
            $table->dropColumn('session_expires_at');
        });
    }
};
