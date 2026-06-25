<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedBigInteger('shop_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('reason')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'start_date', 'end_date']);
        });

        // Migrate existing MAINTENANCE bookings into item_blocks
        $maintenanceBookings = DB::table('bookings')
            ->where('booking_type', 'MAINTENANCE')
            ->get();

        foreach ($maintenanceBookings as $booking) {
            $quantity = DB::table('booking_items')
                ->where('booking_id', $booking->id)
                ->count();

            $itemId = DB::table('booking_items')
                ->where('booking_id', $booking->id)
                ->value('item_id');

            if ($itemId && $quantity > 0) {
                DB::table('item_blocks')->insert([
                    'item_id'    => $itemId,
                    'branch_id'  => $booking->branch_id,
                    'shop_id'    => $booking->shop_id,
                    'start_date' => $booking->booking_date,
                    'end_date'   => $booking->return_date ?? $booking->booking_date,
                    'quantity'   => $quantity,
                    'reason'     => null,
                    'created_by' => null,
                    'created_at' => $booking->created_at,
                    'updated_at' => $booking->updated_at,
                ]);
            }

            DB::table('booking_items')->where('booking_id', $booking->id)->delete();
            DB::table('bookings')->where('id', $booking->id)->delete();
        }
    }

    public function down(): void
    {
        // Restore item_blocks back to MAINTENANCE bookings is not practical — just drop the table
        Schema::dropIfExists('item_blocks');
    }
};
