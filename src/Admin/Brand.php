<?php
/**
 * The Mercury Schema mark: a slanted "M" with a four-point spark.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Admin;

class Brand
{
    public const COLOR = '#1F4FD6';

    /**
     * Inline SVG of the mark in one colour. $color must be a trusted literal.
     */
    public static function mark(string $color): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -26 154 126" focusable="false">'
            . '<g transform="translate(21,0) skewX(-12)"><polyline points="14,86 14,14 58,66 102,14 102,86" fill="none" stroke="' . $color . '" stroke-width="28" stroke-linecap="round" stroke-linejoin="round"/></g>'
            . '<path transform="translate(142,-10) scale(1.3)" d="M0,-10 C1.2,-2.4 2.4,-1.2 10,0 C2.4,1.2 1.2,2.4 0,10 C-1.2,2.4 -2.4,1.2 -10,0 C-2.4,-1.2 -1.2,-2.4 0,-10Z" fill="' . $color . '"/>'
            . '</svg>';
    }
}
