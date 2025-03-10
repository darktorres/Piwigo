<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\inc;

class QDateRangeScope extends QSearchScope
{
    public function __construct($id, $aliases, $nullable = false)
    {
        parent::__construct($id, $aliases, $nullable, false);
    }

    public function parse($token)
    {
        $str = $token->term;
        $strict = [0, 0];
        if (($pos = strpos($str, '..')) !== false) {
            $range = [substr($str, 0, $pos), substr($str, $pos + 2)];
        } elseif (@$str[0] == '>') {
            $range = [substr($str, 1), ''];
            $strict[0] = 1;
        } elseif (@$str[0] == '<') {
            $range = ['', substr($str, 1)];
            $strict[1] = 1;
        } elseif (($token->modifier & functions_search::QST_WILDCARD_BEGIN)) {
            $range = ['', $str];
        } elseif (($token->modifier & functions_search::QST_WILDCARD_END)) {
            $range = [$str, ''];
        } else {
            $range = [$str, $str];
        }

        foreach ($range as $i => &$val) {
            if (preg_match('/([0-9]{4})-?((?:1[0-2])|(?:0?[1-9]))?-?((?:(?:[1-3][0-9])|(?:0?[1-9])))?/', $val, $matches)) {
                array_shift($matches);
                if (! isset($matches[1])) {
                    $matches[1] = ($i ^ $strict[$i]) ? 12 : 1;
                }
                if (! isset($matches[2])) {
                    $matches[2] = ($i ^ $strict[$i]) ? 31 : 1;
                }
                $val = implode('-', $matches);
                if ($i ^ $strict[$i]) {
                    $val .= ' 23:59:59';
                }
            } elseif (strlen($val)) {
                return false;
            }
        }

        if (! $this->nullable && $range[0] == '' && $range[1] == '') {
            return false;
        }

        $token->scope_data = $range;
        return true;
    }

    public function get_sql($field, $token)
    {
        $clauses = [];
        if ($token->scope_data[0] != '') {
            $clauses[] = $field . ' >= \'' . $token->scope_data[0] . '\'';
        }
        if ($token->scope_data[1] != '') {
            $clauses[] = $field . ' <= \'' . $token->scope_data[1] . '\'';
        }

        if (empty($clauses)) {
            if ($token->modifier & functions_search::QST_WILDCARD) {
                return $field . ' IS NOT NULL';
            }

            return $field . ' IS NULL';
        }
        return '(' . implode(' AND ', $clauses) . ')';
    }
}
