<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('audio_path');
            $table->string('driver')->default('whisper');
            $table->string('status')->default('queued')->index();
            $table->longText('text')->nullable();
            $table->json('segments')->nullable();
            $table->string('language')->nullable();
            $table->double('duration')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcriptions');
    }
};
