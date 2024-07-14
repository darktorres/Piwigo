<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Rate a picture by the current user.
 *
 * @return array as return by update_rating_score()
 */
function rate_picture(
    int $image_id,
    float $rate
): array|bool {
    global $conf, $user;

    if (! isset($rate) || ! $conf['rate'] || ! preg_match('/^\d+$/', (string) $rate) || ! in_array($rate, $conf['rate_items'])) {
        return false;
    }

    $user_anonymous = ! is_autorize_status(ACCESS_CLASSIC);

    if ($user_anonymous && ! $conf['rate_anonymous']) {
        return false;
    }

    $ip_components = explode('.', (string) $_SERVER['REMOTE_ADDR']);
    if (count($ip_components) > 3) {
        array_pop($ip_components);
    }

    $anonymous_id = implode('.', $ip_components);

    if ($user_anonymous) {
        $save_anonymous_id = pwg_get_cookie_var('anonymous_rater', $anonymous_id);

        if ($anonymous_id != $save_anonymous_id) { // client has changed his IP adress or he's trying to fool us
            $query = "SELECT element_id FROM rate WHERE user_id = {$user['id']} AND anonymous_id = '{$anonymous_id}';";
            $already_there = query2array($query, null, 'element_id');

            if ($already_there !== []) {
                $already_there_ = implode(',', $already_there);
                $query = "DELETE FROM rate WHERE user_id = {$user['id']} AND anonymous_id = '{$save_anonymous_id}' AND element_id IN ({$already_there_});";
                pwg_query($query);
            }

            $query = "UPDATE rate SET anonymous_id = '{$anonymous_id}' WHERE user_id = {$user['id']} AND anonymous_id = '{$save_anonymous_id}';";
            pwg_query($query);
        } // end client changed ip

        pwg_set_cookie_var('anonymous_rater', $anonymous_id);
    } // end anonymous user

    $query = "DELETE FROM rate WHERE element_id = {$image_id} AND user_id = {$user['id']}";
    if ($user_anonymous) {
        $query .= " AND anonymous_id = '{$anonymous_id}'";
    }

    pwg_query($query);
    $query = "INSERT INTO rate (user_id, anonymous_id, element_id, rate, date) VALUES ({$user['id']}, '{$anonymous_id}', {$image_id}, {$rate}, NOW());";
    pwg_query($query);

    return update_rating_score($image_id);
}

/**
 * Update images.rating_score field.
 * We use a bayesian average (http://en.wikipedia.org/wiki/Bayesian_average) with
 *  C = average number of rates per item
 *  m = global average rate (all rates)
 *
 * @param int|false $element_id if false applies to all
 * @return array (score, average, count) values are null if $element_id is false
 */
function update_rating_score(
    int|bool $element_id = false
): array {
    if (($alt_result = trigger_change('update_rating_score', false, $element_id)) !== false) {
        return $alt_result;
    }

    $query = 'SELECT element_id, COUNT(rate) AS rcount, SUM(rate) AS rsum FROM rate GROUP by element_id';

    $all_rates_count = 0;
    $all_rates_avg = 0;
    $item_ratecount_avg = 0;
    $by_item = [];

    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $all_rates_count += $row['rcount'];
        $all_rates_avg += $row['rsum'];
        $by_item[$row['element_id']] = $row;
    }

    if ($all_rates_count > 0) {
        $all_rates_avg /= $all_rates_count;
        $item_ratecount_avg = $all_rates_count / count($by_item);
    }

    $updates = [];
    foreach ($by_item as $id => $rate_summary) {
        $score = ($item_ratecount_avg * $all_rates_avg + $rate_summary['rsum']) / ($item_ratecount_avg + $rate_summary['rcount']);
        $score = round($score, 2);
        if ($id == $element_id) {
            $return = [
                'score' => $score,
                'average' => round($rate_summary['rsum'] / $rate_summary['rcount'], 2),
                'count' => $rate_summary['rcount'],
            ];
        }

        $updates[] = [
            'id' => $id,
            'rating_score' => $score,
        ];
    }

    mass_updates(
        'images',
        [
            'primary' => ['id'],
            'update' => ['rating_score'],
        ],
        $updates
    );

    //set to null all items with no rate
    if (! isset($by_item[$element_id])) {
        $query = 'SELECT id FROM images LEFT JOIN rate ON id = element_id WHERE element_id IS NULL AND rating_score IS NOT NULL';

        $to_update = query2array($query, null, 'id');

        if ($to_update !== []) {
            $to_update_ = implode(',', $to_update);
            $query = "UPDATE images SET rating_score = NULL WHERE id IN ({$to_update_})";
            pwg_query($query);
        }
    }

    return $return ?? [
        'score' => null,
        'average' => null,
        'count' => 0,
    ];
}
