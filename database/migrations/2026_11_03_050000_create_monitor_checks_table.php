<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active'); // shown on the public status page
            $table->timestamp('last_checked_at')->nullable()->after('is_public');
        });

        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_up');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('checked_at')->useCurrent();

            $table->index(['monitor_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_checks');

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'last_checked_at']);
        });
    }
};
