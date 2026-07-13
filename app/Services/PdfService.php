<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    public function generateInvoice(Booking $booking, bool $withAck = false): string
    {
        $stamp = $booking->updated_at?->getTimestamp() ?? 0;
        $path  = sprintf('invoices/%d-%d-ack%d.pdf', $booking->id, $stamp, $withAck ? 1 : 0);

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->get($path);
        }

        $booking->loadMissing('items.item', 'branch.shop', 'customer');
        $blob = Pdf::loadView('pdf.invoice', compact('booking', 'withAck'))->output();

        Storage::disk('local')->put($path, $blob);
        return $blob;
    }
}
