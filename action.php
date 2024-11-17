<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', './');
session_cache_limiter('public');
require_once PHPWG_ROOT_PATH . 'include/common.inc.php';

// Check Access and exit when user status is not ok
check_status(ACCESS_GUEST);

function guess_mime_type(
    string $ext
): string {
    return match (strtolower($ext)) {
        'jpe', 'jpeg', 'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'tiff', 'tif' => 'image/tiff',
        'txt' => 'text/plain',
        'html', 'htm' => 'text/html',
        'xml' => 'text/xml',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'ogg' => 'application/ogg',
        default => 'application/octet-stream',
    };
}

function do_error(
    int $code,
    string $str
): void {
    set_status_header($code);
    echo $str;
    exit();
}

if ($conf['enable_formats'] && isset($_GET['format'])) {
    check_input_parameter('format', $_GET, false, PATTERN_ID);

    $query = <<<SQL
        SELECT *
        FROM image_format
        WHERE format_id = {$_GET['format']};
        SQL;
    $formats = query2array($query);

    if (count($formats) == 0) {
        do_error(400, 'Invalid request - format');
    }

    $format = $formats[0];

    $_GET['id'] = $format['image_id'];
    $_GET['part'] = 'f'; // "f" for "format"
}

if (! isset($_GET['id']) || ! is_numeric($_GET['id']) || ! isset($_GET['part']) || ! in_array($_GET['part'], ['e', 'r', 'f'])) {
    do_error(400, 'Invalid request - id/part');
}

$query = <<<SQL
    SELECT *
    FROM images
    WHERE id = {$_GET['id']};
    SQL;

$element_info = pwg_db_fetch_assoc(pwg_query($query));
if ($element_info === false || $element_info === [] || $element_info === null) {
    do_error(404, 'Requested id not found');
}

// special download action for admins
$is_admin_download = false;
if (is_admin() && isset($_GET['pwg_token']) && get_pwg_token() == $_GET['pwg_token']) {
    $is_admin_download = true;
    $user['enabled_high'] = true;
}

$src_image = new SrcImage($element_info);

// $filter['visible_categories'] and $filter['visible_images']
// are not used because it's not necessary (filter <> restriction)
$sql_condition = get_sql_condition_FandF(
    [
        'forbidden_categories' => 'category_id',
        'forbidden_images' => 'image_id',
    ],
    ' AND'
);

$query = <<<SQL
    SELECT id
    FROM categories
    INNER JOIN image_category ON category_id = id
    WHERE image_id = {$_GET['id']}
    {$sql_condition}
    LIMIT 1;
    SQL;
if (! $is_admin_download && pwg_db_num_rows(pwg_query($query)) < 1) {
    do_error(401, 'Access denied');
}

require_once PHPWG_ROOT_PATH . 'include/functions_picture.inc.php';
$file = '';
switch ($_GET['part']) {
    case 'e':
        if ($src_image->is_original() && ! $user['enabled_high']) {// we have a photo and the user has no access to HD
            $deriv = new DerivativeImage(IMG_XXLARGE, $src_image);
            if (! $deriv->same_as_source()) {
                do_error(401, 'Access denied e');
            }
        }

        $file = get_element_path($element_info);
        break;
    case 'r':
        $file = original_to_representative(get_element_path($element_info), $element_info['representative_ext']);
        break;
    case 'f':
        $file = original_to_format(get_element_path($element_info), $format['ext']);
        $element_info['file'] = get_filename_wo_extension($element_info['file']) . '.' . $format['ext'];
        break;
}

if ($file === '' || $file === '0') {
    do_error(404, 'Requested file not found');
}

if ($_GET['part'] == 'e') {
    pwg_log($_GET['id'], 'high');
} elseif ($_GET['part'] == 'r') {
    pwg_log($_GET['id'], 'other');
} elseif ($_GET['part'] == 'f') {
    pwg_log($_GET['id'], 'high', $format['format_id']);
}

trigger_notify('loc_action_before_http_headers');

$http_headers = [];

$ctype = null;
if (! url_is_remote($file)) {
    if (! is_readable($file)) {
        do_error(404, "Requested file not found - {$file}");
    }

    $http_headers[] = 'Content-Length: ' . filesize($file);
    if (function_exists('mime_content_type')) {
        $ctype = mime_content_type($file);
    }

    $gmt_mtime = gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT';
    $http_headers[] = 'Last-Modified: ' . $gmt_mtime;

    // following lines would indicate how the client should handle the cache
    /* $max_age=300;
    $http_headers[] = 'Expires: '.gmdate('D, d M Y H:i:s', time()+$max_age).' GMT';
    // HTTP/1.1 only
    $http_headers[] = 'Cache-Control: private, must-revalidate, max-age='.$max_age;*/

    if ($_GET['part'] != 'f' && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        set_status_header(304);
        foreach ($http_headers as $header) {
            header($header);
        }

        exit();
    }
}

if (! isset($ctype)) { // give it a guess
    $ctype = guess_mime_type(get_extension($file));
}

$http_headers[] = 'Content-Type: ' . $ctype;

if (isset($_GET['download'])) {
    $http_headers[] = 'Content-Disposition: attachment; filename="' . htmlspecialchars_decode((string) $element_info['file']) . '";';
    $http_headers[] = 'Content-Transfer-Encoding: binary';
} else {
    $http_headers[] = 'Content-Disposition: inline; filename="'
              . basename($file) . '";';
}

foreach ($http_headers as $header) {
    header($header);
}

// Looking at the safe_mode configuration for execution time
if (ini_get('safe_mode') == 0) {
    set_time_limit(0);
}

// Without clean and flush there may be some image download problems, or image can be corrupted after download
if (ob_get_length() !== false) {
    ob_flush();
}

flush();

readfile($file);
