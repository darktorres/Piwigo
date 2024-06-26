<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/functions_search.inc.php';

class QNumericRangeScope extends QSearchScope
{
    public function __construct(
        $id,
        $aliases,
        $nullable = false,
        private $epsilon = 0
    ) {
        parent::__construct($id, $aliases, $nullable, false);
    }

    #[\Override]
    public function parse($token)
    {
        $str = $token->term;
        $strict = [0, 0];
        $range_requested = true;
        if (($pos = strpos($str, '..')) !== false) {
            $range = [substr($str, 0, $pos), substr($str, $pos + 2)];
        } elseif (@$str[0] == '>') {// ratio:>1
            $range = [substr($str, 1), ''];
            $strict[0] = 1;
        } elseif (@$str[0] == '<') { // size:<5mp
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
                    $mult = 1;
                    $mult = $matches[2] === 'k' || $matches[2] === 'K' ? 1000 : 1000000;
                    $val *= $mult;
                    if ($i && ! $range_requested) {// round up the upper limit if possible - e.g 6k goes up to 6999, but 6.12k goes only up to 6129
                        if (($dot_pos = strpos(
                            $matches[1],
                            '.'
                        )) !== false) {
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

    public function get_sql($field, $token)
    {
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
