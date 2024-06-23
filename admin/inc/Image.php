<?php

namespace Piwigo\admin\inc;

use function Piwigo\inc\get_extension;
use function Piwigo\inc\get_moment;
use function Piwigo\inc\trigger_notify;

require_once __DIR__ . '/../../Inc/PluginMaintain.php';
require_once __DIR__ . '/../../Inc/functions.inc.php';

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                          Main Image Class                             |
// +-----------------------------------------------------------------------+

class Image
{
    public $image;

    public $library = '';

    public static $ext_imagick_version = '';

    public function __construct(
        public $source_filepath,
        $library = null
    ) {
        trigger_notify('load_image_library', [&$this]);

        if (is_object($this->image)) {
            return; // A plugin may have load its own library
        }

        $extension = strtolower(get_extension($this->source_filepath));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            die('[Image] unsupported file extension');
        }

        if (! ($this->library = self::get_library($library, $extension))) {
            die('No image library available on your server.');
        }

        $class = self::class . $this->library;
        $this->image = new $class($this->source_filepath);
    }

    // Unknow methods will be redirected to image object
    public function __call($method, $arguments)
    {
        return call_user_func_array([$this->image, $method], $arguments);
    }

    // Piwigo resize function
    public function pwg_resize(
        $destination_filepath,
        $max_width,
        $max_height,
        $quality,
        $automatic_rotation = true,
        $strip_metadata = false,
        $crop = false,
        $follow_orientation = true
    ) {
        $starttime = get_moment();

        // width/height
        $source_width = $this->image->get_width();
        $source_height = $this->image->get_height();

        $rotation = null;
        if ($automatic_rotation) {
            $rotation = self::get_rotation_angle($this->source_filepath);
        }

        $resize_dimensions = self::get_resize_dimensions(
            $source_width,
            $source_height,
            $max_width,
            $max_height,
            $rotation,
            $crop,
            $follow_orientation
        );

        // testing on height is useless in theory: if width is unchanged, there
        // should be no resize, because width/height ratio is not modified.
        if ($resize_dimensions['width'] == $source_width && $resize_dimensions['height'] == $source_height) {
            // the image doesn't need any resize! We just copy it to the destination
            copy(
                $this->source_filepath,
                $destination_filepath
            );
            return $this->get_resize_result(
                $destination_filepath,
                $resize_dimensions['width'],
                $resize_dimensions['height'],
                $starttime
            );
        }

        $this->image->set_compression_quality($quality);

        if ($strip_metadata) {
            // we save a few kilobytes. For example a thumbnail with metadata weights 25KB, without metadata 7KB.
            $this->image->strip();
        }

        if (isset($resize_dimensions['crop'])) {
            $this->image->crop(
                $resize_dimensions['crop']['width'],
                $resize_dimensions['crop']['height'],
                $resize_dimensions['crop']['x'],
                $resize_dimensions['crop']['y']
            );
        }

        $this->image->resize($resize_dimensions['width'], $resize_dimensions['height']);

        if (! empty($rotation)) {
            $this->image->rotate($rotation);
        }

        $this->image->write($destination_filepath);

        // everything should be OK if we are here!
        return $this->get_resize_result(
            $destination_filepath,
            $resize_dimensions['width'],
            $resize_dimensions['height'],
            $starttime
        );
    }

    public static function get_resize_dimensions(
        $width,
        $height,
        $max_width,
        $max_height,
        $rotation = null,
        $crop = false,
        $follow_orientation = true
    ) {
        $rotate_for_dimensions = false;
        if (isset($rotation) && in_array(abs($rotation), [90, 270])) {
            $rotate_for_dimensions = true;
        }

        if ($rotate_for_dimensions) {
            [$width, $height] = [$height, $width];
        }

        if ($crop) {
            $x = 0;
            $y = 0;

            if ($width < $height && $follow_orientation) {
                [$max_width, $max_height] = [$max_height, $max_width];
            }

            $img_ratio = $width / $height;
            $dest_ratio = $max_width / $max_height;

            if ($dest_ratio > $img_ratio) {
                $destHeight = round($width * $max_height / $max_width);
                $y = round(($height - $destHeight) / 2);
                $height = $destHeight;
            } elseif ($dest_ratio < $img_ratio) {
                $destWidth = round($height * $max_width / $max_height);
                $x = round(($width - $destWidth) / 2);
                $width = $destWidth;
            }
        }

        $ratio_width = $width / $max_width;
        $ratio_height = $height / $max_height;
        $destination_width = $width;
        $destination_height = $height;

        // maximal size exceeded ?
        if ($ratio_width > 1 || $ratio_height > 1) {
            if ($ratio_width < $ratio_height) {
                $destination_width = round($width / $ratio_height);
                $destination_height = $max_height;
            } else {
                $destination_width = $max_width;
                $destination_height = round($height / $ratio_width);
            }
        }

        if ($rotate_for_dimensions) {
            [$destination_width, $destination_height] = [$destination_height, $destination_width];
        }

        $result = [
            'width' => $destination_width,
            'height' => $destination_height,
        ];

        if ($crop && ($x || $y)) {
            $result['crop'] = [
                'width' => $width,
                'height' => $height,
                'x' => $x,
                'y' => $y,
            ];
        }

        return $result;
    }

    public static function get_rotation_angle($source_filepath)
    {
        [$width, $height, $type] = getimagesize($source_filepath);
        if ($type != IMAGETYPE_JPEG) {
            return null;
        }

        if (! function_exists('exif_read_data')) {
            return null;
        }

        $rotation = 0;

        $exif = @exif_read_data($source_filepath);

        if (isset($exif['Orientation']) && preg_match('/^\s*(\d)/', (string) $exif['Orientation'], $matches)) {
            $orientation = $matches[1];
            if (in_array($orientation, [3, 4])) {
                $rotation = 180;
            } elseif (in_array($orientation, [5, 6])) {
                $rotation = 270;
            } elseif (in_array($orientation, [7, 8])) {
                $rotation = 90;
            }
        }

        return $rotation;
    }

    public static function get_rotation_code_from_angle($rotation_angle)
    {
        return match ($rotation_angle) {
            0 => 0,
            90 => 1,
            180 => 2,
            270 => 3,
            default => null,
        };
    }

    public static function get_rotation_angle_from_code($rotation_code)
    {
        return match ($rotation_code % 4) {
            0 => 0,
            1 => 90,
            2 => 180,
            3 => 270,
            default => null,
        };
    }

    /**
     * Returns a normalized convolution kernel for sharpening
     */
    public static function get_sharpen_matrix(
        $amount
    ) {
        // Amount should be in the range of 48-10
        $amount = round(abs(-48 + ($amount * 0.38)), 2);

        $matrix = [
            [-1,   -1,    -1],
            [-1, $amount, -1],
            [-1,   -1,    -1],
        ];

        $norm = array_sum(array_map('array_sum', $matrix));

        for ($i = 0; $i < 3; ++$i) {
            for ($j = 0; $j < 3; ++$j) {
                $matrix[$i][$j] /= $norm;
            }
        }

        return $matrix;
    }

    public static function is_imagick()
    {
        return extension_loaded('imagick') && class_exists('Imagick');
    }

    public static function is_ext_imagick()
    {
        global $conf;

        if (! function_exists('exec')) {
            return false;
        }

        if (empty($conf['ext_imagick_dir'])) {
            return false;
        }

        @exec($conf['ext_imagick_dir'] . 'convert -version', $returnarray);
        if (is_array($returnarray) && ! empty($returnarray[0]) && preg_match('/ImageMagick/i', $returnarray[0])) {
            if (preg_match('/Version: ImageMagick (\d+\.\d+\.\d+-?\d*)/', $returnarray[0], $match)) {
                self::$ext_imagick_version = $match[1];
            }

            return true;
        }

        return false;
    }

    public static function is_gd()
    {
        return function_exists('gd_info');
    }

    public static function is_vips()
    {
        return class_exists('image_vips');
    }

    public static function get_library($library = null, $extension = null)
    {
        global $conf;

        if ($library === null) {
            $library = $conf['graphics_library'];
        }

        // Choose image library
        switch (strtolower((string) $library)) {
            case 'auto':
            case 'vips':
                if (self::is_vips()) {
                    return 'Vips';
                }
                // no break
            case 'gd':
                if (self::is_gd()) {
                    return 'Gd';
                }
                // no break
            default:
                if ($library != 'auto') {
                    // Requested library not available. Try another library
                    return self::get_library(
                        'auto',
                        $extension
                    );
                }
        }

        return false;
    }

    public function destroy()
    {
        if (method_exists($this->image, 'destroy')) {
            return $this->image->destroy();
        }

        return true;
    }

    private function get_resize_result($destination_filepath, $width, $height, $time = null)
    {
        return [
            'source' => $this->source_filepath,
            'destination' => $destination_filepath,
            'width' => $width,
            'height' => $height,
            'size' => floor(filesize($destination_filepath) / 1024) . ' KB',
            'time' => $time ? number_format((get_moment() - $time) * 1000, 2, '.', ' ') . ' ms' : null,
            'library' => $this->library,
        ];
    }
}
