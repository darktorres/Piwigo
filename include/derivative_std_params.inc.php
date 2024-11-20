<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

define('IMG_SQUARE', 'square');
define('IMG_THUMB', 'thumb');
define('IMG_XXSMALL', '2small');
define('IMG_XSMALL', 'xsmall');
define('IMG_SMALL', 'small');
define('IMG_MEDIUM', 'medium');
define('IMG_LARGE', 'large');
define('IMG_XLARGE', 'xlarge');
define('IMG_XXLARGE', 'xxlarge');
define('IMG_CUSTOM', 'custom');

require_once(PHPWG_ROOT_PATH . 'include/functions.inc.php');

/**
 * Container for watermark configuration.
 */
final class WatermarkParams
{
    public string $file = '';

    /**
     * @var int[]
     */
    public $min_size = [500, 500];

    public int $xpos = 50;

    public int $ypos = 50;

    public int $xrepeat = 0;

    public int $yrepeat = 0;

    public int $opacity = 100;
}

/**
 * Container for standard derivatives parameters.
 */
final class ImageStdParams
{
    public static array $custom = [];

    public static int $quality = 95;

    /**
     * @var string[]
     */
    private static array $all_types = [
        IMG_SQUARE, IMG_THUMB, IMG_XXSMALL, IMG_XSMALL, IMG_SMALL,
        IMG_MEDIUM, IMG_LARGE, IMG_XLARGE, IMG_XXLARGE,
    ];

    /**
     * @var DerivativeParams[]
     */
    private static $all_type_map = [];

    /**
     * @var DerivativeParams[]
     */
    private static $type_map = [];

    /**
     * @var DerivativeParams[]
     */
    private static array $undefined_type_map = [];

    private static WatermarkParams $watermark;

    /**
     * @return string[]
     */
    public static function get_all_types(): array
    {
        return self::$all_types;
    }

    /**
     * @return DerivativeParams[]
     */
    public static function get_all_type_map(): array
    {
        return self::$all_type_map;
    }

    /**
     * @return DerivativeParams[]
     */
    public static function get_defined_type_map(): array
    {
        return self::$type_map;
    }

    /**
     * @return DerivativeParams[]
     */
    public static function get_undefined_type_map(): array
    {
        return self::$undefined_type_map;
    }

    public static function get_by_type(
        $type
    ): DerivativeParams {
        return self::$all_type_map[$type];
    }

    public static function get_custom(
        string|int $w,
        string|int $h,
        float $crop = 0,
        string|int|null $minw = null,
        string|int|null $minh = null
    ): DerivativeParams {
        $params = new DerivativeParams(new SizingParams([$w, $h], $crop, [$minw, $minh]));
        self::apply_global($params);

        $key = [];
        $params->add_url_tokens($key);
        $key = implode('_', $key);
        if ((self::$custom[$key] ?? null) < time() - 24 * 3600) {
            self::$custom[$key] = time();
            self::save();
        }
        return $params;
    }

    public static function get_watermark(): WatermarkParams
    {
        return self::$watermark;
    }

    /**
     * Loads derivative configuration from database or initializes it.
     */
    public static function load_from_db(): void
    {
        global $conf;
        $arr = is_serialized($conf['derivatives'] ?? null) ? unserialize($conf['derivatives']) : false;
        if ($arr !== false) {
            self::$type_map = $arr['d'];
            self::$watermark = $arr['w'];
            if (! self::$watermark) {
                self::$watermark = new WatermarkParams();
            }
            self::$custom = $arr['c'];
            if (! self::$custom) {
                self::$custom = [];
            }
            if (isset($arr['q'])) {
                self::$quality = $arr['q'];
            }
        } else {
            self::$watermark = new WatermarkParams();
            self::$type_map = self::get_default_sizes();
            self::save();
        }
        self::build_maps();
    }

    public static function set_watermark(
        WatermarkParams $watermark
    ): void {
        self::$watermark = $watermark;
    }

    /**
     * @see ImageStdParams::save()
     *
     * @param DerivativeParams[] $map
     */
    public static function set_and_save(
        array $map
    ): void {
        self::$type_map = $map;
        self::save();
        self::build_maps();
    }

    /**
     * Saves the configuration in database.
     */
    public static function save(): void
    {
        $ser = serialize([
            'd' => self::$type_map,
            'q' => self::$quality,
            'w' => self::$watermark,
            'c' => self::$custom,
        ]);
        conf_update_param('derivatives', addslashes($ser));
    }

    /**
     * @return DerivativeParams[]
     */
    public static function get_default_sizes(): array
    {
        $arr = [
            IMG_SQUARE => new DerivativeParams(SizingParams::square(120)),
            IMG_THUMB => new DerivativeParams(SizingParams::classic(144, 144)),
            IMG_XXSMALL => new DerivativeParams(SizingParams::classic(240, 240)),
            IMG_XSMALL => new DerivativeParams(SizingParams::classic(432, 324)),
            IMG_SMALL => new DerivativeParams(SizingParams::classic(576, 432)),
            IMG_MEDIUM => new DerivativeParams(SizingParams::classic(792, 594)),
            IMG_LARGE => new DerivativeParams(SizingParams::classic(1008, 756)),
            IMG_XLARGE => new DerivativeParams(SizingParams::classic(1224, 918)),
            IMG_XXLARGE => new DerivativeParams(SizingParams::classic(1656, 1242)),
        ];
        $now = time();
        foreach ($arr as $params) {
            $params->last_mod_time = $now;
        }
        return $arr;
    }

    /**
     * Compute 'apply_watermark'
     */
    public static function apply_global(
        DerivativeParams $params
    ): void {
        $params->use_watermark = ! empty(self::$watermark->file) &&
            (self::$watermark->min_size[0] <= $params->sizing->ideal_size[0]
            or self::$watermark->min_size[1] <= $params->sizing->ideal_size[1]);
    }

    /**
     * Build 'type_map', 'all_type_map' and 'undefined_type_map'.
     */
    private static function build_maps(): void
    {
        foreach (self::$type_map as $type => $params) {
            $params->type = $type;
            self::apply_global($params);
        }
        self::$all_type_map = self::$type_map;

        for ($i = 0; $i < count(self::$all_types); $i++) {
            $tocheck = self::$all_types[$i];
            if (! isset(self::$type_map[$tocheck])) {
                for ($j = $i - 1; $j >= 0; $j--) {
                    $target = self::$all_types[$j];
                    if (isset(self::$type_map[$target])) {
                        self::$all_type_map[$tocheck] = self::$type_map[$target];
                        self::$undefined_type_map[$tocheck] = $target;
                        break;
                    }
                }
            }
        }
    }
}
