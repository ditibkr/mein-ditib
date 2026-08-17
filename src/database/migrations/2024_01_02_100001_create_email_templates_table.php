<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 150);
            $table->string('subject_de', 255);
            $table->string('subject_tr', 255)->nullable();
            $table->longText('body_de');
            $table->longText('body_tr')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('subject_de', 255);
            $table->string('subject_tr', 255)->nullable();
            $table->longText('body_de');
            $table->longText('body_tr')->nullable();
            $table->string('status', 20)->default('entwurf');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('newsletter_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('member_name', 200)->nullable();
            $table->string('language', 5)->default('de');
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['newsletter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_sends');
        Schema::dropIfExists('newsletters');
        Schema::dropIfExists('email_templates');
    }
};
