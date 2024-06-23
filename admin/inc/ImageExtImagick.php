<?php

namespace Piwigo\admin\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |            Class for ImageMagick external installation                |
// +-----------------------------------------------------------------------+

class ImageExtImagick implements imageInterface
{
    public $imagickdir = '';

    public $width = '';

    public $height = '';

    public $commands = [];

    public function __construct(
        public $source_filepath
    ) {
        global $conf;
        $this->imagickdir = $conf['ext_imagick_dir'];

        if (str_starts_with((string) @$_SERVER['SCRIPT_FILENAME'], '/kunden/')) {  // 1and1
            @putenv('MAGICK_THREAD_LIMIT=1');
        }

        $command = $this->imagickdir . 'identify -format "%wx%h" "' . realpath($this->source_filepath) . '"';
        @exec($command, $returnarray);
        if (! is_array($returnarray) || empty($returnarray[0]) || ! preg_match(
            '/^(\d+)x(\d+)$/',
            $returnarray[0],
            $match
        )) {
            die("[External ImageMagick] Corrupt image\n" . var_export($returnarray, true));
        }

        $this->width = $match[1];
        $this->height = $match[2];
    }

    public function add_command($command, $params = null)
    {
        $this->commands[$command] = $params;
    }

    #[\Override]
    public function get_width()
    {
        return $this->width;
    }

    #[\Override]
    public function get_height()
    {
        return $this->height;
    }

    #[\Override]
    public function crop($width, $height, $x, $y)
    {
        $this->width = $width;
        $this->height = $height;

        $this->add_command('crop', $width . 'x' . $height . '+' . $x . '+' . $y);
        return true;
    }

    #[\Override]
    public function strip()
    {
        $this->add_command('strip');
        return true;
    }

    #[\Override]
    public function rotate($rotation)
    {
        if (empty($rotation)) {
            return true;
        }

        if ($rotation == 90 || $rotation == 270) {
            $tmp = $this->width;
            $this->width = $this->height;
            $this->height = $tmp;
        }

        $this->add_command('rotate', -$rotation);
        $this->add_command('orient', 'top-left');
        return true;
    }

    #[\Override]
    public function set_compression_quality($quality)
    {
        $this->add_command('quality', $quality);
        return true;
    }

    #[\Override]
    public function resize($width, $height)
    {
        $this->width = $width;
        $this->height = $height;

        $this->add_command('filter', 'Lanczos');
        $this->add_command('resize', $width . 'x' . $height . '!');
        return true;
    }

    #[\Override]
    public function sharpen($amount)
    {
        $m = Image::get_sharpen_matrix($amount);

        $param = 'convolve "' . count($m) . ':';
        foreach ($m as $line) {
            $param .= ' ';
            $param .= implode(',', $line);
        }

        $param .= '"';
        $this->add_command('morphology', $param);
        return true;
    }

    #[\Override]
    public function compose($overlay, $x, $y, $opacity)
    {
        $param = 'compose dissolve -define compose:args=' . $opacity;
        $param .= ' ' . escapeshellarg(realpath($overlay->image->source_filepath));
        $param .= ' -gravity NorthWest -geometry +' . $x . '+' . $y;
        $param .= ' -composite';
        $this->add_command($param);
        return true;
    }

    #[\Override]
    public function write($destination_filepath)
    {
        global $logger;

        $this->add_command('interlace', 'line'); // progressive rendering
        // use 4:2:2 chroma subsampling (reduce file size by 20-30% with "almost" no human perception)
        //
        // option deactivated for Piwigo 2.4.1, it doesn't work fo old versions
        // of ImageMagick, see bug:2672. To reactivate once we have a better way
        // to detect IM version and when we know which version supports this
        // option
        //
        if (version_compare(Image::$ext_imagick_version, '6.6') > 0) {
            $this->add_command('sampling-factor', '4:2:2');
        }

        $exec = $this->imagickdir . 'convert';
        $exec .= ' "' . realpath($this->source_filepath) . '"';

        foreach ($this->commands as $command => $params) {
            $exec .= ' -' . $command;
            if (! empty($params)) {
                $exec .= ' ' . $params;
            }
        }

        $dest = pathinfo((string) $destination_filepath);
        $exec .= ' "' . realpath($dest['dirname']) . '/' . $dest['basename'] . '" 2>&1';
        $logger->debug($exec);
        @exec($exec, $returnarray);

        if (is_array($returnarray) && ($returnarray !== [])) {
            $logger->error('', $returnarray);
            foreach ($returnarray as $line) {
                trigger_error($line, E_USER_WARNING);
            }
        }

        return is_array($returnarray);
    }
}
