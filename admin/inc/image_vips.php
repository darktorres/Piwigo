<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                       Class for libvips library                       |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH.'admin/inc/imageInterface.php');

class image_vips implements imageInterface
{
    public Jcupitt\Vips\Image $image;

    public $quality = 75;

    public $source_filepath;

    public function __construct(
        $source_filepath
    ) {
        // putenv('VIPS_WARNING=0');
        $this->image = Jcupitt\Vips\Image::newFromFile(realpath($source_filepath), [
            'access' => 'sequential',
        ]);
        $this->source_filepath = realpath($source_filepath);
    }

    public function add_command($command, $params = null)
    {

    }

    #[\Override]
    public function get_width()
    {
        return $this->image->width;
    }

    #[\Override]
    public function get_height()
    {
        return $this->image->height;
    }

    #[\Override]
    public function crop($width, $height, $x, $y)
    {
        $this->image = $this->image->crop($x, $y, $width, $height);
        return true;
    }

    #[\Override]
    public function strip()
    {
        return true;
    }

    #[\Override]
    public function rotate($rotation)
    {
        $this->image = $this->image->rotate($rotation);
        return true;
    }

    #[\Override]
    public function set_compression_quality($quality)
    {
        $this->quality = $quality;
        return true;
    }

    #[\Override]
    public function resize($width, $height)
    {
        $this->image = Jcupitt\Vips\Image::thumbnail($this->source_filepath, $width, [
            'height' => $height,
        ]);
        return true;
    }

    #[\Override]
    public function sharpen($amount)
    {
        return true;
    }

    #[\Override]
    public function compose($overlay, $x, $y, $opacity)
    {
        return true;
    }

    #[\Override]
    public function write($destination_filepath)
    {
        $dest = pathinfo((string) $destination_filepath);
        $this->image->writeToFile(realpath($dest['dirname']) . '/' . $dest['basename']);
        return true;
    }
}

?>
