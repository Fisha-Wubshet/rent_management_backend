<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public function generateInvoice(Booking $booking, bool $withAck = false): \Barryvdh\DomPDF\PDF
    {
        $booking->load('items.item', 'branch.shop', 'customer');
        return Pdf::loadView('pdf.invoice', compact('booking', 'withAck'));
    }
}
