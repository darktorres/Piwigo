<?php

declare(strict_types=1);

namespace Piwigo\admin\inc;

use Piwigo\inc\DerivativeImage;
use Piwigo\inc\SrcImage;
use function Piwigo\inc\add_event_handler;
use function Piwigo\inc\conf_get_param;
use function Piwigo\inc\conf_update_param;
use function Piwigo\inc\dbLayer\boolean_to_string;
use function Piwigo\inc\dbLayer\mass_updates;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_db_fetch_row;
use function Piwigo\inc\dbLayer\pwg_db_insert_id;
use function Piwigo\inc\dbLayer\pwg_db_real_escape_string;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\dbLayer\single_insert;
use function Piwigo\inc\dbLayer\single_update;
use function Piwigo\inc\get_extension;
use function Piwigo\inc\get_filename_wo_extension;
use function Piwigo\inc\get_name_from_file;
use function Piwigo\inc\l10n;
use function Piwigo\inc\original_to_representative;
use function Piwigo\inc\pwg_activity;
use function Piwigo\inc\secure_directory;
use function Piwigo\inc\set_make_full_url;
use function Piwigo\inc\trigger_change;
use function Piwigo\inc\trigger_notify;
use function Piwigo\inc\unset_make_full_url;
use const Piwigo\inc\IMG_MEDIUM;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

// add default event handler for image and thumbnail resize
// add_event_handler('upload_image_resize', 'pwg_image_resize');
// add_event_handler('upload_thumbnail_resize', 'pwg_image_resize');

function get_upload_form_config(): array
{
    // default configuration for upload
    return [
        'original_resize' => [
            'default' => false,
            'can_be_null' => false,
        ],

        'original_resize_maxwidth' => [
            'default' => 2000,
            'min' => 500,
            'max' => 20000,
            'pattern' => '/^\d+$/',
            'can_be_null' => false,
            'error_message' => l10n('The original maximum width must be a number between %d and %d'),
        ],

        'original_resize_maxheight' => [
            'default' => 2000,
            'min' => 300,
            'max' => 20000,
            'pattern' => '/^\d+$/',
            'can_be_null' => false,
            'error_message' => l10n('The original maximum height must be a number between %d and %d'),
        ],

        'original_resize_quality' => [
            'default' => 95,
            'min' => 50,
            'max' => 98,
            'pattern' => '/^\d+$/',
            'can_be_null' => false,
            'error_message' => l10n('The original image quality must be a number between %d and %d'),
        ],
    ];
}

function save_upload_form_config($data, array &$errors = [], array &$form_errors = []): bool
{
    if (! is_array($data) || $data === []) {
        return false;
    }

    $upload_form_config = get_upload_form_config();
    $updates = [];

    foreach ($data as $field => $value) {
        if (! isset($upload_form_config[$field])) {
            continue;
        }

        if (is_bool($upload_form_config[$field]['default'])) {
            $value = isset($value);
            $updates[] = [
                'param' => $field,
                'value' => boolean_to_string($value),
            ];
        } elseif ($upload_form_config[$field]['can_be_null'] && empty($value)) {
            $updates[] = [
                'param' => $field,
                'value' => 'false',
            ];
        } else {
            $min = $upload_form_config[$field]['min'];
            $max = $upload_form_config[$field]['max'];
            $pattern = $upload_form_config[$field]['pattern'];

            if (preg_match($pattern, (string) $value) && $value >= $min && $value <= $max) {
                $updates[] = [
                    'param' => $field,
                    'value' => $value,
                ];
            } else {
                $errors[] = sprintf(
                    $upload_form_config[$field]['error_message'],
                    $min,
                    $max
                );

                $form_errors[$field] = '[' . $min . ' .. ' . $max . ']';
            }
        }
    }

    if (count($errors) == 0) {
        mass_updates(
            CONFIG_TABLE,
            [
                'primary' => ['param'],
                'update' => ['value'],
            ],
            $updates
        );
        return true;
    }

    return false;
}

