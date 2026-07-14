<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_type')->index();
            $table->string('source_id')->index();
            $table->string('label')->nullable();
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->string('content_hash', 64)->index();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_embeddings');
    }
};
