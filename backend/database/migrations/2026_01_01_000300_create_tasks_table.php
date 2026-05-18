<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('assignee_name')->nullable();
            $table->string('assignee_email')->nullable()->index();
            $table->foreignId('matched_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending_approval')->index();
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
