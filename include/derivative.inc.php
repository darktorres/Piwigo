<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * A source image is used to get a derivative image. It is either
 * the original file for a jpg/png/... or a 'representative' image
 * of a  non image file or a standard icon for the non-image file.
 */
final class SrcImage
{
    public const int IS_ORIGINAL = 0x01;

    public const int IS_MIMETYPE = 0x02;

    public const int DIM_NOT_GIVEN = 0x04;

    public string $id;

    public string $rel_path;

    public int $rotation = 0;

    /**
     * @var ?int[]
     */
    private ?array $size = null;

    private int $flags = 0;

    /**
     * @param array $infos assoc array of data from images table
     */
    public function __construct(
        array $infos
    ) {
        global $conf;

        $this->id = $infos['id'];
        $ext = strtolower(get_extension($infos['path']));
        // $infos['file_ext'] = strtolower(get_extension($infos['file']));
        $infos['path_ext'] = $ext;
        if (in_array($ext, $conf['picture_ext'])) {
            $this->rel_path = $infos['path'];
            $this->flags |= self::IS_ORIGINAL;
        } elseif (! empty($infos['representative_ext'])) {
            $this->rel_path = original_to_representative($infos['path'], $infos['representative_ext']);
        } else {
            $this->rel_path = trigger_change('get_mimetype_location', get_themeconf('mime_icon_dir') . $ext . '.png', $ext);
            $this->flags |= self::IS_MIMETYPE;
            $size = file_exists(PHPWG_ROOT_PATH . $this->rel_path) ? getimagesize(PHPWG_ROOT_PATH . $this->rel_path) : false;
            if ($size === false) {
                $this->rel_path = $ext === 'svg' ? $infos['path'] : 'themes/default/icon/mimetypes/unknown.png';

                $size = getimagesize(PHPWG_ROOT_PATH . $this->rel_path);
            }

            $this->size = [$size[0], $size[1]];
        }

        // Split the string by "/"
        $segments = explode('/', (string) $this->rel_path);
        // Apply rawurlencode to each segment
        $encodedSegments = array_map(rawurlencode(...), $segments);
        // Join the segments back together with "/"
        $this->rel_path = implode('/', $encodedSegments);

        if (! $this->size) {
            if (isset($infos['width']) && isset($infos['height'])) {
                $width = $infos['width'];
                $height = $infos['height'];

                $this->rotation = intval($infos['rotation']) % 4;
                // 1 or 5 =>  90 clockwise
                // 3 or 7 => 270 clockwise
                if ($this->rotation % 2 !== 0) {
                    $width = $infos['height'];
                    $height = $infos['width'];
                }

                $this->size = [$width, $height];
            } elseif (! array_key_exists('width', $infos)) {
                $this->flags |= self::DIM_NOT_GIVEN;
            }
        }
    }

    public function is_original(): int
    {
        return $this->flags & self::IS_ORIGINAL;
    }

    public function is_mimetype(): int
    {
        return $this->flags & self::IS_MIMETYPE;
    }

    public function get_path(): string
    {
        $segments = explode('/', $this->rel_path);
        $decodedSegments = array_map(rawurldecode(...), $segments);
        return PHPWG_ROOT_PATH . implode('/', $decodedSegments);
    }

    public function get_url(): string
    {
        $url = get_root_url() . $this->rel_path;
        if (($this->flags & self::IS_MIMETYPE) === 0) {
            $url = trigger_change('get_src_image_url', $url, $this);
        }

        return embellish_url($url);
    }

    public function has_size(): bool
    {
        return $this->size != null;
    }

    /**
     * @return ?int[] 0=width, 1=height or null if fail to compute size
     */
    public function get_size(): ?array
    {
        if ($this->size == null) {
            if (($this->flags & self::DIM_NOT_GIVEN) !== 0) {
                fatal_error('SrcImage dimensions required but not provided');
            }

            // probably not metadata synced
            if (($size = getimagesize($this->get_path())) !== false) {
                $this->size = [$size[0], $size[1]];
                $query = <<<SQL
                    UPDATE images
                    SET width = {$size[0]},
                        height = {$size[1]}
                    WHERE id = {$this->id};
                    SQL;
                pwg_query($query);
            }
        }

        return $this->size;
    }
}

