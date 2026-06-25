<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan_name')->default('Basic');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('max_branches')->default(1);
            $table->boolean('is_trial')->default(false);
            $table->boolean('is_suspended')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
