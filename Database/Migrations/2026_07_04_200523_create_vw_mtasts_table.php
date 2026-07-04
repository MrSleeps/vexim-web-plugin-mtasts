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
        Schema::create('vw_mta_sts', function (Blueprint $table) {
            $table->id(); // Auto-incrementing id column
            $table->unsignedInteger('domain_id'); // Linked to domains.domain_id
            $table->string('policy_type'); // Policy type (e.g., "none", "testing", "enforce")
            $table->integer('max_age'); // Max age in seconds
            $table->string('generated_id'); // Generated ID (you may want to change type based on your needs)
            $table->timestamps(); // created_at and updated_at columns
            
            // Foreign key constraint
            $table->foreign('domain_id')
                  ->references('domain_id')
                  ->on('domains')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vw_mta_sts');
    }
};