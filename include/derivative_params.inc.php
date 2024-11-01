<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Formats a size name into a 2 chars identifier usable in filename.
 *
 * @param string $t one of IMG_*
 */
function derivative_to_url(
    string $t
): string {
    return substr($t, 0, 2);
}

/**
 * Formats a size array into a identifier usable in filename.
 *
 * @param int[] $s
 * @return string
 */
function size_to_url(
    array $s
): int|string {
    if ($s[0] == $s[1]) {
        return $s[0];
    }

    return $s[0] . 'x' . $s[1];
}

/**
 * @param int[] $s1
 * @param int[] $s2
 */
function size_equals(
    array $s1,
    array $s2
): bool {
    return $s1[0] == $s2[0] && $s1[1] == $s2[1];
}

/**
 * Converts a char a-z into a float.
 */
function char_to_fraction(
    string $c
): int {
    return (ord($c) - ord('a')) / 25;
}

/**
 * Converts a float into a char a-z.
 */
function fraction_to_char(
    float|int $f
): string {
    return chr((int) (ord('a') + round($f * 25)));
}

/**
 * Small utility to manipulate a 'rectangle'.
 */
final class ImageRect
{
    public int|float $l = 0;

    public int|float $t = 0;

    public int|float $r;

    public int|float $b;

    /**
     * @param int[] $l width and height
     */
    public function __construct(
        array $l
    ) {
        $this->r = (int) $l[0];
        $this->b = (int) $l[1];
    }

    public function width(): int|float
    {
        return $this->r - $this->l;
    }

    public function height(): int|float
    {
        return $this->b - $this->t;
    }

