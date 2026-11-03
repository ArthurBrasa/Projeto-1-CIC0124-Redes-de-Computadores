<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('public_id')->unique();          // app_xxxxxxxxxx, used in URLs and webhook
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('environment')->default('production'); // production | staging | development
            $table->string('url');
            $table->string('token_hash', 64)->nullable()->unique(); // sha256 of the API token
            $table->string('token_prefix')->nullable();             // first chars, shown masked in the UI
            $table->timestamp('token_rotated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
