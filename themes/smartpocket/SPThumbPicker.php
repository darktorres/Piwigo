<?php

declare(strict_types=1);

namespace Piwigo\themes\smartpocket;

class SPThumbPicker
{
    public array $candidates;

    public DerivativeParams $default;

    public string $height;

    public function init(
        string $height
    ): void {
        $this->candidates = [];
        foreach (ImageStdParams::get_defined_type_map() as $params) {
            if ($params->max_height() < $height || $params->sizing->max_crop) {
                continue;
            }

            if ($params->max_height() > 3 * $height) {
                break;
            }

            $this->candidates[] = $params;
        }

        $this->default = ImageStdParams::get_custom($height * 3, $height, 1, 0, $height);
        $this->height = $height;
    }

    public function pick(
        SrcImage $src_image,
        string $height
    ): DerivativeImage {
        $ok = false;
        foreach ($this->candidates as $candidate) {
            $deriv = new DerivativeImage($candidate, $src_image);
            $size = $deriv->get_size();
            if ($size[1] >= $height - 2) {
                $ok = true;
                break;
            }
        }

        if (! $ok) {
            $deriv = new DerivativeImage($this->default, $src_image);
        }

        return $deriv;
    }
}
