<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

function get_search_id_pattern($candidate): ?string
{
    $clause_pattern = null;
    if (preg_match('/^psk-\d{8}-[a-z0-9]{10}$/i', (string) $candidate)) {
        $clause_pattern = "search_uuid = '%s'";
    } elseif (preg_match('/^\d+$/', (string) $candidate)) {
        $clause_pattern = 'id = %u';
    }

    return $clause_pattern;
}

function get_search_info($candidate)
{
    global $page;

    // $candidate might be a search.id or a search_uuid
    $clause_pattern = get_search_id_pattern($candidate);

    if ($clause_pattern === null || $clause_pattern === '' || $clause_pattern === '0') {
        die('Invalid search identifier');
    }

    $clause_pattern_ = sprintf($clause_pattern, $candidate);
    $query = "SELECT * FROM search WHERE {$clause_pattern_};";
    $searches = query2array($query);

    if ($searches !== []) {
        // we don't want spies to be able to see the search rules of any prior search (performed
        // by any user). We don't want them to be try index.php?/search/123 then index.php?/search/124
        // and so on. That's why we have implemented search_uuid with random characters.
        //
        // We also don't want to break old search urls with only the numeric id, so we only break if
        // there is no uuid.
        //
        // We also don't want to die if we're in the API.
        if (script_basename() !== 'ws' && $clause_pattern === 'id = %u' && isset($searches[0]['search_uuid'])) {
            fatal_error('this search is not reachable with its id, need the search_uuid instead');
        }

        if (isset($page['section']) && $page['section'] == 'search') {
            // to be used later in pwg_log
            $page['search_id'] = $searches[0]['id'];
        }

        return $searches[0];
    }

    return null;
}

/**
 * Returns search rules stored into a serialized array in "search"
 * table. Each search rules set is numericaly identified.
 */
function get_search_array(
    string $search_id
): array {
    global $user;

    $search = get_search_info($search_id);

    if (empty($search)) {
        bad_request('this search identifier does not exist');
    }

    return unserialize($search['rules']);
}

/**
 * Returns the SQL clause for a search.
 * Transforms the array returned by get_search_array() into SQL sub-query.
 */
function get_sql_search_clause(
    array $search
): array {
    // SQL where clauses are stored in $clauses array during query
    // construction
    $clauses = [];

    foreach (['file', 'name', 'comment', 'author'] as $textfield) {
        if (isset($search['fields'][$textfield])) {
            $local_clauses = [];
            foreach ($search['fields'][$textfield]['words'] as $word) {
                $local_clauses[] = $textfield === 'author' ? $textfield . "='" . $word . "'" : $textfield . " LIKE '%" . $word . "%'";
            }

            if ($local_clauses !== []) {
                // adds brackets around where clauses
                $local_clauses = prepend_append_array_items($local_clauses, '(', ')');

                $clauses[] = implode(
                    ' ' . $search['fields'][$textfield]['mode'] . ' ',
                    $local_clauses
                );
            }
        }
    }

    if (isset($search['fields']['allwords']) && ! empty($search['fields']['allwords']['words']) && count($search['fields']['allwords']['fields']) > 0) {
        // 1) we search in regular fields (ie, the ones in the piwigo_images table)
        $fields = ['file', 'name', 'comment', 'author'];

        if (isset($search['fields']['allwords']['fields']) && count($search['fields']['allwords']['fields']) > 0) {
            $fields = array_intersect($fields, $search['fields']['allwords']['fields']);
        }

        $cat_fields_dictionnary = [
            'cat-title' => 'name',
            'cat-desc' => 'comment',
        ];
        $cat_fields = array_intersect(array_keys($cat_fields_dictionnary), $search['fields']['allwords']['fields']);

        // in the OR mode, request bust be :
        // ((field1 LIKE '%word1%' OR field2 LIKE '%word1%')
        // OR (field1 LIKE '%word2%' OR field2 LIKE '%word2%'))
        //
        // in the AND mode :
        // ((field1 LIKE '%word1%' OR field2 LIKE '%word1%')
        // AND (field1 LIKE '%word2%' OR field2 LIKE '%word2%'))
        $word_clauses = [];
        $cat_ids_by_word = [];
        $tag_ids_by_word = [];
        foreach ($search['fields']['allwords']['words'] as $word) {
            $field_clauses = [];
            foreach ($fields as $field) {
                $field_clauses[] = $field . " LIKE '%" . $word . "%'";
            }

            if ($cat_fields !== []) {
                $cat_word_clauses = [];
                $cat_field_clauses = [];
                foreach ($cat_fields as $cat_field) {
                    $cat_field_clauses[] = $cat_fields_dictionnary[$cat_field] . " LIKE '%" . $word . "%'";
                }

                // adds brackets around where clauses
                $cat_word_clauses[] = implode(' OR ', $cat_field_clauses);

                $cat_word_clauses_ = implode(' OR ', $cat_word_clauses);
                $query = "SELECT id FROM categories WHERE {$cat_word_clauses_};";
                $cat_ids = query2array($query, null, 'id');
                $cat_ids_by_word[$word] = $cat_ids;
                if ($cat_ids !== []) {
                    $cat_ids_ = implode(',', $cat_ids);
                    $query = "SELECT image_id FROM image_category WHERE category_id IN ({$cat_ids_});";
                    $cat_image_ids = query2array($query, null, 'image_id');

                    if ($cat_image_ids !== []) {
                        $field_clauses[] = 'id IN (' . implode(',', $cat_image_ids) . ')';
                    }
                }
            }

            // search_in_tags
            if (in_array('tags', $search['fields']['allwords']['fields'])) {
                $query = "SELECT id FROM tags WHERE name LIKE '%{$word}%';";
                $tag_ids = query2array($query, null, 'id');
                $tag_ids_by_word[$word] = $tag_ids;
                if ($tag_ids !== []) {
                    $tag_ids_ = implode(',', $tag_ids);
                    $query = "SELECT image_id FROM image_tag WHERE tag_id IN ({$tag_ids_});";
                    $tag_image_ids = query2array($query, null, 'image_id');

                    if ($tag_image_ids !== []) {
                        $field_clauses[] = 'id IN (' . implode(',', $tag_image_ids) . ')';
                    }
                }
            }

            if ($field_clauses !== []) {
                // adds brackets around where clauses
                $word_clauses[] = implode(
                    "\n          OR ",
                    $field_clauses
                );
            }
        }

        if ($word_clauses !== []) {
            array_walk(
                $word_clauses,
                function (string &$s): void { $s = '(' . $s . ')'; }
            );
        }

        // make sure the "mode" is either OR or AND
        if (! in_array($search['fields']['allwords']['mode'], ['OR', 'AND'])) {
            $search['fields']['allwords']['mode'] = 'AND';
        }

        $clauses[] = "\n         " . implode(
            "\n         " . $search['fields']['allwords']['mode'] . "\n         ",
            $word_clauses
        );

        if ($cat_ids_by_word !== []) {
            $matching_cat_ids = null;
            foreach ($cat_ids_by_word as $cat_ids) {
                $matching_cat_ids = $matching_cat_ids === null ? $cat_ids : array_merge($matching_cat_ids, $cat_ids);
            }

            $matching_cat_ids = array_unique($matching_cat_ids);
        }

        if ($tag_ids_by_word !== []) {
            $matching_tag_ids = null;
            foreach ($tag_ids_by_word as $tag_ids) {
                $matching_tag_ids = $matching_tag_ids === null ? $tag_ids : array_merge($matching_tag_ids, $tag_ids);
            }

            $matching_tag_ids = array_unique($matching_tag_ids);
        }
    }

    foreach (['date_available', 'date_creation'] as $datefield) {
        if (isset($search['fields'][$datefield])) {
            $clauses[] = $datefield . " = '" . $search['fields'][$datefield]['date'] . "'";
        }

        foreach (['after', 'before'] as $suffix) {
            $key = $datefield . '-' . $suffix;

            if (isset($search['fields'][$key])) {
                $clauses[] = $datefield .
                  ($suffix === 'after' ? ' >' : ' <') .
                  ($search['fields'][$key]['inc'] ? '=' : '') .
                  " '" . $search['fields'][$key]['date'] . "'";
            }
        }
    }

    if (! empty($search['fields']['date_posted'])) {
        $options = [
            '24h' => '24 HOUR',
            '7d' => '7 DAY',
            '30d' => '30 DAY',
            '3m' => '3 MONTH',
            '6m' => '6 MONTH',
            '1y' => '1 YEAR',
        ];

        if (isset($options[$search['fields']['date_posted']])) {
            $clauses[] = 'date_available > SUBDATE(NOW(), INTERVAL ' . $options[$search['fields']['date_posted']] . ')';
        } elseif (preg_match('/^y(\d+)$/', (string) $search['fields']['date_posted'], $matches)) {
            // that is for y2023 = all photos posted in 2022
            $clauses[] = 'YEAR(date_available) = ' . $matches[1];
        }
    }

    if (! empty($search['fields']['filetypes'])) {
        $filetypes_clauses = [];
        foreach ($search['fields']['filetypes'] as $ext) {
            $filetypes_clauses[] = "path LIKE '%." . $ext . "'";
        }

        $clauses[] = implode(' OR ', $filetypes_clauses);
    }

    if (! empty($search['fields']['added_by'])) {
        $clauses[] = 'added_by IN (' . implode(',', $search['fields']['added_by']) . ')';
    }

    if (isset($search['fields']['cat']) && ! empty($search['fields']['cat']['words'])) {
        if ($search['fields']['cat']['sub_inc']) {
            // searching all the categories id of sub-categories
            $cat_ids = get_subcat_ids($search['fields']['cat']['words']);
        } else {
            $cat_ids = $search['fields']['cat']['words'];
        }

        $local_clause = 'category_id IN (' . implode(',', $cat_ids) . ')';
        $clauses[] = $local_clause;
    }

    // adds brackets around where clauses
    $clauses = prepend_append_array_items($clauses, '(', ')');

    $where_separator =
      implode(
          "\n    " . $search['mode'] . ' ',
          $clauses
      );

    $search_clause = $where_separator;

    return [
        $search_clause,
        isset($matching_cat_ids) ? array_values($matching_cat_ids) : null,
        isset($matching_tag_ids) ? array_values($matching_tag_ids) : null,
    ];
}

