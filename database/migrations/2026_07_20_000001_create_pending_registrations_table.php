<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();

            // Business intake
            $table->string('shop_name');
            $table->string('item_label')->nullable();       // "Dress" / "Car" / "Equipment"
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number');                  // E.164 normalized
            $table->string('country_code', 8)->default('+251');
            $table->string('email')->nullable();
            $table->string('city')->nullable();

            // Lifecycle
            // UNCONFIRMED = email link not clicked yet (only for rows with an email)
            // PENDING     = ready for super admin review (either email-confirmed or submitted without email)
            // APPROVED    = super admin approved; real Shop/User/Subscription have been created
            // REJECTED    = super admin rejected with a reason
            $table->string('status', 20)->default('PENDING')->index();

            // Email confirmation
            $table->string('confirm_token', 64)->nullable()->unique();
            $table->timestamp('confirmed_at')->nullable();

            // Approval / rejection
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 40)->nullable();   // e.g. SPAM, INSUFFICIENT_INFO, DUPLICATE, OUT_OF_REGION, OTHER
            $table->text('rejection_note')->nullable();

            // If approved, link to the created shop so we can jump straight to it from the queue
            $table->foreignId('created_shop_id')->nullable()->constrained('shops')->nullOnDelete();

            // Preferred language for follow-up emails (welcome / rejection)
            $table->string('email_language', 2)->default('en');

            // Bookkeeping
            $table->string('source_ip', 45)->nullable();
            $table->timestamps();

            $table->index('phone_number');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
