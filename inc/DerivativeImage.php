<?php

declare(strict_types=1);

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Holds information (path, url, dimensions) about a derivative image.
 * A derivative image is constructed from a source image (SrcImage class)
 * and derivative parameters (DerivativeParams class).
 */
final class DerivativeImage
{
    private array|DerivativeParams|null $params = null;

    private string $rel_path = '';

    private string $rel_url = '';

    /**
     * @param DerivativeParams|string $type standard derivative param type (e.g. IMG_*)
     *    or a DerivativeParams object
     * @param SrcImage $src_image the source image of this derivative
     */
    public function __construct(
        DerivativeParams|string $type,
        public SrcImage $src_image
    ) {
        $this->params = is_string($type) ? ImageStdParams::get_by_type($type) : $type;

        self::build($this->src_image, $this->params, $this->rel_path, $this->rel_url);
    }

    /**
     * Generates the url of a thumbnail.
     *
     * @param SrcImage|array $infos array of info from db or SrcImage
     */
    public static function thumb_url(
        SrcImage|array $infos
    ): string {
        return self::url(IMG_THUMB, $infos);
    }

    /**
     * Generates the url for a particular photo size.
     *
     * @param DerivativeParams|string $type standard derivative param type (e.g. IMG_*)
     *    or a DerivativeParams object
     * @param SrcImage|array $infos array of info from db or SrcImage
     */
    public static function url(
        DerivativeParams|string $type,
        SrcImage|array $infos
    ): string {
        $src_image = is_object($infos) ? $infos : new SrcImage($infos);
        $params = is_string($type) ? ImageStdParams::get_by_type($type) : $type;
        self::build($src_image, $params, $rel_path, $rel_url);
        if ($params == null) {
            return $src_image->get_url();
        }

        return embellish_url(
            trigger_change(
                'get_derivative_url',
                get_root_url() . $rel_url,
                $params,
                $src_image,
                $rel_url
            )
        );
    }

    /**
     * Return associative an array of all DerivativeImage for a specific image.
     * Disabled derivative types can be still found in the return, mapped to an
     * enabled derivative (e.g. the values are not unique in the return array).
     * This is useful for any plugin/theme to just use $deriv[IMG_XLARGE] even if
     * the XLARGE is disabled.
     *
     * @param SrcImage|array $src_image array of info from db or SrcImage
     * @return DerivativeImage[]
     */
    public static function get_all(
        SrcImage|array $src_image
    ): array {
        if (! is_object($src_image)) {
            $src_image = new SrcImage($src_image);
        }

        $ret = [];
        // build enabled types
        foreach (ImageStdParams::get_defined_type_map() as $type => $params) {
            $derivative = new self($params, $src_image);
            $ret[$type] = $derivative;
        }

        // disabled types, fallback to enabled types
        foreach (ImageStdParams::get_undefined_type_map() as $type => $type2) {
            $ret[$type] = $ret[$type2];
        }

        return $ret;
    }

    /**
     * Returns an instance of DerivativeImage for a specific image and size.
     * Disabled derivatives fallback to an enabled derivative.
     *
     * @param string $type standard derivative param type (e.g. IMG_*)
     * @param SrcImage|array $src_image array of info from db or SrcImage
     * @return DerivativeImage|null null if $type not found
     */
    public static function get_one(
        string $type,
        SrcImage|array $src_image
    ): ?self {
        if (! is_object($src_image)) {
            $src_image = new SrcImage($src_image);
        }

        $defined = ImageStdParams::get_defined_type_map();
        if (isset($defined[$type])) {
            return new self($defined[$type], $src_image);
        }

        $undefined = ImageStdParams::get_undefined_type_map();
        if (isset($undefined[$type])) {
            return new self($defined[$undefined[$type]], $src_image);
        }

        return null;
    }

    public function get_path(): string
    {
        return PHPWG_ROOT_PATH . $this->rel_path;
    }

    public function get_url(): string
    {
        if ($this->params == null) {
            return $this->src_image->get_url();
        }

        return embellish_url(
            trigger_change(
                'get_derivative_url',
                get_root_url() . $this->rel_url,
                $this->params,
                $this->src_image,
                $this->rel_url
            )
        );
    }

    public function same_as_source(): bool
    {
        return $this->params == null;
    }

    /**
     * @return string one if IMG_* or 'Original'
     */
    public function get_type(): string
    {
        if ($this->params == null) {
            return 'Original';
        }

        return $this->params->type;
    }