/**
 * Returns the list of items corresponding to the advanced search array.
 *
 * @param string $images_where optional additional restriction on images table
 */
function get_regular_search_results(
    array $search,
    string $images_where = ''
): array {
    global $conf, $logger;

    $logger->debug(__FUNCTION__, $search);

    $has_filters_filled = false;

    $forbidden = get_sql_condition_FandF(
        [
            'forbidden_categories' => 'category_id',
            'visible_categories' => 'category_id',
            'visible_images' => 'id',
        ],
        "\n  AND"
    );

    $items = [];
    $tag_items = [];

    if (isset($search['fields']['tags'])) {
        if (! empty($search['fields']['tags']['words'])) {
            $has_filters_filled = true;
        }

        $tag_items = get_image_ids_for_tags(
            $search['fields']['tags']['words'],
            $search['fields']['tags']['mode']
        );

        $logger->debug(__FUNCTION__ . ' ' . count($tag_items) . ' items in $tag_items');
    }

    [$search_clause, $matching_cat_ids, $matching_tag_ids] = get_sql_search_clause($search);

    if ($search_clause !== '' && $search_clause !== '0') {
        $has_filters_filled = true;

        $query = "SELECT DISTINCT(id) FROM images i INNER JOIN image_category AS ic ON id = ic.image_id LEFT JOIN image_tag AS it ON id = it.image_id WHERE {$search_clause}";
        if ($images_where !== '' && $images_where !== '0') {
            $query .= "\n AND {$images_where}";
        }

        $query .= "{$forbidden} {$conf['order_by']};";
        $items = array_from_query($query, 'id');

        $logger->debug(__FUNCTION__ . ' ' . count($items) . ' items in $items');
    }

    if ($tag_items !== []) {
        switch ($search['mode']) {
            case 'AND':
                if (($search_clause === '' || $search_clause === '0') && ! isset($search_in_tags_items)) {
                    $items = $tag_items;
                } else {
                    $items = array_values(array_intersect($items, $tag_items));
                }

                break;
            case 'OR':
                $items = array_values(
                    array_unique(
                        array_merge(
                            $items,
                            $tag_items
                        )
                    )
                );
                break;
        }
    }

    return [
        'items' => $items,
        'search_details' => [
            'matching_cat_ids' => $matching_cat_ids,
            'matching_tag_ids' => $matching_tag_ids,
            'has_filters_filled' => $has_filters_filled,
        ],
    ];
}

define('QST_QUOTED', 0x01);
define('QST_NOT', 0x02);
define('QST_OR', 0x04);
define('QST_WILDCARD_BEGIN', 0x08);
define('QST_WILDCARD_END', 0x10);
define('QST_WILDCARD', QST_WILDCARD_BEGIN | QST_WILDCARD_END);
define('QST_BREAK', 0x20);

/**
 * A search scope applies to a single token and restricts the search to a subset of searchable fields.
 */
class QSearchScope
{
    public function __construct(
        public string $id,
        public array $aliases,
        public bool $nullable = false,
        public bool $is_text = true
    ) {}

    public function parse(
        QSingleToken $token
    ): bool {
        return ! (! $this->nullable && strlen($token->term) == 0);
    }

    public function process_char(
        string &$ch,
        string &$crt_token
    ): bool {
        return false;
    }
}

class QNumericRangeScope extends QSearchScope
{
    public function __construct(
        string $id,
        array $aliases,
        bool $nullable = false,
        private readonly float $epsilon = 0
    ) {
        parent::__construct($id, $aliases, $nullable, false);
    }

