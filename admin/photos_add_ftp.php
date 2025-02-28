<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\inc\functions;

if (!defined('PHOTOS_ADD_BASE_URL'))
{
  die ("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->assign(
  'FTP_HELP_CONTENT',
  functions::load_language(
    'help/photos_add_ftp.html',
    '',
    array('return'=>true)
    )
  );

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+
$template->assign('ADMIN_PAGE_TITLE', functions::l10n('Upload Photos'));

$template->assign_var_from_handle('ADMIN_CONTENT', 'photos_add');
?>