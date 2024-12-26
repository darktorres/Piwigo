<?php

declare(strict_types=1);

namespace Piwigo\admin\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                       Class for libvips library                       |
// +-----------------------------------------------------------------------+

class image_vips implements imageInterface
{
    public Jcupitt\Vips\Image $image;

    public $quality = 75;

    public $source_filepath;

    public function __construct(
        string $source_filepath
    ) {
        // putenv('VIPS_WARNING=0');
        $this->image = Jcupitt\Vips\Image::newFromFile(realpath($source_filepath), [
            'access' => 'sequential',
        ]);
        $this->source_filepath = realpath($source_filepath);
    }

    public function add_command(
        string $command,
        ?string $params = null
    ): void {}

    #[\Override]
    public function get_width(): int
    {
        return $this->image->width;
    }

    #[\Override]
    public function get_height(): int
    {
        return $this->image->height;
    }

    #[\Override]
    public function crop(
        int|float $width,
        int|float $height,
        int|float $x,
        int|float $y
    ): bool {
        $this->image = $this->image->crop($x, $y, $width, $height);
        return true;
    }

    #[\Override]
    public function strip(): bool
    {
        return true;
    }

    #[\Override]
    public function rotate(
        int $rotation
    ): bool {
        $this->image = $this->image->rotate($rotation);
        return true;
    }

    #[\Override]
    public function set_compression_quality(
        int $quality
    ): bool {
        $this->quality = $quality;
        return true;
    }

    #[\Override]
    public function resize(
        float $width,
        float $height
    ): bool {
        $this->image = Jcupitt\Vips\Image::thumbnail($this->source_filepath, $width, [
            'height' => $height,
        ]);
        return true;
    }

    #[\Override]
    public function sharpen(
        int $amount
    ): bool {
        return true;
    }

    #[\Override]
    public function compose(
        int $overlay,
        int $x,
        int $y,
        int $opacity
    ): bool {
        return true;
    }

    #[\Override]
    public function write(
        string $destination_filepath
    ): bool {
        $dest = pathinfo($destination_filepath);
        $this->image->writeToFile(realpath($dest['dirname']) . '/' . $dest['basename']);
        return true;
    }
}