    #[\Override]
    public function parse(
        QSingleToken $token
    ): bool {
        $str = $token->term;
        $strict = [0, 0];
        $range_requested = true;
        if (($pos = strpos($str, '..')) !== false) {
            $range = [substr($str, 0, $pos), substr($str, $pos + 2)];
        } elseif (@$str[0] === '>') {// ratio:>1
            $range = [substr($str, 1), ''];
            $strict[0] = 1;
        } elseif (@$str[0] === '<') { // size:<5mp
            $range = ['', substr($str, 1)];
            $strict[1] = 1;
        } elseif ((($token->modifier & QST_WILDCARD_BEGIN) !== 0)) {
            $range = ['', $str];
        } elseif ((($token->modifier & QST_WILDCARD_END) !== 0)) {
            $range = [$str, ''];
        } else {
            $range = [$str, $str];
            $range_requested = false;
        }

        foreach ($range as $i => &$val) {
            if (preg_match('#^(-?[0-9.]+)/([0-9.]+)$#i', $val, $matches)) {
                $val = floatval($matches[1] / $matches[2]);
            } elseif (preg_match('/^(-?[0-9.]+)([km])?/i', $val, $matches)) {
                $val = floatval($matches[1]);
                if (isset($matches[2])) {
                    $mult = $matches[2] === 'k' || $matches[2] === 'K' ? 1000 : 1000000;

                    $val *= $mult;
                    if ($i && ! $range_requested) {// round up the upper limit if possible - e.g 6k goes up to 6999, but 6.12k goes only up to 6129
                        if (($dot_pos = strpos($matches[1], '.')) !== false) {
                            $requested_precision = strlen($matches[1]) - $dot_pos - 1;
                            $mult /= 10 ** $requested_precision;
                        }

                        if ($mult > 1) {
                            $val += $mult - 1;
                        }
                    }
                }
            } else {
                $val = '';
            }

            if (is_numeric($val)) {
                if (($i ^ $strict[$i]) !== 0) {
                    $val += $this->epsilon;
                } else {
                    $val -= $this->epsilon;
                }
            }
        }

        if (! $this->nullable && $range[0] === '' && $range[1] === '') {
            return false;
        }

        $token->scope_data = [
            'range' => $range,
            'strict' => $strict,
        ];
        return true;
    }

    public function get_sql(
        string $field,
        QSingleToken $token
    ): string {
        $clauses = [];
        if ($token->scope_data['range'][0] !== '') {
            $clauses[] = $field . ' >' . ($token->scope_data['strict'][0] ? '' : '=') . $token->scope_data['range'][0] . ' ';
        }

        if ($token->scope_data['range'][1] !== '') {
            $clauses[] = $field . ' <' . ($token->scope_data['strict'][1] ? '' : '=') . $token->scope_data['range'][1] . ' ';
        }

        if ($clauses === []) {
            if (($token->modifier & QST_WILDCARD) !== 0) {
                return $field . ' IS NOT NULL';
            }

            return $field . ' IS NULL';
        }

        return '(' . implode(' AND ', $clauses) . ')';
    }
}

class QDateRangeScope extends QSearchScope
{
    public function __construct(
        string $id,
        array $aliases,
        bool $nullable = false
    ) {
        parent::__construct($id, $aliases, $nullable, false);
    }

    #[\Override]
    public function parse(
        QSingleToken $token
    ): bool {
        $str = $token->term;
        $strict = [0, 0];
        if (($pos = strpos($str, '..')) !== false) {
            $range = [substr($str, 0, $pos), substr($str, $pos + 2)];
        } elseif (@$str[0] === '>') {
            $range = [substr($str, 1), ''];
            $strict[0] = 1;
        } elseif (@$str[0] === '<') {
            $range = ['', substr($str, 1)];
            $strict[1] = 1;
        } elseif ((($token->modifier & QST_WILDCARD_BEGIN) !== 0)) {
            $range = ['', $str];
        } elseif ((($token->modifier & QST_WILDCARD_END) !== 0)) {
            $range = [$str, ''];
        } else {
            $range = [$str, $str];
        }

        foreach ($range as $i => &$val) {
            if (preg_match('/(\d{4})-?((?:1[0-2])|(?:0?[1-9]))?-?((?:(?:[1-3]\d)|(?:0?[1-9])))?/', $val, $matches)) {
                array_shift($matches);
                if (! isset($matches[1])) {
                    $matches[1] = (($i ^ $strict[$i]) !== 0) ? 12 : 1;
                }

                if (! isset($matches[2])) {
                    $matches[2] = (($i ^ $strict[$i]) !== 0) ? 31 : 1;
                }

                $val = implode('-', $matches);
                if (($i ^ $strict[$i]) !== 0) {
                    $val .= ' 23:59:59';
                }
            } elseif (strlen($val) !== 0) {
                return false;
            }
        }

        if (! $this->nullable && $range[0] === '' && $range[1] === '') {
            return false;
        }

        $token->scope_data = $range;
        return true;
    }

    public function get_sql(
        string $field,
        QSingleToken $token
    ): string {
        $clauses = [];
        if ($token->scope_data[0] != '') {
            $clauses[] = $field . " >= '" . $token->scope_data[0] . "'";
        }

        if ($token->scope_data[1] != '') {
            $clauses[] = $field . " <= '" . $token->scope_data[1] . "'";
        }

        if ($clauses === []) {
            if (($token->modifier & QST_WILDCARD) !== 0) {
                return $field . ' IS NOT NULL';
            }

            return $field . ' IS NULL';
        }

        return '(' . implode(' AND ', $clauses) . ')';
    }
}

/**
 * Analyzes and splits the quick/query search query $q into tokens.
 * q='john bill' => 2 tokens 'john' 'bill'
 * Special characters for MySql full text search (+,<,>,~) appear in the token modifiers.
 * The query can contain a phrase: 'Pierre "New York"' will return 'pierre' qnd 'new york'.
 *
 * @param string $term
 */

/** Represents a single word or quoted phrase to be searched.*/
class QSingleToken implements \Stringable
{
    public bool $is_single = true; /* the actual word/phrase string*/

    public array $variants = [];

    public array $scope_data;

    public int $idx;

    public function __construct(
        public string $term,
        public int $modifier,
        public QSearchScope|null $scope
    ) {}

    #[\Override]
    public function __toString(): string
    {
        $s = '';
        if (isset($this->scope)) {
            $s .= $this->scope->id . ':';
        }

        if (($this->modifier & QST_WILDCARD_BEGIN) !== 0) {
            $s .= '*';
        }

        if (($this->modifier & QST_QUOTED) !== 0) {
            $s .= '"';
        }

        $s .= $this->term;
        if (($this->modifier & QST_QUOTED) !== 0) {
            $s .= '"';
        }

        if (($this->modifier & QST_WILDCARD_END) !== 0) {
            $s .= '*';
        }

        return $s;
    }
}

/** Represents an expression of several words or sub expressions to be searched.*/
class QMultiToken implements \Stringable
{
    public bool $is_single = false;

