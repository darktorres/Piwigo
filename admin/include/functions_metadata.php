<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'include/functions_metadata.inc.php';

/**
 * Returns IPTC metadata to sync from a file, depending on IPTC mapping.
 * @toto : clean code (factorize foreach)
 */
function get_sync_iptc_data(
    string $file
): array {
    global $conf;

    $map = $conf['use_iptc_mapping'];

    $iptc = get_iptc_data($file, $map);

    foreach ($iptc as $pwg_key => $value) {
        if (in_array($pwg_key, ['date_creation', 'date_available']) && preg_match('/(\d{4})(\d{2})(\d{2})/', (string) $value, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
            if (! checkdate($month, $day, $year)) {
                // we suppose the year is correct
                $month = 1;
                $day = 1;
            }

            $iptc[$pwg_key] = $year . '-' . $month . '-' . $day;
        }
    }

    if (isset($iptc['keywords'])) {
        $iptc['keywords'] = metadata_normalize_keywords_string($iptc['keywords']);
    }

    foreach (array_keys($iptc) as $pwg_key) {
        $iptc[$pwg_key] = addslashes((string) $iptc[$pwg_key]);
    }

    return $iptc;
}

/**
 * Returns EXIF metadata to sync from a file, depending on EXIF mapping.
 */
function get_sync_exif_data(
    string $file
): array {
    global $conf;

    $exif = get_exif_data($file, $conf['use_exif_mapping']);

    foreach ($exif as $pwg_key => $value) {
        if (in_array($pwg_key, ['date_creation', 'date_available'])) {
            if (is_numeric($value) && (int) $value == $value) {
                // UNIX timestamp
                $exif[$pwg_key] = DateTime::createFromFormat('U', (string) $value)->format('Y-m-d H:i:s');

                if ($exif[$pwg_key] === '0000-00-00' || ! validateDate($exif[$pwg_key])) {
                    $exif[$pwg_key] = null;
                }
            } elseif (preg_match('/^(\d{4}).(\d{2}).(\d{2}).(\d{2}).(\d{2}).(\d{2})/', (string) $value, $matches)) {
                // YYYY-MM-DDTHH:MM:SS
                $exif[$pwg_key] = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
                if ($exif[$pwg_key] === '0000-00-00 00:00:00' || ! validateDate($exif[$pwg_key])) {
                    $exif[$pwg_key] = null;
                }
            } elseif (preg_match('/^(\d{4}).(\d{2}).(\d{2})/', (string) $value, $matches)) {
                // YYYY-MM-DD
                $exif[$pwg_key] = $matches[1] . '-' . $matches[2] . '-' . $matches[3];

                if ($exif[$pwg_key] === '0000-00-00' || ! validateDate($exif[$pwg_key], 'Y-m-d')) {
                    $exif[$pwg_key] = null;
                }
            } else {
                unset($exif[$pwg_key]);
                continue;
            }
        }

        if (in_array($pwg_key, ['keywords', 'tags'])) {
            $exif[$pwg_key] = metadata_normalize_keywords_string($exif[$pwg_key]);
        }

        if ($exif[$pwg_key] !== null && ! is_numeric($exif[$pwg_key])) {
            $exif[$pwg_key] = addslashes((string) $exif[$pwg_key]);
        }
    }

    return $exif;
}

function validateDate($date, $format = 'Y-m-d H:i:s'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

/**
 * Get all potential file metadata fields, including IPTC and EXIF.
 *
 * @return string[]
 */
function get_sync_metadata_attributes(): array
{
    global $conf;

    $update_fields = ['filesize', 'width', 'height'];

    if ($conf['use_exif']) {
        $update_fields =
          array_merge(
              $update_fields,
              array_keys($conf['use_exif_mapping']),
              ['latitude', 'longitude']
          );
    }

    if ($conf['use_iptc']) {
        $update_fields =
          array_merge(
              $update_fields,
              array_keys($conf['use_iptc_mapping'])
          );
    }

    return array_unique($update_fields);
}

/**
 * Get all metadata of a file.
 *
 * @param array $infos - (path[, representative_ext])
 * @return array|bool - includes data provided in $infos
 */
function get_sync_metadata(
    array $infos
): array|bool {
    global $conf;
    $file = PHPWG_ROOT_PATH . $infos['path'];
    $fs = filesize($file);

    if ($fs === false) {
        return false;
    }

    $infos['filesize'] = floor($fs / 1024);

    $is_tiff = false;

    if (isset($infos['representative_ext'])) {
        if ($image_size = getimagesize($file)) {
            $type = $image_size[2];

            if ($type == IMAGETYPE_TIFF_MM || $type == IMAGETYPE_TIFF_II) {
                // in case of TIFF files, we want to use the original file and not
                // the representative for EXIF/IPTC, but we need the representative
                // for width/height (to compute the multiple size dimensions)
                $is_tiff = true;
            }
        }

        $file = original_to_representative($file, $infos['representative_ext']);
    }

    if (function_exists('mime_content_type') && in_array(mime_content_type($file), ['image/svg+xml', 'image/svg'])) {
        $xml = file_get_contents($file);

        $xmlget = simplexml_load_string($xml);
        $xmlattributes = $xmlget->attributes();
        $width = (int) $xmlattributes->width;
        $height = (int) $xmlattributes->height;
        $vb = (string) $xmlattributes->viewBox;

        if (isset($width) && $width != 0) {
            $infos['width'] = $width;
        } elseif (isset($vb)) {
            $infos['width'] = explode(' ', $vb)[2];
        }

        if (isset($height) && $height != 0) {
            $infos['height'] = $height;
        } elseif (isset($vb)) {
            $infos['height'] = explode(' ', $vb)[3];
        }
    }

    if ($image_size = getimagesize($file)) {
        $infos['width'] = $image_size[0];
        $infos['height'] = $image_size[1];
    }

    if ($is_tiff) {
        // back to original file
        $file = PHPWG_ROOT_PATH . $infos['path'];
    }

    if ($conf['use_exif']) {
        $exif = get_sync_exif_data($file);
        $infos = array_merge($infos, $exif);
    }

    if ($conf['use_iptc']) {
        $iptc = get_sync_iptc_data($file);
        $infos = array_merge($infos, $iptc);
    }

    foreach (['name', 'author'] as $single_line_field) {
        if (isset($infos[$single_line_field])) {
            foreach (["\r\n", "\n"] as $to_replace_string) {
                $infos[$single_line_field] = str_replace($to_replace_string, ' ', $infos[$single_line_field]);
            }
        }
    }

    return $infos;
}

/**
 * Sync all metadata of a list of images.
 * Metadata is fetched from original files and saved in database.
 *
 * @param int[] $ids
 */
function sync_metadata(
    array $ids
): void {
    global $conf;

    if (! defined('CURRENT_DATE')) {
        define('CURRENT_DATE', date('Y-m-d'));
    }

    $datas = [];
    $tags_of = [];

    $wrapped_ids = wordwrap(implode(', ', $ids), 160, "\n");
    $query = <<<SQL
        SELECT id, path, representative_ext
        FROM images
        WHERE id IN ({$wrapped_ids});
        SQL;

    $result = pwg_query($query);
    while ($data = pwg_db_fetch_assoc($result)) {
        $data = get_sync_metadata($data);
        if ($data === false) {
            continue;
        }

        // print_r($data);
        $id = $data['id'];
        foreach (['keywords', 'tags'] as $key) {
            if (isset($data[$key])) {
                if (! isset($tags_of[$id])) {
                    $tags_of[$id] = [];
                }

                foreach (explode(',', (string) $data[$key]) as $tag_name) {
                    $tags_of[$id][] = tag_id_from_tag_name($tag_name);
                }
            }
        }

        $data['date_metadata_update'] = CURRENT_DATE;

        $datas[] = $data;
    }

    if ($datas !== []) {
        $update_fields = get_sync_metadata_attributes();
        $update_fields[] = 'date_metadata_update';

        $update_fields = array_diff(
            $update_fields,
            ['tags', 'keywords']
        );

        mass_updates(
            'images',
            [
                'primary' => ['id'],
                'update' => $update_fields,
            ],
            $datas,
            MASS_UPDATES_SKIP_EMPTY
        );
    }

    set_tags_of($tags_of);
}

/**
 * Returns an array associating element id (images.id) with its complete
 * path in the filesystem
 */
function get_filelist(
    string $category_id = '',
    int $site_id = 1,
    bool $recursive = false,
    bool $only_new = false
): array {
    // filling $cat_ids : all categories required
    $cat_ids = [];

    $query = <<<SQL
        SELECT id
        FROM categories
        WHERE site_id = {$site_id}
            AND dir IS NOT NULL

        SQL;

    if (is_numeric($category_id)) {
        if ($recursive) {
            $regex_operator = DB_REGEX_OPERATOR;
            $query .= <<<SQL
                AND uppercats {$regex_operator} '(^|,){$category_id}(,|$)'

                SQL;
        } else {
            $query .= <<<SQL
                AND id = {$category_id}

                SQL;
        }
    }

    $query .= ';';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $cat_ids[] = $row['id'];
    }

    if (count($cat_ids) == 0) {
        return [];
    }

    $imploded_cat_ids = implode(',', $cat_ids);
    $query = <<<SQL
        SELECT id, path, representative_ext
        FROM images
        WHERE storage_category_id IN ({$imploded_cat_ids})

        SQL;

    if ($only_new) {
        $query .= <<<SQL
            AND date_metadata_update IS NULL

            SQL;
    }

    $query .= ';';
    return query2array($query, 'id');
}

/**
 * Returns the list of keywords (future tags) correctly separated with
 * commas. Other separators are converted into commas.
 */
function metadata_normalize_keywords_string(
    string $keywords_string
): string {
    global $conf;

    $keywords_string = preg_replace($conf['metadata_keyword_separator_regex'], ',', $keywords_string);
    // new lines are always considered as keyword separators
    $keywords_string = str_replace(["\r\n", "\n"], ',', $keywords_string);
    $keywords_string = preg_replace('/,+/', ',', $keywords_string);
    $keywords_string = preg_replace('/^,+|,+$/', '', (string) $keywords_string);

    $keywords_string = implode(
        ',',
        array_unique(
            explode(
                ',',
                (string) $keywords_string
            )
        )
    );

    return $keywords_string;
}