/**
 * @return int|mixed|string|void|null
 */
function add_uploaded_file(
    $source_filepath,
    $original_filename = null,
    $categories = null,
    $level = null,
    $image_id = null,
    $original_md5sum = null
) {
    // 1) move uploaded file to upload/2010/01/22/20100122003814-449ada00.jpg
    //
    // 2) keep/resize original
    //
    // 3) register in database

    // TODO
    // * check md5sum (already exists?)

    global $conf, $user;

    if ($original_filename !== null) {
        $original_filename = htmlspecialchars((string) $original_filename);
    }

    $md5sum = $original_md5sum ?? md5_file($source_filepath);

    $file_path = null;

    if (isset($image_id)) {
        // this photo already exists, we update it
        $query = '
SELECT
    path
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . $image_id . '
;';
        $result = pwg_query($query);
        while ($row = pwg_db_fetch_assoc($result)) {
            $file_path = $row['path'];
        }

        if (! isset($file_path)) {
            die('[' . __FUNCTION__ . '] this photo does not exist in the database');
        }

        // delete all physical files related to the photo (thumbnail, web site, HD)
        delete_element_files([$image_id]);
    } else {
        // this photo is new

        // current date
        [$dbnow] = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
        [$year, $month, $day] = preg_split('/[^\d]/', (string) $dbnow, 4);

        // upload directory hierarchy
        $upload_dir = sprintf(
            PHPWG_ROOT_PATH . $conf['upload_dir'] . '/%s/%s/%s',
            $year,
            $month,
            $day
        );

        // compute file path
        $date_string = preg_replace('/[^\d]/', '', $dbnow);
        $random_string = substr((string) $md5sum, 0, 8);
        $filename_wo_ext = $date_string . '-' . $random_string;
        $file_path = $upload_dir . '/' . $filename_wo_ext . '.';

        [$width, $height, $type] = getimagesize($source_filepath);

        if ($type == IMAGETYPE_PNG) {
            $file_path .= 'png';
        } elseif ($type == IMAGETYPE_GIF) {
            $file_path .= 'gif';
        } elseif ($type == IMAGETYPE_JPEG) {
            $file_path .= 'jpg';
        } elseif (isset($conf['upload_form_all_types']) && $conf['upload_form_all_types']) {
            $original_extension = strtolower(get_extension($original_filename));

            if (in_array($original_extension, $conf['file_ext'])) {
                $file_path .= $original_extension;
            } else {
                unlink($source_filepath);
                die('unexpected file type');
            }
        } else {
            unlink($source_filepath);
            die('forbidden file type');
        }

        prepare_directory($upload_dir);
    }

    if (is_uploaded_file($source_filepath)) {
        move_uploaded_file($source_filepath, $file_path);
    } else {
        rename($source_filepath, $file_path);
    }

    chmod($file_path, 0644);

    // handle the uploaded file type by potentially making a
    // pwg_representative file.
    $representative_ext = trigger_change('upload_file', null, $file_path);

    global $logger;
    $logger->info('Handling ' . $file_path . ' got ' . $representative_ext);

    // If it is set to either true (the file didn't need a
    // representative generated) or false (the generation of the
    // representative failed), set it to null because we have no
    // representative file.
    if (is_bool($representative_ext)) {
        $representative_ext = null;
    }

    if (Image::get_library() != 'gd' && $conf['original_resize']) {
        $need_resize = need_resize(
            $file_path,
            $conf['original_resize_maxwidth'],
            $conf['original_resize_maxheight']
        );
        if ($need_resize) {
            $img = new Image($file_path);

            $img->pwg_resize(
                $file_path,
                $conf['original_resize_maxwidth'],
                $conf['original_resize_maxheight'],
                $conf['original_resize_quality'],
                $conf['upload_form_automatic_rotation']
            );

            $img->destroy();
        }
    }

    // we need to save the rotation angle in the database to compute
    // width/height of "multisizes"
    $rotation_angle = Image::get_rotation_angle($file_path);
    $rotation = Image::get_rotation_code_from_angle($rotation_angle);

    $file_infos = pwg_image_infos($file_path);

    if (isset($image_id)) {
        $update = [
            'file' => pwg_db_real_escape_string($original_filename ?? basename((string) $file_path)),
            'filesize' => $file_infos['filesize'],
            'width' => $file_infos['width'],
            'height' => $file_infos['height'],
            'md5sum' => $md5sum,
            'added_by' => $user['id'],
            'rotation' => $rotation,
        ];

        if (isset($level)) {
            $update['level'] = $level;
        }

        single_update(
            IMAGES_TABLE,
            $update,
            [
                'id' => $image_id,
            ]
        );
    } else {
        // database registration
        $file = pwg_db_real_escape_string($original_filename ?? basename((string) $file_path));
        $insert = [
            'file' => $file,
            'name' => get_name_from_file($file),
            'date_available' => $dbnow,
            'path' => preg_replace('#^' . preg_quote(PHPWG_ROOT_PATH) . '#', '', $file_path),
            'filesize' => $file_infos['filesize'],
            'width' => $file_infos['width'],
            'height' => $file_infos['height'],
            'md5sum' => $md5sum,
            'added_by' => $user['id'],
            'rotation' => $rotation,
        ];

        if (isset($level)) {
            $insert['level'] = $level;
        }

        if (isset($representative_ext)) {
            $insert['representative_ext'] = $representative_ext;
        }

        single_insert(IMAGES_TABLE, $insert);

        $image_id = pwg_db_insert_id();
        pwg_activity('photo', $image_id, 'add');
    }

    if (! isset($conf['lounge_active'])) {
        conf_update_param('lounge_active', false, true);
    }

    if (! $conf['lounge_active']) {
        // check if we need to use the lounge from now
        [$nb_photos] = pwg_db_fetch_row(
            pwg_query('SELECT COUNT(*) FROM ' . IMAGES_TABLE . ';')
        );
        if ($nb_photos >= $conf['lounge_activate_threshold']) {
            conf_update_param('lounge_active', true, true);
        }
    }

    if (isset($categories) && count($categories) > 0) {
        if ($conf['lounge_active']) {
            fill_lounge([$image_id], $categories);
        } else {
            associate_images_to_categories([$image_id], $categories);
        }
    }

    // update metadata from the uploaded file (exif/iptc)
    if ($conf['use_exif'] && ! function_exists(
        'exif_read_data'
    )) {
        $conf['use_exif'] = false;
    }

    sync_metadata([$image_id]);

    if (! $conf['lounge_active']) {
        invalidate_user_cache();
    }

    // cache a derivative
    $query = '
SELECT
    id,
    path,
    representative_ext
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . $image_id . '
;';
    $image_infos = pwg_db_fetch_assoc(pwg_query($query));
    $src_image = new SrcImage($image_infos);

    set_make_full_url();
    // in case we are on uploadify.php, we have to replace the false path
    $derivative_url = preg_replace(
        '#admin/inc/i#',
        'i',
        DerivativeImage::url(IMG_MEDIUM, $src_image)
    );
    unset_make_full_url();

    $logger->info(__FUNCTION__ . ' : force cache generation, derivative_url = ' . $derivative_url);

    fetchRemote($derivative_url, $dest);

    trigger_notify('loc_end_add_uploaded_file', $image_infos);

    return $image_id;
}