    public $modifier;

    public $tokens = []; // the actual array of QSingleToken or QMultiToken

    #[\Override]
    public function __toString(): string
    {
        $s = '';
        $counter = count($this->tokens);
        for ($i = 0; $i < $counter; $i++) {
            $modifier = $this->tokens[$i]->modifier;
            if ($i !== 0) {
                $s .= ' ';
            }

            if (($modifier & QST_OR) !== 0) {
                $s .= 'OR ';
            }

            if (($modifier & QST_NOT) !== 0) {
                $s .= 'NOT ';
            }

            if (! ($this->tokens[$i]->is_single)) {
                $s .= '(';
                $s .= $this->tokens[$i];
                $s .= ')';
            } else {
                $s .= $this->tokens[$i];
            }
        }

        return $s;
    }

    /**
     * Parses the input query string by tokenizing the input, generating the modifiers (and/or/not/quotation/wildcards...).
     * Recursivity occurs when parsing ()
     * @param string $q the actual query to be parsed
     * @param int $qi the character index in $q where to start parsing
     * @param int $level the depth from root in the tree (number of opened and unclosed opening brackets)
     */
    protected function parse_expression(
        string $q,
        int &$qi,
        int $level,
        QExpression $root
    ): void {
        $crt_token = '';
        $crt_modifier = 0;
        $crt_scope = null;

        for ($stop = false; ! $stop && $qi < strlen($q); $qi++) {
            $ch = $q[$qi];
            if (($crt_modifier & QST_QUOTED) == 0) {
                switch ($ch) {
                    case '(':
                        if (strlen((string) $crt_token) !== 0) {
                            $this->push($crt_token, $crt_modifier, $crt_scope);
                        }

                        $sub = new self();
                        $qi++;
                        $sub->parse_expression($q, $qi, $level + 1, $root);
                        $sub->modifier = $crt_modifier;
                        if (isset($crt_scope) && $crt_scope->is_text) {
                            $sub->apply_scope($crt_scope); // eg. 'tag:(John OR Bill)'
                        }

                        $this->tokens[] = $sub;
                        $crt_modifier = 0;
                        $crt_scope = null;
                        break;
                    case ')':
                        if ($level > 0) {
                            $stop = true;
                        }

                        break;
                    case ':':
                        $scope = @$root->scopes[strtolower((string) $crt_token)];
                        if (! isset($scope) || isset($crt_scope)) { // white space
                            $this->push($crt_token, $crt_modifier, $crt_scope);
                        } else {
                            $crt_token = '';
                            $crt_scope = $scope;
                        }

                        break;
                    case '"':
                        if (strlen((string) $crt_token) !== 0) {
                            $this->push($crt_token, $crt_modifier, $crt_scope);
                        }

                        $crt_modifier |= QST_QUOTED;
                        break;
                    case '-':
                        if (strlen((string) $crt_token) || isset($crt_scope)) {
                            $crt_token .= $ch;
                        } else {
                            $crt_modifier |= QST_NOT;
                        }

                        break;
                    case '*':
                        if (strlen((string) $crt_token) !== 0) {
                            $crt_token .= $ch;
                        } // wildcard end later
                        else {
                            $crt_modifier |= QST_WILDCARD_BEGIN;
                        }

                        break;
                    case '.':
                        if (isset($crt_scope) && ! $crt_scope->is_text) {
                            $crt_token .= $ch;
                            break;
                        }

                        if (strlen((string) $crt_token) && preg_match('/\d/', substr((string) $crt_token, -1))
                          && $qi + 1 < strlen($q) && preg_match('/\d/', $q[$qi + 1])) {// dot between digits is not a separator e.g. F2.8
                            $crt_token .= $ch;
                            break;
                        }
                        // else white space go on..
                        // no break
                    default:
                        if (! $crt_scope || ! $crt_scope->process_char($ch, $crt_token)) {
                            if (str_contains(' ,.;!?', $ch)) { // white space
                                $this->push($crt_token, $crt_modifier, $crt_scope);
                            } else {
                                $crt_token .= $ch;
                            }
                        }

                        break;
                }
            } elseif ($ch === '"') {
                // quoted
                if ($qi + 1 < strlen($q) && $q[$qi + 1] === '*') {
                    $crt_modifier |= QST_WILDCARD_END;
                    $qi++;
                }

                $this->push($crt_token, $crt_modifier, $crt_scope);
            } else {
                $crt_token .= $ch;
            }
        }

        $this->push($crt_token, $crt_modifier, $crt_scope);
        $counter = count($this->tokens);

        for ($i = 0; $i < $counter; $i++) {
            $token = $this->tokens[$i];
            $remove = false;
            if ($token->is_single) {
                if (($token->modifier & QST_QUOTED) == 0
                  && str_ends_with($token->term, '*')) {
                    $token->term = rtrim($token->term, '*');
                    $token->modifier |= QST_WILDCARD_END;
                }

                if (! isset($token->scope)
                  && ($token->modifier & (QST_QUOTED | QST_WILDCARD)) == 0) {
                    if (strtolower($token->term) === 'not') {
                        if ($i + 1 < count($this->tokens)) {
                            $this->tokens[$i + 1]->modifier |= QST_NOT;
                        }

                        $token->term = '';
                    }

                    if (strtolower($token->term) === 'or') {
                        if ($i + 1 < count($this->tokens)) {
                            $this->tokens[$i + 1]->modifier |= QST_OR;
                        }

                        $token->term = '';
                    }

                    if (strtolower($token->term) === 'and') {
                        $token->term = '';
                    }
                }

                if (! strlen($token->term)
                  && (! isset($token->scope) || ! $token->scope->nullable)) {
                    $remove = true;
                }

                if (isset($token->scope)
                  && ! $token->scope->parse($token)) {
                    $remove = true;
                }
            } elseif (count($token->tokens) === 0) {
                $remove = true;
            }

            if ($remove) {
                array_splice($this->tokens, $i, 1);
                if ($i < count($this->tokens) && $this->tokens[$i]->is_single) {
                    $this->tokens[$i]->modifier |= QST_BREAK;
                }

                $i--;
            }
        }

        if ($level > 0 && count($this->tokens) && $this->tokens[0]->is_single) {
            $this->tokens[0]->modifier |= QST_BREAK;
        }
    }

