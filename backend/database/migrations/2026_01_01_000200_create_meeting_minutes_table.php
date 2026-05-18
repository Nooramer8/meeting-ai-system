<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->longText('summary')->nullable();
            $table->jsonb('decisions')->nullable();
            $table->jsonb('risks')->nullable();
            $table->jsonb('raw_ai_output')->nullable();
            $table->timestamps();

            $table->unique('meeting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes');
    }
};