/**
 * @return int|string|void
 */
function add_format($source_filepath, $format_ext, $format_of)
{
    // 1) find infos about the extended image
    //
    // 2) move uploaded file to upload/2022/05/16/pwg_format/20100122003814-449ada00.cr2
    //
    // 3) register in database

    if (! conf_get_param('enable_formats', false)) {
        die('[' . __FUNCTION__ . '] formats are disabled');
    }

    if (! in_array($format_ext, conf_get_param('format_ext', ['cr2']))) {
        die('[' . __FUNCTION__ . '] unexpected format extension "' . $format_ext . '" (authorized extensions: ' . implode(
            ', ',
            conf_get_param('format_ext', ['cr2'])
        ) . ')');
    }

    $query = '
SELECT
    path
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . $format_of . '
;';
    $images = query2array($query);

    if (! isset($images[0])) {
        die('[' . __FUNCTION__ . '] this photo does not exist in the database');
    }

    $format_path = dirname((string) $images[0]['path']) . '/pwg_format/';
    $format_path .= get_filename_wo_extension(basename((string) $images[0]['path']));
    $format_path .= '.' . $format_ext;

    prepare_directory(dirname($format_path));

    if (is_uploaded_file($source_filepath)) {
        move_uploaded_file($source_filepath, $format_path);
    } else {
        rename($source_filepath, $format_path);
    }

    chmod($format_path, 0644);

    $file_infos = pwg_image_infos($format_path);

    $insert = [
        'image_id' => $format_of,
        'ext' => $format_ext,
        'filesize' => $file_infos['filesize'],
    ];

    single_insert(IMAGE_FORMAT_TABLE, $insert);
    $format_id = pwg_db_insert_id();

    pwg_activity('photo', $format_of, 'edit', [
        'action' => 'add format',
        'format_ext' => $format_ext,
        'format_id' => $format_id,
    ]);

    $format_infos = $insert;
    $format_infos['format_id'] = $format_id;

    trigger_notify('loc_end_add_format', $format_infos);

    return $format_id;
}