    /**
     * Crops horizontally this rectangle by increasing left side and/or reducing the right side.
     *
     * @param int|float $pixels - the amount to substract from the width
     * @param string $coi - a 4 character string (or null) containing the center of interest
     */
    public function crop_h(
        int|float $pixels,
        string|null $coi
    ): void {
        if ($this->width() <= $pixels) {
            return;
        }

        $tlcrop = floor($pixels / 2);

        if ($coi !== null && $coi !== '' && $coi !== '0') {
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
     * @param int|float $pixels - the amount to substract from the height
     * @param string $coi - a 4 character string (or null) containing the center of interest
     */
    public function crop_v(
        int|float $pixels,
        string|null $coi
    ): void {
        if ($this->height() <= $pixels) {
            return;
        }

        $tlcrop = floor($pixels / 2);

        if ($coi !== null && $coi !== '' && $coi !== '0') {
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

/**
 * Paramaters for derivative scaling and cropping.
 * Instance of this class contained by DerivativeParams class.
 */
final class SizingParams
{
    /**
     * @param int[] $ideal_size - two element array of maximum output dimensions (width, height)
     * @param float $max_crop - from 0=no cropping to 1= max cropping (100% of width/height);
     *    expressed as a factor of the input width/height
     * @param int[] $min_size - (used only if _$max_crop_ !=0) two element array of output dimensions (width, height)
     */
    public function __construct(
        public array $ideal_size,
        public float $max_crop = 0,
        public array|null $min_size = null
    ) {}

    /**
     * Returns a simple SizingParams object.
     */
    public static function classic(
        int $w,
        int $h
    ): self {
        return new self([$w, $h]);
    }

    /**
     * Returns a square SizingParams object.
     */
    public static function square(
        int $w
    ): self {
        return new self([$w, $w], 1, [$w, $w]);
    }

    /**
     * Adds tokens depending on sizing configuration.
     */
    public function add_url_tokens(
        array &$tokens
    ): void {
        if ($this->max_crop == 0) {
            $tokens[] = 's' . size_to_url($this->ideal_size);
        } elseif ($this->max_crop == 1 && size_equals($this->ideal_size, $this->min_size)) {
            $tokens[] = 'e' . size_to_url($this->ideal_size);
        } else {
            $tokens[] = size_to_url($this->ideal_size);
            $tokens[] = fraction_to_char($this->max_crop);
            $tokens[] = size_to_url($this->min_size);
        }
    }

    /**
     * Calculates the cropping rectangle and the scaled size for an input image size.
     *
     * @param int[] $in_size - two element array of input dimensions (width, height)
     * @param string|null $coi - four character encoded string containing the center of interest (unused if max_crop=0)
     * @param ImageRect $crop_rect - ImageRect containing the cropping rectangle or null if cropping is not required
     * @param int[] $scale_size - two element array containing width and height of the scaled image
     */
    public function compute(
        array $in_size,
        string|null $coi,
        ImageRect|null &$crop_rect,
        array|null &$scale_size
    ): void {
        $destCrop = new ImageRect($in_size);

        if ($this->max_crop > 0) {
            $ratio_w = $destCrop->width() / $this->ideal_size[0];
            $ratio_h = $destCrop->height() / $this->ideal_size[1];
            if ($ratio_w > 1 || $ratio_h > 1) {
                if ($ratio_w > $ratio_h) {
                    $h = $destCrop->height() / $ratio_w;
                    if ($h < $this->min_size[1]) {
                        $idealCropPx = $destCrop->width() - floor($destCrop->height() * $this->ideal_size[0] / $this->min_size[1]);
                        $maxCropPx = round($this->max_crop * $destCrop->width());
                        $destCrop->crop_h(min($idealCropPx, $maxCropPx), $coi);
                    }
                } else {
                    $w = $destCrop->width() / $ratio_h;
                    if ($w < $this->min_size[0]) {
                        $idealCropPx = $destCrop->height() - floor($destCrop->width() * $this->ideal_size[1] / $this->min_size[0]);
                        $maxCropPx = round($this->max_crop * $destCrop->height());
                        $destCrop->crop_v(min($idealCropPx, $maxCropPx), $coi);
                    }
                }
            }
        }

        $scale_size = [$destCrop->width(), $destCrop->height()];

        $ratio_w = $destCrop->width() / $this->ideal_size[0];
        $ratio_h = $destCrop->height() / $this->ideal_size[1];
        if ($ratio_w > 1 || $ratio_h > 1) {
            if ($ratio_w > $ratio_h) {
                $scale_size[0] = $this->ideal_size[0];
                $scale_size[1] = floor(1e-6 + $scale_size[1] / $ratio_w);
            } else {
                $scale_size[0] = floor(1e-6 + $scale_size[0] / $ratio_h);
                $scale_size[1] = $this->ideal_size[1];
            }
        } else {
            $scale_size = null;
        }

        $crop_rect = null;
        if ($destCrop->width() != $in_size[0] || $destCrop->height() != $in_size[1]) {
            $crop_rect = $destCrop;
        }
    }
}

/**
 * All needed parameters to generate a derivative image.
 */
final class DerivativeParams
{
    /**
     * among IMG_*
     */
    public string $type = IMG_CUSTOM;

    /**
     * used for non-custom images to regenerate the cached files
     */
    public int $last_mod_time = 0;

    public bool $use_watermark = false;

    /**
     * from 0=no sharpening to 1=max sharpening
     */
    public float $sharpen = 0;

    public function __construct(
        public SizingParams $sizing
    ) {}

    public function __sleep(): array
    {
        return ['last_mod_time', 'sizing', 'sharpen'];
    }

    /**
     * Adds tokens depending on sizing configuration.
     */
    public function add_url_tokens(
        array &$tokens
    ): void {
        $this->sizing->add_url_tokens($tokens);
    }

    /**
     * @return int[]
     */
    public function compute_final_size(
        array $in_size
    ): mixed {
        $this->sizing->compute($in_size, null, $crop_rect, $scale_size);
        return $scale_size != null ? $scale_size : $in_size;
    }

    public function max_width(): int
    {
        return $this->sizing->ideal_size[0];
    }

    public function max_height(): int
    {
        return $this->sizing->ideal_size[1];
    }

    /**
     * @todo : description of DerivativeParams::is_identity
     */
    public function is_identity(array $in_size): bool
    {
        return $in_size[0] <= $this->sizing->ideal_size[0] && $in_size[1] <= $this->sizing->ideal_size[1];
    }

    public function will_watermark(
        array $out_size
    ): bool {
        if ($this->use_watermark) {
            $min_size = ImageStdParams::get_watermark()->min_size;
            return $min_size[0] <= $out_size[0]
              || $min_size[1] <= $out_size[1];
        }

        return false;
    }
}
