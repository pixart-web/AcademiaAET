<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_associations', function (Blueprint $table) {
            // Set only once the device completes its one-time activation (device
            // code + PIN); afterwards the browser's cookie carries this token and
            // day-to-day unlocks only ever need the short PIN on top of it.
            $table->string('device_token_hash')->nullable()->after('device_identifier');
            $table->timestamp('activated_at')->nullable()->after('device_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('device_associations', function (Blueprint $table) {
            $table->dropColumn(['device_token_hash', 'activated_at']);
        });
    }
};
