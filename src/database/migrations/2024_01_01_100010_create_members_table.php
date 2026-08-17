<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_number', 20)->unique()->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->unique()->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('gender', 10)->nullable();
            $table->text('street')->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 50)->default('Deutschland');
            $table->date('membership_start')->nullable();
            $table->date('membership_end')->nullable();
            $table->string('status', 20)->default('aktiv');
            $table->string('category', 30)->default('vollmitglied');
            $table->decimal('membership_fee', 8, 2)->default(0);
            $table->string('fee_interval', 20)->default('monatlich');
            $table->string('language_preference', 5)->default('de');
            $table->boolean('sepa_active')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('gdpr_consent')->default(false);
            $table->timestamp('gdpr_consent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index(['last_name', 'first_name']);
            $table->index('zip_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