    public function get_size(): array|null
    {
        if ($this->params == null) {
            return $this->src_image->get_size();
        }

        return $this->params->compute_final_size($this->src_image->get_size());
    }

    /**
     * Returns the size as CSS rule.
     */
    public function get_size_css(): string
    {
        $size = $this->get_size();
        if ($size) {
            return 'width:' . $size[0] . 'px; height:' . $size[1] . 'px';
        }
    }

    /**
     * Returns the size as HTML attributes.
     */
    public function get_size_htm(): string
    {
        $size = $this->get_size();
        if ($size) {
            return 'width="' . $size[0] . '" height="' . $size[1] . '"';
        }

        return '';

    }

    /**
     * Returns literal size: $widthx$height.
     */
    public function get_size_hr(): string
    {
        $size = $this->get_size();
        if ($size) {
            return $size[0] . ' x ' . $size[1];
        }
    }

    /**
     * @return int[]
     */
    public function get_scaled_size(int $maxw, int $maxh): array
    {
        $size = $this->get_size();
        if ($size) {
            $ratio_w = $size[0] / $maxw;
            $ratio_h = $size[1] / $maxh;
            if ($ratio_w > 1 || $ratio_h > 1) {
                if ($ratio_w > $ratio_h) {
                    $size[0] = $maxw;
                    $size[1] = floor($size[1] / $ratio_w);
                } else {
                    $size[0] = floor($size[0] / $ratio_h);
                    $size[1] = $maxh;
                }
            }
        }

        return $size;
    }

    /**
     * Returns the scaled size as HTML attributes.
     */
    public function get_scaled_size_htm(
        int $maxw = 9999,
        int $maxh = 9999
    ): string {
        $size = $this->get_scaled_size($maxw, $maxh);
        if ($size !== []) {
            return 'width="' . $size[0] . '" height="' . $size[1] . '"';
        }
    }

    private static function build(
        SrcImage $src,
        array|DerivativeParams|null &$params,
        string|null &$rel_path,
        string|null &$rel_url
    ): void {
        if ($src->has_size() && $params->is_identity(
            $src->get_size()
        )) {// the source image is smaller than what we should do - we do not upsample
            if (! $params->will_watermark(
                $src->get_size()
            ) && ! $src->rotation) {// no watermark, no rotation required -> we will use the source image
                $params = null;
                $rel_path = $src->rel_path;
                $rel_url = $src->rel_path;
                return;
            }

            $defined_types = array_keys(ImageStdParams::get_defined_type_map());
            $counter = count($defined_types);
            for ($i = 0; $i < $counter; $i++) {
                if ($defined_types[$i] == $params->type) {
                    for ($i--; $i >= 0; $i--) {
                        $smaller = ImageStdParams::get_by_type($defined_types[$i]);
                        if ($smaller->sizing->max_crop == $params->sizing->max_crop && $smaller->is_identity(
                            $src->get_size()
                        )) {
                            $params = $smaller;
                            self::build($src, $params, $rel_path, $rel_url);
                            return;
                        }
                    }

                    break;
                }
            }
        }

        $tokens = [];
        $tokens[] = substr($params->type, 0, 2);

        if ($params->type == IMG_CUSTOM) {
            $params->add_url_tokens($tokens);
        }

        $loc = $src->rel_path;
        if (substr_compare($loc, './', 0, 2) == 0) {
            $loc = substr($loc, 2);
        } elseif (substr_compare($loc, '../', 0, 3) == 0) {
            $loc = substr($loc, 3);
        }

        $loc = substr_replace($loc, '-' . implode('_', $tokens), strrpos($loc, '.'), 0);

        $rel_path = PWG_DERIVATIVE_DIR . $loc;

        global $conf;
        $url_style = $conf['derivative_url_style'];
        if (! $url_style) {
            $mtime = file_exists(PHPWG_ROOT_PATH . $rel_path) ? filemtime(PHPWG_ROOT_PATH . $rel_path) : false;
            $url_style = $mtime === false || $mtime < $params->last_mod_time ? 2 : 1;
        }

        if ($url_style == 2) {
            $rel_url = 'i';
            if ($conf['php_extension_in_urls']) {
                $rel_url .= '.php';
            }

            if ($conf['question_mark_in_urls']) {
                $rel_url .= '?';
            }

            $rel_url .= '/' . $loc;
        } else {
            $rel_url = $rel_path;
        }
    }
}
