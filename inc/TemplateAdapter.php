<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * This class contains basic functions that can be called directly from the
 * templates in the form $pwg->l10n('edit')
 */
class TemplateAdapter
{
    /**
     * @deprecated use "translate" modifier
     */
    public function l10n(?string $text)
    {
        return l10n($text);
    }

    /**
     * @deprecated use "translate_dec" modifier
     */
    public function l10n_dec($s, $p, $v): string
    {
        return l10n_dec($s, $p, $v);
    }

    /**
     * @deprecated use "translate" or "sprintf" modifier
     */
    public function sprintf(...$args): string
    {
        return sprintf(...$args);
    }

    /**
     * @param string $type
     * @param SrcImage $img
     */
    public function derivative(
        $type,
        $img
    ): \Piwigo\inc\DerivativeImage {
        return new DerivativeImage($type, $img);
    }

    /**
     * @param string $type
     * @param array $img
     * @return string
     */
    public function derivative_url(
        $type,
        $img
    ) {
        return DerivativeImage::url($type, $img);
    }
}
