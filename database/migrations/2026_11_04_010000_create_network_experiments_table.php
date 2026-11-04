<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 15);                  // rtt | throughput | concurrency
            $table->string('target_url');
            $table->json('params');                      // samples, clients, duration
            $table->string('status', 10)->default('queued'); // queued | running | done | failed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('results')->nullable();         // summary numbers
            $table->json('samples')->nullable();         // raw series for the charts
            $table->string('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_experiments');
    }
};
