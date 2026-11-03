<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('method', 10)->default('GET');                 // GET | POST
            $table->unsignedInteger('interval_seconds')->default(60);     // how often to check
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->unsignedSmallInteger('expected_status')->default(200);
            $table->boolean('is_active')->default(true);                  // paused when false
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