    /* because evaluations occur left to right, we ensure that 'a OR b c d' is interpreted as 'a OR (b c d)'*/
    protected function check_operator_priority(): void
    {
        $counter = count($this->tokens);
        for ($i = 0; $i < $counter; $i++) {
            if (! $this->tokens[$i]->is_single) {
                $this->tokens[$i]->check_operator_priority();
            }

            if ($i == 1) {
                $crt_prio = $this->priority($this->tokens[$i]->modifier);
            }

            if ($i <= 1) {
                continue;
            }

            $prio = $this->priority($this->tokens[$i]->modifier);
            if ($prio > $crt_prio) {// e.g. 'a OR b c d' i=2, operator(c)=AND -> prio(AND) > prio(OR) = operator(b)
                $term_count = 2; // at least b and c to be regrouped
                for ($j = $i + 1; $j < count($this->tokens); $j++) {
                    if ($this->priority($this->tokens[$j]->modifier) >= $prio) {
                        $term_count++;
                    } // also take d
                    else {
                        break;
                    }
                }

                $i--; // move pointer to b
                // crate sub expression (b c d)
                $sub = new self();
                $sub->tokens = array_splice($this->tokens, $i, $term_count);

                // rewrite ourseleves as a (b c d)
                array_splice($this->tokens, $i, 0, [$sub]);
                $sub->modifier = $sub->tokens[0]->modifier & QST_OR;
                $sub->tokens[0]->modifier &= ~QST_OR;

                $sub->check_operator_priority();
            } else {
                $crt_prio = $prio;
            }
        }
    }

    private function push(
        string &$token,
        int &$modifier,
        QSearchScope|null &$scope
    ): void {
        if (strlen($token) || (isset($scope) && $scope->nullable)) {
            if (isset($scope)) {
                $modifier |= QST_BREAK;
            }

            $this->tokens[] = new QSingleToken($token, $modifier, $scope);
        }

        $token = '';
        $modifier = 0;
        $scope = null;
    }

    /**
     * Applies recursively a search scope to all sub single tokens. We allow 'tag:(John Bill)' but we cannot evaluate
     * scopes on expressions so we rewrite as '(tag:John tag:Bill)'
     */
    private function apply_scope(
        QSearchScope $scope
    ): void {
        $counter = count($this->tokens);
        for ($i = 0; $i < $counter; $i++) {
            if ($this->tokens[$i]->is_single) {
                if (! isset($this->tokens[$i]->scope)) {
                    $this->tokens[$i]->scope = $scope;
                }
            } else {
                $this->tokens[$i]->apply_scope($scope);
            }
        }
    }

    private function priority(
        string $modifier
    ): int {
        return $modifier & QST_OR ? 0 : 1;
    }
}

class QExpression extends QMultiToken
{
    public $scopes = [];

    public $stokens = [];

    public $stoken_modifiers = [];

    public function __construct(
        string $q,
        array $scopes
    ) {
        foreach ($scopes as $scope) {
            $this->scopes[$scope->id] = $scope;
            foreach ($scope->aliases as $alias) {
                $this->scopes[strtolower($alias)] = $scope;
            }
        }

        $i = 0;
        $this->parse_expression($q, $i, 0, $this);
        //manipulate the tree so that 'a OR b c' is the same as 'b c OR a'
        $this->check_operator_priority();
        $this->build_single_tokens($this, 0);
    }

    private function build_single_tokens(
        QMultiToken $expr,
        int $this_is_not
    ): void {
        $counter = count($expr->tokens);
        for ($i = 0; $i < $counter; $i++) {
            $token = $expr->tokens[$i];
            $crt_is_not = ($token->modifier ^ $this_is_not) & QST_NOT; // no negation OR double negation -> no negation;

            if ($token->is_single) {
                $token->idx = count($this->stokens);
                $this->stokens[] = $token;

                $modifier = $token->modifier;
                if ($crt_is_not !== 0) {
                    $modifier |= QST_NOT;
                } else {
                    $modifier &= ~QST_NOT;
                }

                $this->stoken_modifiers[] = $modifier;
            } else {
                $this->build_single_tokens($token, $crt_is_not);
            }
        }
    }
}

/**
 * Structure of results being filled from different tables
 */
class QResults
{
    public $all_tags;

    public $tag_ids;

    public $tag_iids;

    public $all_cats;

    public $cat_ids;

    public $cat_iids;

    public $images_iids;

    public $iids;
}

function qsearch_get_text_token_search_sql(
    QSingleToken $token,
    array $fields
): array {
    global $page;

    $clauses = [];
    $variants = array_merge([$token->term], $token->variants);
    $fts = [];
    foreach ($variants as $variant) {
        $use_ft = mb_strlen((string) $variant) > 3;
        if (($token->modifier & QST_WILDCARD_BEGIN) !== 0) {
            $use_ft = false;
        }

        if (($token->modifier & (QST_QUOTED | QST_WILDCARD_END) === (QST_QUOTED | QST_WILDCARD_END)) !== 0) {
            $use_ft = false;
        }

        if ($use_ft) {
            $max = max(array_map(
                mb_strlen(...),
                preg_split('/[' . preg_quote('-\'!"#$%&()*+,./:;<=>?@[\]^`{|}~', '/') . ']+/', (string) $variant)
            ));
            if ($max < 4) {
                $use_ft = false;
            }
        }

        if (! $use_ft) {// odd term or too short for full text search; fallback to regex but unfortunately this is diacritic/accent sensitive
            if (! isset($page['use_regexp_ICU'])) {
                // Prior to MySQL 8.0.4, MySQL used the Henry Spencer regular expression library to support
                // regular expression operations, rather than International Components for Unicode (ICU)
                $page['use_regexp_ICU'] = false;
                $db_version = pwg_get_db_version();
                if (! preg_match('/mariadb/i', $db_version) && version_compare($db_version, '8.0.4', '>')) {
                    $page['use_regexp_ICU'] = true;
                }
            }

            $pre = (($token->modifier & QST_WILDCARD_BEGIN) !== 0) ? '' : ($page['use_regexp_ICU'] ? '\\\\b' : '[[:<:]]');
            $post = (($token->modifier & QST_WILDCARD_END) !== 0) ? '' : ($page['use_regexp_ICU'] ? '\\\\b' : '[[:>:]]');
            foreach ($fields as $field) {
                $variant_ = addslashes(preg_quote((string) $variant));
                $clauses[] = "{$field} REGEXP '{$pre}{$variant_}{$post}'";
            }
        } else {
            $ft = $variant;
            if (($token->modifier & QST_QUOTED) !== 0) {
                $ft = "'{$ft}'";
            }

            if (($token->modifier & QST_WILDCARD_END) !== 0) {
                $ft .= '*';
            }

            $fts[] = $ft;
        }
    }

    if ($fts !== []) {
        $fields_ = implode(', ', $fields);
        $fulltext_ = addslashes(implode(' ', $fts));
        $clauses[] = "MATCH({$fields_}) AGAINST('{$fulltext_}' IN BOOLEAN MODE)";
    }

    return $clauses;
}

