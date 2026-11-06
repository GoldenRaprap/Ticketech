<?php

namespace App\Services;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class EmailBranding
{
    public function embedLogos(Email $email): void
    {
        foreach ([
            ['TickeTech_Logo.png', 'ticketech-logo@ticketech'],
            ['Philippine_Christian_University_logo.png', 'pcu-logo@ticketech'],
        ] as [$filename, $contentId]) {
            $email->addPart(
                (new DataPart(
                    new File(public_path('images/'.$filename)),
                    $filename,
                    'image/png',
                ))->asInline()->setContentId($contentId),
            );
        }

        $htmlBody = $email->getHtmlBody();

        if ($htmlBody !== null) {
            $htmlBody = preg_replace_callback(
                '/<p\b([^>]*)>(\s*)(<img\b(?=[^>]*src="cid:pcu-logo@ticketech")[^>]*>)(\s*)<\/p>/i',
                function (array $matches): string {
                    $attributes = preg_replace('/text-align:\s*left;?/i', 'text-align: center;', $matches[1]);
                    $image = preg_replace('/\s+(?:width|style)="[^"]*"/i', '', $matches[3]);

                    return '<p'.$attributes.' align="center">'.$matches[2].rtrim($image, '>')
                        .' width="90" style="display: block; width: 90px; max-width: 90px; height: auto; margin: 0 auto;">'
                        .$matches[4].'</p>';
                },
                $htmlBody,
            );

            if ($htmlBody !== null) {
                $email->html($htmlBody);
            }
        }
    }
}