add_event_handler('upload_file', 'upload_file_pdf');
function upload_file_pdf($representative_ext, $file_path): mixed
{
    global $logger, $conf;

    $logger->info(__FUNCTION__ . ', $file_path = ' . $file_path . ', $representative_ext = ' . $representative_ext);

    if (isset($representative_ext)) {
        return $representative_ext;
    }

    if (Image::get_library() != 'ext_imagick') {
        return $representative_ext;
    }

    if (strtolower(get_extension($file_path)) !== 'pdf') {
        return $representative_ext;
    }

    $ext = conf_get_param('pdf_representative_ext', 'jpg');
    $jpg_quality = conf_get_param('pdf_jpg_quality', 90);

    // move the uploaded file to pwg_representative sub-directory
    $representative_file_path = original_to_representative(
        $file_path,
        $ext
    );
    prepare_directory(dirname($representative_file_path));

    $exec = $conf['ext_imagick_dir'] . 'convert';
    if ($ext == 'jpg') {
        $exec .= ' -quality ' . $jpg_quality;
    }

    $exec .= ' "' . realpath($file_path) . '"[0]';
    $exec .= ' "' . $representative_file_path . '"';
    $exec .= ' 2>&1';
    exec($exec, $returnarray);

    // Return the extension (if successful) or false (if failed)
    if (file_exists($representative_file_path)) {
        $representative_ext = $ext;
    }

    return $representative_ext;
}

add_event_handler('upload_file', 'upload_file_tiff');
/**
 * @return mixed|string
 */