function qsearch_get_images(
    QExpression $expr,
    QResults $qsr
): void {
    $qsr->images_iids = array_fill(0, count($expr->stokens), []);

    $query_base = 'SELECT id from images i WHERE';
    $counter = count($expr->stokens);
    for ($i = 0; $i < $counter; $i++) {
        $token = $expr->stokens[$i];
        $scope_id = isset($token->scope) ? $token->scope->id : 'photo';
        $clauses = [];

        $like = addslashes($token->term);
        $like = str_replace(['%', '_'], ['\\%', '\\_'], $like); // escape LIKE specials %_
        $file_like = "CONVERT(file, CHAR) LIKE '%{$like}%'";

        switch ($scope_id) {
            case 'photo':
                $clauses[] = $file_like;
                $clauses = array_merge($clauses, qsearch_get_text_token_search_sql($token, ['name', 'comment']));
                break;

            case 'file':
                $clauses[] = $file_like;
                break;
            case 'author':
                if (strlen($token->term) !== 0) {
                    $clauses = array_merge($clauses, qsearch_get_text_token_search_sql($token, ['author']));
                } elseif (($token->modifier & QST_WILDCARD) !== 0) {
                    $clauses[] = 'author IS NOT NULL';
                } else {
                    $clauses[] = 'author IS NULL';
                }

                break;
            case 'width':
            case 'height':
                $clauses[] = $token->scope->get_sql($scope_id, $token);
                break;
            case 'ratio':
                $clauses[] = $token->scope->get_sql('width/height', $token);
                break;
            case 'size':
                $clauses[] = $token->scope->get_sql('width*height', $token);
                break;
            case 'hits':
                $clauses[] = $token->scope->get_sql('hit', $token);
                break;
            case 'score':
                $clauses[] = $token->scope->get_sql('rating_score', $token);
                break;
            case 'filesize':
                $clauses[] = $token->scope->get_sql('1024*filesize', $token);
                break;
            case 'created':
                $clauses[] = $token->scope->get_sql('date_creation', $token);
                break;
            case 'posted':
                $clauses[] = $token->scope->get_sql('date_available', $token);
                break;
            case 'id':
                $clauses[] = $token->scope->get_sql($scope_id, $token);
                break;
            default:
                // allow plugins to have their own scope with columns added in db by themselves
                $clauses = trigger_change('qsearch_get_images_sql_scopes', $clauses, $token, $expr);
                break;
        }

        if (! empty($clauses)) {
            $clauses_ = implode("\n OR ", $clauses);
            $query = "{$query_base} ({$clauses_});";
            $qsr->images_iids[$i] = query2array($query, null, 'id');
        }
    }
}

function qsearch_get_tags(
    QExpression $expr,
    QResults $qsr
): void {
    $token_tag_ids = array_fill(0, count($expr->stokens), []);
    $qsr->tag_iids = $token_tag_ids;
    $all_tags = [];
    $counter = count($expr->stokens);

    for ($i = 0; $i < $counter; $i++) {
        $token = $expr->stokens[$i];
        if (isset($token->scope) && $token->scope->id != 'tag') {
            continue;
        }

        if (empty($token->term)) {
            continue;
        }

        $clauses = qsearch_get_text_token_search_sql($token, ['name']);
        $clauses_ = implode("\n OR ", $clauses);
        $query = "SELECT * FROM tags WHERE ({$clauses_})";
        $result = pwg_query($query);
        while ($tag = pwg_db_fetch_assoc($result)) {
            $token_tag_ids[$i][] = $tag['id'];
            $all_tags[$tag['id']] = $tag;
        }
    }

    // check adjacent short words
    for ($i = 0; $i < count($expr->stokens) - 1; $i++) {
        if ((strlen($expr->stokens[$i]->term) <= 3 || strlen($expr->stokens[$i + 1]->term) <= 3)
          && (($expr->stoken_modifiers[$i] & (QST_QUOTED | QST_WILDCARD)) == 0)
          && (($expr->stoken_modifiers[$i + 1] & (QST_BREAK | QST_QUOTED | QST_WILDCARD)) == 0)) {
            $common = array_intersect($token_tag_ids[$i], $token_tag_ids[$i + 1]);
            if ($common !== []) {
                $token_tag_ids[$i] = $token_tag_ids[$i + 1] = $common;
            }
        }
    }

    // get images
    $positive_ids = [];
    $not_ids = [];
    $counter = count($expr->stokens);
    for ($i = 0; $i < $counter; $i++) {
        $tag_ids = $token_tag_ids[$i];
        $token = $expr->stokens[$i];

        if (! empty($tag_ids)) {
            $tag_ids_ = implode(',', $tag_ids);
            $query = "SELECT image_id FROM image_tag WHERE tag_id IN ({$tag_ids_}) GROUP BY image_id";
            $qsr->tag_iids[$i] = query2array($query, null, 'image_id');
            if (($expr->stoken_modifiers[$i] & QST_NOT) !== 0) {
                $not_ids = array_merge($not_ids, $tag_ids);
            } elseif (strlen($token->term) > 2 || count($expr->stokens) == 1 || isset($token->scope) || ($token->modifier & (QST_WILDCARD | QST_QUOTED))) {
                // add tag ids to list only if the word is not too short (such as de / la /les ...)
                $positive_ids = array_merge($positive_ids, $tag_ids);
            }
        } elseif (isset($token->scope) && $token->scope->id == 'tag' && strlen($token->term) == 0) {
            if (($token->modifier & QST_WILDCARD) !== 0) {// eg. 'tag:*' returns all tagged images
                $qsr->tag_iids[$i] = query2array('SELECT DISTINCT image_id FROM image_tag;', null, 'image_id');
            } else {// eg. 'tag:' returns all untagged images
                $qsr->tag_iids[$i] = query2array('SELECT id FROM images LEFT JOIN image_tag ON id = image_id WHERE image_id IS NULL;', null, 'id');
            }
        }
    }

    $all_tags = array_intersect_key($all_tags, array_flip(array_diff($positive_ids, $not_ids)));
    usort($all_tags, tag_alpha_compare(...));
    foreach ($all_tags as &$tag) {
        $tag['name'] = trigger_change('render_tag_name', $tag['name'], $tag);
    }

    $qsr->all_tags = $all_tags;
    $qsr->tag_ids = $token_tag_ids;
}

