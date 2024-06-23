<?php

namespace Piwigo\admin\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                   Class for Imagick extension                         |
// +-----------------------------------------------------------------------+

class ImageImagick implements imageInterface
{
    public $image;

    public function __construct(
        $source_filepath
    ) {
        // A bug cause that Imagick class can not be extended
        $this->image = new \Imagick($source_filepath);
    }

    #[\Override]
    public function get_width()
    {
        return $this->image->getImageWidth();
    }

    #[\Override]
    public function get_height()
    {
        return $this->image->getImageHeight();
    }

    #[\Override]
    public function set_compression_quality($quality)
    {
        return $this->image->setImageCompressionQuality($quality);
    }

    #[\Override]
    public function crop($width, $height, $x, $y)
    {
        return $this->image->cropImage($width, $height, $x, $y);
    }

    #[\Override]
    public function strip()
    {
        return $this->image->stripImage();
    }

    #[\Override]
    public function rotate($rotation)
    {
        $this->image->rotateImage(new \ImagickPixel(), -$rotation);
        $this->image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
        return true;
    }

    #[\Override]
    public function resize($width, $height)
    {
        $this->image->setInterlaceScheme(\Imagick::INTERLACE_LINE);

        // TODO need to explain this condition
        if ($this->get_width() % 2 == 0
            && $this->get_height() % 2 == 0
            && $this->get_width() > 3 * $width) {
            $this->image->scaleImage($this->get_width() / 2, $this->get_height() / 2);
        }

        return $this->image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 0.9);
    }

    #[\Override]
    public function sharpen($amount)
    {
        $m = Image::get_sharpen_matrix($amount);
        return $this->image->convolveImage($m);
    }

    #[\Override]
    public function compose($overlay, $x, $y, $opacity)
    {
        $ioverlay = $overlay->image->image;
        /*if ($ioverlay->getImageAlphaChannel() !== Imagick::ALPHACHANNEL_OPAQUE)
        {
          // Force the image to have an alpha channel
          $ioverlay->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        }*/

        global $dirty_trick_xrepeat;
        if (! isset($dirty_trick_xrepeat) && $opacity < 100) {// NOTE: Using setImageOpacity will destroy current alpha channels!
            $ioverlay->evaluateImage(
                \Imagick::EVALUATE_MULTIPLY,
                $opacity / 100,
                \Imagick::CHANNEL_ALPHA
            );
            $dirty_trick_xrepeat = true;
        }

        return $this->image->compositeImage($ioverlay, \Imagick::COMPOSITE_DISSOLVE, $x, $y);
    }

    #[\Override]
    public function write($destination_filepath)
    {
        // use 4:2:2 chroma subsampling (reduce file size by 20-30% with "almost" no human perception)
        $this->image->setSamplingFactors(
            [2, 1]
        );
        return $this->image->writeImage($destination_filepath);
    }
}
