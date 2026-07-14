<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('path')->index();
            $table->string('title')->nullable();
            $table->string('audience')->default('staff')->index();
            $table->string('module')->nullable()->index();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->string('content_hash', 64)->index();
            $table->timestamps();

            $table->unique(['path', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_chunks');
    }
};
