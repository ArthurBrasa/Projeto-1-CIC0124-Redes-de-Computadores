<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                 // monitor_down | latency | error_rate
            $table->morphs('alertable');                // the Monitor or Application it is about
            $table->string('status', 15)->default('active'); // active | acknowledged | resolved
            $table->string('title');
            $table->json('details')->nullable();        // latest numbers (error, ms, rate...)
            $table->timestamp('started_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['alertable_type', 'alertable_id', 'type', 'status']);
        });

        // Where Slack / webhook notifications are sent.
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->string('slack_webhook_url')->nullable()->after('notify_webhook');
            $table->string('webhook_url')->nullable()->after('slack_webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->dropColumn(['slack_webhook_url', 'webhook_url']);
        });

        Schema::dropIfExists('alerts');
    }
};