function upload_file_tiff($representative_ext, $file_path): mixed
{
    global $logger, $conf;

    $logger->info(__FUNCTION__ . ', $file_path = ' . $file_path . ', $representative_ext = ' . $representative_ext);

    if (isset($representative_ext)) {
        return $representative_ext;
    }

    if (Image::get_library() != 'ext_imagick') {
        return $representative_ext;
    }

    if (! in_array(strtolower(get_extension($file_path)), ['tif', 'tiff'])) {
        return $representative_ext;
    }

    // move the uploaded file to pwg_representative sub-directory
    $representative_file_path = dirname(
        (string) $file_path
    ) . '/pwg_representative/';
    $representative_file_path .= get_filename_wo_extension(basename((string) $file_path)) . '.';

    $representative_ext = $conf['tiff_representative_ext'];
    $representative_file_path .= $representative_ext;

    prepare_directory(dirname($representative_file_path));

    $exec = $conf['ext_imagick_dir'] . 'convert';

    if ($conf['tiff_representative_ext'] == 'jpg') {
        $exec .= ' -quality 98';
    }

    $exec .= ' "' . realpath($file_path) . '"';

    $dest = pathinfo($representative_file_path);
    $exec .= ' "' . realpath($dest['dirname']) . '/' . $dest['basename'] . '"';

    $exec .= ' 2>&1';
    exec($exec, $returnarray);

    // sometimes ImageMagick creates file-0.jpg (full size) + file-1.jpg
    // (thumbnail). I don't know how to avoid it.
    $representative_file_abspath = realpath(
        $dest['dirname']
    ) . '/' . $dest['basename'];
    if (! file_exists($representative_file_abspath)) {
        $first_file_abspath = preg_replace(
            '/\.' . $representative_ext . '$/',
            '-0.' . $representative_ext,
            $representative_file_abspath
        );

        if (file_exists($first_file_abspath)) {
            rename($first_file_abspath, $representative_file_abspath);
        }
    }

    return get_extension($representative_file_abspath);
}

add_event_handler('upload_file', 'upload_file_video');
/**
 * @return mixed|string|null
 */
function upload_file_video($representative_ext, $file_path): mixed
{
    global $logger, $conf;

    $logger->info(__FUNCTION__ . ', $file_path = ' . $file_path . ', $representative_ext = ' . $representative_ext);

    if (isset($representative_ext)) {
        return $representative_ext;
    }

    $ffmpeg_video_exts = [ // extensions tested with FFmpeg
        'wmv', 'mov', 'mkv', 'mp4', 'mpg', 'flv', 'asf', 'xvid', 'divx', 'mpeg',
        'avi', 'rm', 'm4v', 'ogg', 'ogv', 'webm', 'webmv',
    ];

    if (! in_array(strtolower(get_extension($file_path)), $ffmpeg_video_exts)) {
        return $representative_ext;
    }

    $representative_file_path = dirname((string) $file_path) . '/pwg_representative/';
    $representative_file_path .= get_filename_wo_extension(basename((string) $file_path)) . '.';

    $representative_ext = 'jpg';
    $representative_file_path .= $representative_ext;

    prepare_directory(dirname($representative_file_path));

    // Get duration of video and determine time of poster
    exec(
        'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1' . sprintf(
            " '%s'",
            $file_path
        ),
        $O,
        $S
    );

    if (! empty($O[0])) {
        $second = min(floor($O[0] * 10) / 10, 2);
    } else {
        $second = 0; // Safest position of the poster
    }

    $logger->info(__FUNCTION__ . ', Poster at ' . $second . 's');

    // Generate poster, see https://trac.ffmpeg.org/wiki/Seeking
    $ffmpeg = $conf['ffmpeg_dir'] . 'ffmpeg';
    $ffmpeg .= ' -ss ' . $second;  // Fast seeking
    $ffmpeg .= ' -i "' . $file_path . '"'; // Video file
    $ffmpeg .= ' -frames:v 1';  // Extract one frame
    $ffmpeg .= ' "' . $representative_file_path . '"'; // Output file

    exec($ffmpeg . ' 2>&1', $FO, $FS);
    if (! empty($FO[0])) {
        $logger->debug(__FUNCTION__ . ', Tried ' . $ffmpeg);
        $logger->debug($FO[0]);
    }

    // Did we generate the file ?
    if (! file_exists($representative_file_path)) {
        return null;
    }

    return $representative_ext;
}

