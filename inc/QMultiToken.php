<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/functions_search.inc.php';

/** Represents an expression of several words or sub expressions to be searched.*/
class QMultiToken implements \Stringable
{
    public $is_single = false;

    public $modifier;

    public $tokens = []; // the actual array of QSingleToken or QMultiToken

    #[\Override]
    public function __toString(): string
    {
        $s = '';
        $counter = count($this->tokens);
        for ($i = 0; $i < $counter; ++$i) {
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
        $q,
        &$qi,
        $level,
        $root
    ) {
        $crt_token = '';
        $crt_modifier = 0;
        $crt_scope = null;

        for ($stop = false; ! $stop && $qi < strlen($q); ++$qi) {
            $ch = $q[$qi];
            if (($crt_modifier & QST_QUOTED) == 0) {
                switch ($ch) {
                    case '(':
                        if (strlen((string) $crt_token) !== 0) {
                            $this->push($crt_token, $crt_modifier, $crt_scope);
                        }

                        $sub = new self();
                        ++$qi;
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
                          && $qi + 1 < strlen($q) && preg_match(
                              '/\d/',
                              $q[$qi + 1]
                          )) {// dot between digits is not a separator e.g. F2.8
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
            } elseif ($ch == '"') {
                // quoted
                if ($qi + 1 < strlen($q) && $q[$qi + 1] == '*') {
                    $crt_modifier |= QST_WILDCARD_END;
                    ++$qi;
                }

                $this->push($crt_token, $crt_modifier, $crt_scope);
            } else {
                $crt_token .= $ch;
            }
        }

        $this->push($crt_token, $crt_modifier, $crt_scope);
        $counter = count($this->tokens);

        for ($i = 0; $i < $counter; ++$i) {
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

                --$i;
            }
        }

        if ($level > 0 && count($this->tokens) && $this->tokens[0]->is_single) {
            $this->tokens[0]->modifier |= QST_BREAK;
        }
    }

    /* because evaluations occur left to right, we ensure that 'a OR b c d' is interpreted as 'a OR (b c d)'*/
    protected function check_operator_priority()
    {
        $counter = count($this->tokens);
        for ($i = 0; $i < $counter; ++$i) {
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
                for ($j = $i + 1; $j < count($this->tokens); ++$j) {
                    if ($this->priority($this->tokens[$j]->modifier) >= $prio) {
                        ++$term_count;
                    } // also take d
                    else {
                        break;
                    }
                }

                --$i; // move pointer to b
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

    private function push(&$token, &$modifier, &$scope)
    {
        if (strlen((string) $token) || (isset($scope) && $scope->nullable)) {
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
    ) {
        $counter = count($this->tokens);
        for ($i = 0; $i < $counter; ++$i) {
            if ($this->tokens[$i]->is_single) {
                if (! isset($this->tokens[$i]->scope)) {
                    $this->tokens[$i]->scope = $scope;
                }
            } else {
                $this->tokens[$i]->apply_scope($scope);
            }
        }
    }

    private function priority($modifier)
    {
        return ($modifier & QST_OR) !== 0 ? 0 : 1;
    }
}
