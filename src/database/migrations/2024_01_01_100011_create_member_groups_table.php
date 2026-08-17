<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_tr', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6B7280');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('member_group_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_group_id')->constrained()->cascadeOnDelete();
            $table->date('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'member_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_group_pivot');
        Schema::dropIfExists('member_groups');
    }
};
