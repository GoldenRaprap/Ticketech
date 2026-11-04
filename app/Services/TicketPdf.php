<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Str;

class TicketPdf
{
    public function generate(Ticket $ticket): string
    {
        $lines = [
            'Ticket number: '.$ticket->ticket_number,
            'Status: '.$ticket->status,
            'Priority: '.$ticket->priority,
            'Submitted: '.$ticket->created_at->copy()
                ->timezone(config('app.display_timezone'))
                ->format('F j, Y g:i A'),
            'Requester: '.$ticket->requester_name,
            'Email: '.$ticket->requester_email,
            'Department: '.($ticket->department?->name ?? 'Not available'),
            'Category: '.($ticket->category?->name ?? 'Not selected'),
            'Subject: '.Str::ascii($ticket->subject),
            '',
            'Description:',
            $ticket->description,
            '',
            'Check ticket status: '.route('ticket.check'),
        ];
        $wrappedLines = [];

        foreach ($lines as $line) {
            foreach (preg_split('/\R/', Str::ascii($line)) ?: [''] as $paragraph) {
                if ($paragraph === '') {
                    $wrappedLines[] = '';

                    continue;
                }

                array_push($wrappedLines, ...explode("\n", wordwrap($paragraph, 82, "\n", true)));
            }
        }

        $content = "BT\n/F1 16 Tf\n50 748 Td\n(PCU Helpdesk Support Ticket) Tj\n/F1 11 Tf\n0 -28 Td\n";

        foreach ($wrappedLines as $line) {
            if ($line !== '') {
                $content .= '('.$this->escapePdfText($line).") Tj\n";
            }

            $content .= "0 -15 Td\n";
        }

        $content .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content).">>\nstream\n".$content."\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $crossReferenceOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf
            ."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n"
            ."startxref\n{$crossReferenceOffset}\n%%EOF";
    }

    private function escapePdfText(string $text): string
    {
        $singleLineText = preg_replace('/\s+/', ' ', Str::ascii($text)) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $singleLineText);
    }
}
