<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/derivative_params.inc.php';

/**
 * Small utility to manipulate a 'rectangle'.
 */
final class ImageRect
{
    /**
     * @var int
     * @var int
     * @var int
     * @var int
     */
    public $l = 0;

    public $t = 0;

    public $r;

    public $b;

    /**
     * @param int[] $l width and height
     */
    public function __construct($l)
    {
        $this->r = $l[0];
        $this->b = $l[1];
    }

    /**
     * @return int
     */
    public function width()
    {
        return $this->r - $this->l;
    }

    /**
     * @return int
     */
    public function height()
    {
        return $this->b - $this->t;
    }

    /**
     * Crops horizontally this rectangle by increasing left side and/or reducing the right side.
     *
     * @param int $pixels - the amount to substract from the width
     * @param stirng $coi - a 4 character string (or null) containing the center of interest
     */
    public function crop_h(
        $pixels,
        $coi
    ) {
        if ($this->width() <= $pixels) {
            return;
        }

        $tlcrop = floor($pixels / 2);

        if (! empty($coi)) {
            $coil = floor($this->r * char_to_fraction($coi[0]));
            $coir = ceil($this->r * char_to_fraction($coi[2]));
            $availableL = $coil > $this->l ? $coil - $this->l : 0;
            $availableR = $coir < $this->r ? $this->r - $coir : 0;
            if ($availableL + $availableR >= $pixels) {
                if ($availableL < $tlcrop) {
                    $tlcrop = $availableL;
                } elseif ($availableR < $tlcrop) {
                    $tlcrop = $pixels - $availableR;
                }
            }
        }

        $this->l += $tlcrop;
        $this->r -= $pixels - $tlcrop;
    }

    /**
     * Crops vertically this rectangle by increasing top side and/or reducing the bottom side.
     *
     * @param int $pixels - the amount to substract from the height
     * @param string $coi - a 4 character string (or null) containing the center of interest
     */
    public function crop_v(
        $pixels,
        $coi
    ) {
        if ($this->height() <= $pixels) {
            return;
        }

        $tlcrop = floor($pixels / 2);

        if (! empty($coi)) {
            $coit = floor($this->b * char_to_fraction($coi[1]));
            $coib = ceil($this->b * char_to_fraction($coi[3]));
            $availableT = $coit > $this->t ? $coit - $this->t : 0;
            $availableB = $coib < $this->b ? $this->b - $coib : 0;
            if ($availableT + $availableB >= $pixels) {
                if ($availableT < $tlcrop) {
                    $tlcrop = $availableT;
                } elseif ($availableB < $tlcrop) {
                    $tlcrop = $pixels - $availableB;
                }
            }
        }

        $this->t += $tlcrop;
        $this->b -= $pixels - $tlcrop;
    }
}
