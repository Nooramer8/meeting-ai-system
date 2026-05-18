<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('original_filename')->nullable();
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->string('status')->default('uploaded')->index();
            $table->string('language')->nullable();
            $table->longText('transcript')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