function prepare_directory($directory): void
{
    if (! is_dir($directory)) {
        if (str_starts_with(PHP_OS, 'WIN')) {
            $directory = str_replace('/', DIRECTORY_SEPARATOR, $directory);
        }

        umask(0000);
        $recursive = true;
        if (! mkdir($directory, 0777, $recursive)) {
            die('[prepare_directory] cannot create directory "' . $directory . '"');
        }
    }

    if (! is_writable($directory)) {
        // last chance to make the directory writable
        chmod($directory, 0777);

        if (! is_writable($directory)) {
            die('[prepare_directory] directory "' . $directory . '" has no write access');
        }
    }

    secure_directory($directory);
}

function need_resize($image_filepath, $max_width, $max_height): bool
{
    // TODO : the resize check should take the orientation into account. If a
    // rotation must be applied to the resized photo, then we should test
    // invert width and height.
    [$width, $height] = getimagesize($image_filepath);
    return $width > $max_width || $height > $max_height;
}

function pwg_image_infos($path): array
{
    [$width, $height] = getimagesize($path);
    $filesize = floor(filesize($path) / 1024);

    return [
        'width' => $width,
        'height' => $height,
        'filesize' => $filesize,
    ];
}

function is_valid_image_extension($extension): array
{
    global $conf;

    if (isset($conf['upload_form_all_types']) && $conf['upload_form_all_types']) {
        $extensions = $conf['file_ext'];
    } else {
        $extensions = $conf['picture_ext'];
    }

    return array_unique(array_map('strtolower', $extensions));
}

function file_upload_error_message($error_code): string
{
    return match ($error_code) {
        UPLOAD_ERR_INI_SIZE => sprintf(
            l10n('The uploaded file exceeds the upload_max_filesize directive in php.ini: %sB'),
            get_ini_size('upload_max_filesize', false)
        ),
        UPLOAD_ERR_FORM_SIZE => l10n(
            'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'
        ),
        UPLOAD_ERR_PARTIAL => l10n('The uploaded file was only partially uploaded'),
        UPLOAD_ERR_NO_FILE => l10n('No file was uploaded'),
        UPLOAD_ERR_NO_TMP_DIR => l10n('Missing a temporary folder'),
        UPLOAD_ERR_CANT_WRITE => l10n('Failed to write file to disk'),
        UPLOAD_ERR_EXTENSION => l10n('File upload stopped by extension'),
        default => l10n('Unknown upload error'),
    };
}

function get_ini_size($ini_key, bool $in_bytes = true): mixed
{
    $size = ini_get($ini_key);

    if ($in_bytes) {
        $size = convert_shorthand_notation_to_bytes($size);
    }

    return $size;
}

function convert_shorthand_notation_to_bytes($value): mixed
{
    $suffix = substr((string) $value, -1);
    $multiply_by = null;

    if ($suffix === 'K') {
        $multiply_by = 1024;
    } elseif ($suffix === 'M') {
        $multiply_by = 1024 * 1024;
    } elseif ($suffix === 'G') {
        $multiply_by = 1024 * 1024 * 1024;
    }

    if (isset($multiply_by)) {
        $value = substr((string) $value, 0, -1);
        $value *= $multiply_by;
    }

    return $value;
}

function add_upload_error($upload_id, $error_message): void
{
    $_SESSION['uploads_error'][$upload_id][] = $error_message;
}

function ready_for_upload_message(): ?string
{
    global $conf;

    $relative_dir = preg_replace('#^' . PHPWG_ROOT_PATH . '#', '', $conf['upload_dir']);

    if (! is_dir($conf['upload_dir'])) {
        if (! is_writable(dirname((string) $conf['upload_dir']))) {
            return sprintf(
                l10n('Create the "%s" directory at the root of your Piwigo installation'),
                $relative_dir
            );
        }
    } elseif (! is_writable($conf['upload_dir'])) {
        chmod($conf['upload_dir'], 0777);
        if (! is_writable($conf['upload_dir'])) {
            return sprintf(
                l10n('Give write access (chmod 777) to "%s" directory at the root of your Piwigo installation'),
                $relative_dir
            );
        }
    }

    return null;
}
