<?php

declare(strict_types=1);

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
class PwgTemplateAdapter
{
    public function derivative(
        string|DerivativeParams $type,
        SrcImage $img
    ): DerivativeImage {
        return new DerivativeImage($type, $img);
    }

    public function derivative_url(
        string|DerivativeParams $type,
        SrcImage $img
    ): string {
        return DerivativeImage::url($type, $img);
    }
}