function qsearch_get_categories(
    QExpression $expr,
    QResults $qsr
): void {
    global $user, $conf;
    $token_cat_ids = array_fill(0, count($expr->stokens), []);
    $qsr->cat_iids = $token_cat_ids;
    $all_cats = [];
    $counter = count($expr->stokens);

    for ($i = 0; $i < $counter; $i++) {
        $token = $expr->stokens[$i];
        if (isset($token->scope) && $token->scope->id != 'category') { // not relevant yet
            continue;
        }

        if (empty($token->term)) {
            continue;
        }

        $clauses = qsearch_get_text_token_search_sql($token, ['name', 'comment']);
        $clauses_ = implode("\n OR ", $clauses);
        $query = "SELECT * FROM categories INNER JOIN user_cache_categories ON id = cat_id and user_id = {$user['id']} WHERE ({$clauses_});";
        $result = pwg_query($query);
        while ($cat = pwg_db_fetch_assoc($result)) {
            $token_cat_ids[$i][] = $cat['id'];
            $all_cats[$cat['id']] = $cat;
        }
    }

    // check adjacent short words
    for ($i = 0; $i < count($expr->stokens) - 1; $i++) {
        if ((strlen($expr->stokens[$i]->term) <= 3 || strlen($expr->stokens[$i + 1]->term) <= 3)
          && (($expr->stoken_modifiers[$i] & (QST_QUOTED | QST_WILDCARD)) == 0)
          && (($expr->stoken_modifiers[$i + 1] & (QST_BREAK | QST_QUOTED | QST_WILDCARD)) == 0)) {
            $common = array_intersect($token_cat_ids[$i], $token_cat_ids[$i + 1]);
            if ($common !== []) {
                $token_cat_ids[$i] = $token_cat_ids[$i + 1] = $common;
            }
        }
    }

    // get images
    $positive_ids = [];
    $not_ids = [];
    $counter = count($expr->stokens);
    for ($i = 0; $i < $counter; $i++) {
        $cat_ids = $token_cat_ids[$i];
        $token = $expr->stokens[$i];

        if (! empty($cat_ids)) {
            if ($conf['quick_search_include_sub_albums']) {
                $cat_ids_ = implode(',', get_subcat_ids($cat_ids));
                $query = "SELECT id FROM categories INNER JOIN user_cache_categories ON id = cat_id and user_id = {$user['id']} WHERE id IN ({$cat_ids_});";
                $cat_ids = query2array($query, null, 'id');
            }

            $cat_ids_ = implode(',', $cat_ids);
            $query = "SELECT image_id FROM image_category WHERE category_id IN ({$cat_ids_}) GROUP BY image_id;";
            $qsr->cat_iids[$i] = query2array($query, null, 'image_id');
            if (($expr->stoken_modifiers[$i] & QST_NOT) !== 0) {
                $not_ids = array_merge($not_ids, $cat_ids);
            } elseif (strlen($token->term) > 2 || count($expr->stokens) == 1 || isset($token->scope) || ($token->modifier & (QST_WILDCARD | QST_QUOTED))) {
                // add cat ids to list only if the word is not too short (such as de / la /les ...)
                $positive_ids = array_merge($positive_ids, $cat_ids);
            }
        } elseif (isset($token->scope) && $token->scope->id == 'category' && strlen($token->term) == 0) {
            if (($token->modifier & QST_WILDCARD) !== 0) {// eg. 'category:*' returns all images associated to an album
                $qsr->cat_iids[$i] = query2array('SELECT DISTINCT image_id FROM image_category;', null, 'image_id');
            } else {// eg. 'category:' returns all orphan images
                $qsr->cat_iids[$i] = query2array('SELECT id FROM images LEFT JOIN image_category ON id = image_id WHERE image_id IS NULL;', null, 'id');
            }
        }
    }

    $all_cats = array_intersect_key($all_cats, array_flip(array_diff($positive_ids, $not_ids)));
    usort($all_cats, tag_alpha_compare(...));
    foreach ($all_cats as &$cat) {
        $cat['name'] = trigger_change('render_category_name', $cat['name'], $cat);
    }

    $qsr->all_cats = $all_cats;
    $qsr->cat_ids = $token_cat_ids;
}

function qsearch_eval(
    QMultiToken $expr,
    QResults $qsr,
    string|null &$qualifies,
    string|null &$ignored_terms
) {
    $qualifies = false; // until we find at least one positive term
    $ignored_terms = [];
    $ids = [];
    $not_ids = [];
    $counter = count($expr->tokens);

    for ($i = 0; $i < $counter; $i++) {
        $crt = $expr->tokens[$i];
        if ($crt->is_single) {
            $crt_ids = $qsr->iids[$crt->idx] = array_unique(
                array_merge(
                    $qsr->images_iids[$crt->idx],
                    $qsr->cat_iids[$crt->idx],
                    $qsr->tag_iids[$crt->idx]
                )
            );
            $crt_qualifies = $crt_ids !== [] || count($qsr->tag_ids[$crt->idx]) > 0;
            $crt_ignored_terms = $crt_qualifies ? [] : [(string) $crt];
        } else {
            $crt_ids = qsearch_eval($crt, $qsr, $crt_qualifies, $crt_ignored_terms);
        }

        $modifier = $crt->modifier;
        if (($modifier & QST_NOT) !== 0) {
            $not_ids = array_unique(array_merge($not_ids, $crt_ids));
        } else {
            $ignored_terms = array_merge($ignored_terms, $crt_ignored_terms);
            if (($modifier & QST_OR) !== 0) {
                $ids = array_unique(array_merge($ids, $crt_ids));
                $qualifies |= $crt_qualifies;
            } elseif ($crt_qualifies) {
                $ids = $qualifies ? array_intersect($ids, $crt_ids) : $crt_ids;

                $qualifies = true;
            }
        }
    }

    if ($not_ids !== []) {
        $ids = array_diff($ids, $not_ids);
    }

    return $ids;
}

/**
 * Returns the search results corresponding to a quick/query search.
 * A quick/query search returns many items (search is not strict), but results
 * are sorted by relevance unless $super_order_by is true. Returns:
 *  array (
 *    'items' => array of matching images
 *    'qs'    => array(
 *      'unmatched_terms' => array of terms from the input string that were not matched
 *      'matching_tags' => array of matching tags
 *      'matching_cats' => array of matching categories
 *      'matching_cats_no_images' =>array(99) - matching categories without images
 *      )
 *    )
 */
function get_quick_search_results(
    string $q,
    array $options
): array {
    global $persistent_cache, $conf, $user;

    $cache_key = $persistent_cache->make_key([
        strtolower($q),
        $conf['order_by'],
        $user['id'], $user['cache_update_time'],
        isset($options['permissions']) ? (bool) $options['permissions'] : true,
        $options['images_where'] ?? '',
    ]);
    if ($persistent_cache->get($cache_key, $res)) {
        return $res;
    }

    $res = get_quick_search_results_no_cache($q, $options);

    if (count($res['items']) > 0) {// cache the results only if not empty - otherwise it is useless
        $persistent_cache->set($cache_key, $res, 300);
    }

    return $res;
}

/**
 * @see get_quick_search_results but without result caching
 */
