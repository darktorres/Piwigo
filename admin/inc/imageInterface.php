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
// |                           Image Interface                             |
// +-----------------------------------------------------------------------+

// Define all necessary methods for image class
interface imageInterface
{
    public function get_width();

    public function get_height();

    public function set_compression_quality(int $quality);

    public function crop(int $width, int $height, int $x, int $y);

    public function strip();

    public function rotate(int $rotation);

    public function resize(float $width, float $height);

    public function sharpen(int $amount);

    public function compose(int $overlay, int $x, int $y, int $opacity);

    public function write(string $destination_filepath);
}
