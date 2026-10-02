<?php

namespace App\Support;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Qr
{
    /** Inline-SVG (ohne XML-Header), skalierbar per CSS. */
    public static function svg(string $text): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
            'svgAddXmlHeader' => false,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'drawLightModules' => false,
            'svgUseFillAttributes' => true,
        ]);

        return (new QRCode($options))->render($text);
    }
}