function get_quick_search_results_no_cache(
    string $q,
    array $options
): array {
    global $conf;

    $q = trim(stripslashes($q));
    $search_results =
      [
          'items' => [],
          'qs' => [
              'q' => $q,
          ],
      ];

    $q = trigger_change('qsearch_pre', $q);

    $scopes = [];
    $scopes[] = new QSearchScope('tag', ['tags']);
    $scopes[] = new QSearchScope('photo', ['photos']);
    $scopes[] = new QSearchScope('file', ['filename']);
    $scopes[] = new QSearchScope('author', [], true);
    $scopes[] = new QNumericRangeScope('width', []);
    $scopes[] = new QNumericRangeScope('height', []);
    $scopes[] = new QNumericRangeScope('ratio', [], false, 0.001);
    $scopes[] = new QNumericRangeScope('size', []);
    $scopes[] = new QNumericRangeScope('filesize', []);
    $scopes[] = new QNumericRangeScope('hits', ['hit', 'visit', 'visits']);
    $scopes[] = new QNumericRangeScope('score', ['rating'], true);
    $scopes[] = new QNumericRangeScope('id', []);

    $createdDateAliases = ['taken', 'shot'];
    $postedDateAliases = ['added'];
    if ($conf['calendar_datefield'] == 'date_creation') {
        $createdDateAliases[] = 'date';
    } else {
        $postedDateAliases[] = 'date';
    }

    $scopes[] = new QDateRangeScope('created', $createdDateAliases, true);
    $scopes[] = new QDateRangeScope('posted', $postedDateAliases);

    // allow plugins to add their own scopes
    $scopes = trigger_change('qsearch_get_scopes', $scopes);
    $expression = new QExpression($q, $scopes);

    // get inflections for terms
    $inflector = null;
    $lang_code = substr(get_default_language(), 0, 2);
    @include_once(PHPWG_ROOT_PATH . 'include/inflectors/' . $lang_code . '.php');
    $class_name = 'Inflector_' . $lang_code;
    if (class_exists($class_name)) {
        $inflector = new $class_name();
        foreach ($expression->stokens as $token) {
            if (isset($token->scope) && ! $token->scope->is_text) {
                continue;
            }

            if (strlen($token->term) > 2
              && ($token->modifier & (QST_QUOTED | QST_WILDCARD)) == 0
              && strcspn($token->term, "'0123456789") == strlen($token->term)) {
                $token->variants = array_unique(array_diff($inflector->get_variants($token->term), [$token->term]));
            }
        }
    }

    trigger_notify('qsearch_expression_parsed', $expression);
    //var_export($expression);

    if (count($expression->stokens) == 0) {
        return $search_results;
    }

    $qsr = new QResults();
    qsearch_get_tags($expression, $qsr);
    qsearch_get_categories($expression, $qsr);
    qsearch_get_images($expression, $qsr);

    // allow plugins to evaluate their own scopes
    trigger_notify('qsearch_before_eval', $expression, $qsr);

    $ids = qsearch_eval($expression, $qsr, $tmp, $search_results['qs']['unmatched_terms']);

    $debug[] = "<!--\nparsed: " . htmlspecialchars($expression);
    $debug[] = count($expression->stokens) . ' tokens';
    $counter = count($expression->stokens);
    for ($i = 0; $i < $counter; $i++) {
        $debug[] = htmlspecialchars((string) $expression->stokens[$i]) . ': ' . count($qsr->tag_ids[$i]) . ' tags, ' . count($qsr->tag_iids[$i]) . ' tiids, ' . count($qsr->images_iids[$i]) . ' iiids, ' . count($qsr->iids[$i]) . ' iids'
          . ' modifier:' . dechex($expression->stoken_modifiers[$i])
          . (empty($expression->stokens[$i]->variants) ? '' : ' variants: ' . htmlspecialchars(implode(', ', $expression->stokens[$i]->variants)));
    }

    $debug[] = 'before perms ' . count($ids);

    $search_results['qs']['matching_tags'] = $qsr->all_tags;
    $search_results['qs']['matching_cats'] = $qsr->all_cats;
    $search_results = trigger_change('qsearch_results', $search_results, $expression, $qsr);
    if (isset($search_results['items'])) {
        $ids = array_merge($ids, $search_results['items']);
    }

    global $template;

    if (empty($ids)) {
        $debug[] = '-->';
        $template->append('footer_elements', implode("\n", $debug));
        return $search_results;
    }

    $permissions = $options['permissions'] ?? true;

    $where_clauses = [];
    $where_clauses[] = 'i.id IN (' . implode(',', $ids) . ')';
    if (! empty($options['images_where'])) {
        $where_clauses[] = '(' . $options['images_where'] . ')';
    }

    if ($permissions) {
        $where_clauses[] = get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'forbidden_images' => 'i.id',
            ],
            null,
            true
        );
    }

    $query = 'SELECT DISTINCT(id) FROM images i';
    if ($permissions) {
        $query .= ' INNER JOIN image_category AS ic ON id = ic.image_id';
    }

    $where_clauses_ = implode("\n AND ", $where_clauses);
    $query .= " WHERE {$where_clauses_} \n {$conf['order_by']};";
    $ids = query2array($query, null, 'id');

    $debug[] = count($ids) . ' final photo count -->';
    $template->append('footer_elements', implode("\n", $debug));

    $search_results['items'] = $ids;
    return $search_results;
}

/**
 * Returns an array of 'items' corresponding to the search id.
 * It can be either a quick search or a regular search.
 *
 * @param string $images_where optional aditional restriction on images table
 */
function get_search_results(
    string $search_id,
    bool|null $super_order_by,
    string $images_where = ''
): array {
    $search = get_search_array($search_id);
    if (! isset($search['q'])) {
        return get_regular_search_results($search, $images_where);
    }

    return get_quick_search_results($search['q'], [
        'super_order_by' => $super_order_by,
        'images_where' => $images_where,
    ]);

}

function split_allwords($raw_allwords): ?array
{
    $words = null;

    // we specify the list of characters to trim, to add the ".". We don't want to split words
    // on "." but on ". ", and we have to deal with trailing dots.
    $raw_allwords = trim((string) $raw_allwords, " \n\r\t\v\x00.");

    if (! preg_match('/^\s*$/', $raw_allwords)) {
        $drop_char_match = [';', '&', '(', ')', '<', '>', '`', "'", '"', '|', ',', '@', '?', '%', '. ', '[', ']', '{', '}', ':', '\\', '/', '=', "'", '!', '*'];
        $drop_char_replace = [' ', ' ', ' ', ' ', ' ', ' ', '', '', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', ' ', ' ', ' ', ' ', ' '];

        // Split words
        $words = array_unique(
            preg_split(
                '/\s+/',
                str_replace(
                    $drop_char_match,
                    $drop_char_replace,
                    $raw_allwords
                )
            )
        );
    }

    return $words;
}

function get_available_search_uuid()
{
    $candidate = 'psk-' . date('Ymd') . '-' . generate_key(10);

    $query = "SELECT COUNT(*) FROM search WHERE search_uuid = '{$candidate}';";
    [$counter] = pwg_db_fetch_row(pwg_query($query));
    if ($counter == 0) {
        return $candidate;
    }

    return get_available_search_uuid();

}

function save_search($rules, $forked_from = null): array
{
    global $user;

    [$dbnow] = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
    $search_uuid = get_available_search_uuid();

    single_insert(
        'search',
        [
            'rules' => pwg_db_real_escape_string(serialize($rules)),
            'created_on' => $dbnow,
            'created_by' => $user['user_id'],
            'search_uuid' => $search_uuid,
            'forked_from' => $forked_from,
        ]
    );

    if (! is_a_guest() && ! is_generic()) {
        userprefs_update_param('gallery_search_filters', array_keys($rules['fields'] ?? []));
    }

    $url = make_index_url(
        [
            'section' => 'search',
            'search' => $search_uuid,
        ]
    );

    return [$search_uuid, $url];
}