/**
 * Holds information (path, url, dimensions) about a derivative image.
 * A derivative image is constructed from a source image (SrcImage class)
 * and derivative parameters (DerivativeParams class).
 */
final class DerivativeImage
{
    private string|DerivativeParams|null $params;

    private ?string $rel_path = null;

    private ?string $rel_url = null;

    /**
     * @param string|DerivativeParams $type standard derivative param type (e.g. IMG_*)
     *    or a DerivativeParams object
     * @param SrcImage $src_image the source image of this derivative
     */
    public function __construct(
        string|DerivativeParams $type,
        public SrcImage $src_image
    ) {
        $this->params = is_string($type) ? ImageStdParams::get_by_type($type) : $type;
        self::build($this->src_image, $this->params, $this->rel_path, $this->rel_url);
    }

    /**
     * Generates the url of a thumbnail.
     *
     * @param array|SrcImage $infos array of info from db or SrcImage
     */
    public static function thumb_url(
        array|SrcImage $infos
    ): string {
        return self::url(IMG_THUMB, $infos);
    }

    /**
     * Generates the url for a particular photo size.
     *
     * @param string|DerivativeParams $type standard derivative param type (e.g. IMG_*)
     *    or a DerivativeParams object
     * @param array|SrcImage $infos array of info from db or SrcImage
     */
    public static function url(
        string|DerivativeParams $type,
        array|SrcImage $infos
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
     * enabled derivative (e.g., the values are not unique in the return array).
     * This is useful for any plugin/theme to just use $deriv[IMG_XLARGE] even if
     * the XLARGE is disabled.
     *
     * @param array|SrcImage $src_image array of info from db or SrcImage
     * @return DerivativeImage[]
     */
    public static function get_all(
        array|SrcImage $src_image
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
     * @param array|SrcImage $src_image array of info from db or SrcImage
     * @return ?DerivativeImage null if $type not found
     */
    public static function get_one(
        string $type,
        array|SrcImage $src_image
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

    /**
     * @return int[]
     */
    public function get_size(): array
    {
        if ($this->params == null) {
            return $this->src_image->get_size();
        }

        return $this->params->compute_final_size($this->src_image->get_size());
    }

    /**
     * Returns the size as CSS rule.
     */
    public function get_size_css(): ?string
    {
        $size = $this->get_size();
        if ($size !== []) {
            return 'width:' . $size[0] . 'px; height:' . $size[1] . 'px';
        }

        return null;
    }

    /**
     * Returns the size as HTML attributes.
     */
    public function get_size_htm(): ?string
    {
        $size = $this->get_size();
        if ($size !== []) {
            return 'width="' . $size[0] . '" height="' . $size[1] . '"';
        }

        return '';
    }

    /**
     * Returns literal size: $widthx$height.
     */
    public function get_size_hr(): ?string
    {
        $size = $this->get_size();
        if ($size !== []) {
            return $size[0] . ' x ' . $size[1];
        }

        return null;
    }

    /**
     * @return int[]
     */
    public function get_scaled_size(
        int $maxw,
        int $maxh
    ): array {
        $size = $this->get_size();
        if ($size !== []) {
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
    ): ?string {
        $size = $this->get_scaled_size($maxw, $maxh);
        if ($size !== []) {
            return 'width="' . $size[0] . '" height="' . $size[1] . '"';
        }

        return null;
    }

    /**
     * @todo : documentation of DerivativeImage::build
     */
    private static function build(
        SrcImage $src,
        DerivativeParams &$params,
        ?string &$rel_path,
        ?string &$rel_url
    ): void {
        if ($src->has_size() && $params->is_identity($src->get_size())) {// the source image is smaller than what we should do - we do not upsample
            if (! $params->will_watermark($src->get_size()) && ! $src->rotation) {// no watermark, no rotation required -> we will use the source image
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
                        if ($smaller->sizing->max_crop === $params->sizing->max_crop && $smaller->is_identity($src->get_size())) {
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

        if ($params->type === IMG_CUSTOM) {
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
