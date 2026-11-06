<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Str;

class TicketPdf
{
    public function __construct(private RequesterTicketDocument $document) {}

    public function generate(Ticket $ticket): string
    {
        $document = $this->document->forRequester($ticket);
        $pages = [];
        $content = '';
        $y = 0;
        $startPage = function () use (&$content, &$y): void {
            $content = "q\n0.08 0.25 0.49 rg\n0 700 612 92 re f\nQ\n";
            $content .= $this->textCommand('PCU HELPDESK', 50, 760, 'F2', 10, [1, 1, 1]);
            $content .= $this->textCommand('SUPPORT REQUEST', 50, 738, 'F1', 9, [0.83, 0.89, 0.96]);
            $y = 670;
        };
        $finishPage = function () use (&$pages, &$content, $document): void {
            $content .= $this->textCommand('PCU Helpdesk  |  Ticket '.$document['ticket_number'], 50, 30, 'F1', 8, [0.42, 0.51, 0.62]);
            $content .= $this->textCommand('Check ticket status: '.route('ticket.check'), 50, 16, 'F1', 8, [0.13, 0.34, 0.58]);
            $pages[] = $content;
        };
        $ensureSpace = function (int $height) use (&$y, $startPage, $finishPage): void {
            if ($y - $height < 54) {
                $finishPage();
                $startPage();
            }
        };
        $writeLines = function (string $text, string $font = 'F1', int $size = 10, array $color = [0.12, 0.23, 0.36]) use (&$content, &$y, $ensureSpace): void {
            $paragraphs = preg_split('/\R/', Str::ascii($text)) ?: [''];

            foreach ($paragraphs as $paragraph) {
                $lines = $paragraph === '' ? [''] : explode("\n", wordwrap($paragraph, 88, "\n", true));

                foreach ($lines as $line) {
                    $ensureSpace(15);

                    if ($line !== '') {
                        $content .= $this->textCommand($line, 50, $y, $font, $size, $color);
                    }

                    $y -= 15;
                }
            }
        };
        $wrapLines = function (string $text, int $width): array {
            $paragraphs = preg_split('/\R/', Str::ascii($text)) ?: [''];
            $lines = [];

            foreach ($paragraphs as $paragraph) {
                array_push(
                    $lines,
                    ...($paragraph === '' ? [''] : explode("\n", wordwrap($paragraph, $width, "\n", true))),
                );
            }

            return $lines;
        };
        $writeDetailGroup = function (array $group) use (
            &$content,
            &$y,
            $startPage,
            $finishPage,
            $wrapLines,
        ): void {
            $rows = array_chunk($group['details'], 2);
            $rowHeights = [];

            foreach ($rows as $row) {
                $rowHeights[] = max(array_map(
                    fn (array $detail): int => 31 + (11 * (count($wrapLines($detail['value'], 38)) - 1)),
                    $row,
                ));
            }

            $height = 27 + array_sum($rowHeights) + 8;

            if ($y - $height < 58) {
                $finishPage();
                $startPage();
            }

            $top = $y;
            $bottom = $top - $height;
            $content .= "q\n0.97 0.98 0.99 rg\n0.84 0.89 0.94 RG\n50 {$bottom} 512 {$height} re B\nQ\n";
            $content .= "q\n0.91 0.95 0.99 rg\n50 ".($top - 27)." 512 27 re f\nQ\n";
            $content .= $this->textCommand(Str::upper($group['title']), 62, $top - 18, 'F2', 9, [0.13, 0.34, 0.58]);
            $rowTop = $top - 27;

            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $columnIndex => $detail) {
                    $x = 62 + ($columnIndex * 250);
                    $content .= $this->textCommand($detail['label'], $x, $rowTop - 14, 'F1', 8, [0.42, 0.51, 0.62]);

                    foreach ($wrapLines($detail['value'], 38) as $lineIndex => $line) {
                        $content .= $this->textCommand($line, $x, $rowTop - 27 - ($lineIndex * 11), 'F2', 9, [0.12, 0.23, 0.36]);
                    }
                }

                $rowTop -= $rowHeights[$rowIndex];
            }

            $y = $bottom - 14;
        };

        $startPage();
        $writeLines($document['subject'], 'F2', 18, [0.08, 0.25, 0.49]);
        $writeLines('Ticket '.$document['ticket_number'].'  |  Submitted '.$document['submitted_at'], 'F1', 9, [0.39, 0.48, 0.58]);
        $y -= 8;

        foreach ($document['detail_groups'] as $group) {
            $writeDetailGroup($group);
        }

        $ensureSpace(28);
        $content .= "q\n0.91 0.95 0.99 rg\n50 ".($y - 4)." 512 18 re f\nQ\n";
        $content .= $this->textCommand('TICKET CONVERSATION', 60, $y, 'F2', 9, [0.13, 0.34, 0.58]);
        $y -= 28;

        foreach ($document['messages'] as $index => $message) {
            $ensureSpace(35);
            if ($index > 0) {
                $content .= "q\n0.82 0.87 0.92 RG\n50 ".($y + 6)." m\n562 ".($y + 6)." l\nS\nQ\n";
                $y -= 5;
            }

            $writeLines($message['author'].'  |  '.$message['context'].'  |  '.$message['created_at'], 'F2', 9, [0.16, 0.31, 0.47]);
            $writeLines($message['body'], 'F1', 9);

            foreach ($message['attachments'] as $attachment) {
                $writeLines('Attachment: '.$attachment, 'F1', 9, [0.39, 0.48, 0.58]);
            }

            $y -= 8;
        }

        $finishPage();

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids ['.implode(' ', array_map(
                fn (int $index): string => (5 + ($index * 2)).' 0 R',
                array_keys($pages),
            )).'] /Count '.count($pages).' >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        foreach ($pages as $index => $pageContent) {
            $pageObject = 5 + ($index * 2);
            $streamObject = $pageObject + 1;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$streamObject.' 0 R >>';
            $objects[] = '<< /Length '.strlen($pageContent).">>\nstream\n".$pageContent."\nendstream";
        }

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

    /**
     * @param  array{0: float|int, 1: float|int, 2: float|int}  $color
     */
    private function textCommand(string $text, int $x, int $y, string $font, int $size, array $color): string
    {
        $singleLineText = preg_replace('/\s+/', ' ', Str::ascii($text)) ?? '';
        $escapedText = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $singleLineText);

        return sprintf(
            "BT\n/%s %d Tf\n%.3F %.3F %.3F rg\n1 0 0 1 %d %d Tm\n(%s) Tj\nET\n",
            $font,
            $size,
            $color[0],
            $color[1],
            $color[2],
            $x,
            $y,
            $escapedText,
        );
    }
}
