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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('business_name')->nullable();
            $table->string('business_address')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('state_province_region')->nullable();
            $table->string('postal_zip_code')->nullable();
            $table->string('website')->nullable();
            $table->enum('prefer_company', ['Company', 'Personal'])->nullable();
            $table->enum('preferred_correspondence_email',['Company', 'Personal'])->nullable();
            $table->string('preferred_contact_method')->nullable();
            $table->text('applications_used')->nullable();
            $table->string('maximum_budget')->nullable();
            $table->boolean('agree_terms')->default(false);
            $table->boolean('agree_update_terms')->default(false);
            $table->string('signature')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
