<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\inc;

use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\QDateRangeScope;
use Piwigo\inc\QExpression;
use Piwigo\inc\QMultiToken;
use Piwigo\inc\QNumericRangeScope;
use Piwigo\inc\QResults;
use Piwigo\inc\QSearchScope;

class functions_search
{
  static function get_search_id_pattern($candidate)
  {
    $clause_pattern = null;
    if (preg_match('/^psk-\d{8}-[a-z0-9]{10}$/i', $candidate))
    {
      $clause_pattern = 'search_uuid = \'%s\'';
    }
    elseif (preg_match('/^\d+$/', $candidate))
    {
      $clause_pattern = 'id = %u';
    }

    return $clause_pattern;
  }

  static function get_search_info($candidate)
  {
    global $page;

    // $candidate might be a search.id or a search_uuid
    $clause_pattern = self::get_search_id_pattern($candidate);

    if (empty($clause_pattern))
    {
      die('Invalid search identifier');
    }

    $query = '
  SELECT *
    FROM '.SEARCH_TABLE.'
    WHERE '.sprintf($clause_pattern, $candidate).'
  ;';
    $searches = functions_mysqli::query2array($query);

    if (count($searches) > 0)
    {
      // we don't want spies to be able to see the search rules of any prior search (performed
      // by any user). We don't want them to be try index.php?/search/123 then index.php?/search/124
      // and so on. That's why we have implemented search_uuid with random characters.
      //
      // We also don't want to break old search urls with only the numeric id, so we only break if
      // there is no uuid.
      //
      // We also don't want to die if we're in the API.
      if (functions::script_basename() != 'ws' and 'id = %u' == $clause_pattern and isset($searches[0]['search_uuid']))
      {
        functions_html::fatal_error('this search is not reachable with its id, need the search_uuid instead');
      }

      if (isset($page['section']) and 'search' == $page['section'])
      {
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
   *
   * @param int $search_id
   * @return array
   */
  static function get_search_array($search_id)
  {
    global $user;

    $search = self::get_search_info($search_id);

    if (empty($search))
    {
      functions_html::bad_request('this search identifier does not exist');
    }

    return unserialize($search['rules']);
  }

  /**
   * Returns the SQL clause for a search.
   * Transforms the array returned by get_search_array() into SQL sub-query.
   *
   * @param array $search
   * @return string
   */
  static function get_sql_search_clause($search)
  {
    // SQL where clauses are stored in $clauses array during query
    // construction
    $clauses = array();

    foreach (array('file','name','comment','author') as $textfield)
    {
      if (isset($search['fields'][$textfield]))
      {
        $local_clauses = array();
        foreach ($search['fields'][$textfield]['words'] as $word)
        {
          if ('author' == $textfield)
          {
            $local_clauses[] = $textfield."='".$word."'";
          }
          else
          {
            $local_clauses[] = $textfield." LIKE '%".$word."%'";
          }
        }

        if (count($local_clauses) > 0)
        {
          // adds brackets around where clauses
          $local_clauses = functions::prepend_append_array_items($local_clauses, '(', ')');

          $clauses[] = implode(
            ' '.$search['fields'][$textfield]['mode'].' ',
            $local_clauses
          );
        }
      }
    }

    if (isset($search['fields']['allwords']) and !empty($search['fields']['allwords']['words']) and count($search['fields']['allwords']['fields']) > 0)
    {
      // 1) we search in regular fields (ie, the ones in the piwigo_images table)
      $fields = array('file', 'name', 'comment', 'author');

      if (isset($search['fields']['allwords']['fields']) and count($search['fields']['allwords']['fields']) > 0)
      {
        $fields = array_intersect($fields, $search['fields']['allwords']['fields']);
      }

      $cat_fields_dictionnary = array(
        'cat-title' => 'name',
        'cat-desc' => 'comment',
      );
      $cat_fields = array_intersect(array_keys($cat_fields_dictionnary), $search['fields']['allwords']['fields']);

      // in the OR mode, request bust be :
      // ((field1 LIKE '%word1%' OR field2 LIKE '%word1%')
      // OR (field1 LIKE '%word2%' OR field2 LIKE '%word2%'))
      //
      // in the AND mode :
      // ((field1 LIKE '%word1%' OR field2 LIKE '%word1%')
      // AND (field1 LIKE '%word2%' OR field2 LIKE '%word2%'))
      $word_clauses = array();
      $cat_ids_by_word = $tag_ids_by_word = array();
      foreach ($search['fields']['allwords']['words'] as $word)
      {
        $field_clauses = array();
        foreach ($fields as $field)
        {
          $field_clauses[] = $field." LIKE '%".$word."%'";
        }

        if (count($cat_fields) > 0)
        {
          $cat_word_clauses = array();
          $cat_field_clauses = array();
          foreach ($cat_fields as $cat_field)
          {
            $cat_field_clauses[] = $cat_fields_dictionnary[$cat_field]." LIKE '%".$word."%'";
          }

          // adds brackets around where clauses
          $cat_word_clauses[] = implode(' OR ', $cat_field_clauses);

          $query = '
  SELECT
      id
    FROM '.CATEGORIES_TABLE.'
    WHERE '.implode(' OR ', $cat_word_clauses).'
  ;';
          $cat_ids = functions_mysqli::query2array($query, null, 'id');
          $cat_ids_by_word[$word] = $cat_ids;
          if (count($cat_ids) > 0)
          {
            $query = '
  SELECT
      image_id
    FROM '.IMAGE_CATEGORY_TABLE.'
    WHERE category_id IN ('.implode(',', $cat_ids).')
  ;';
            $cat_image_ids = functions_mysqli::query2array($query, null, 'image_id');

            if (count($cat_image_ids) > 0)
            {
              $field_clauses[] = 'id IN ('.implode(',', $cat_image_ids).')';
            }
          }
        }

        // search_in_tags
        if (in_array('tags', $search['fields']['allwords']['fields']))
        {
          $query = '
  SELECT
      id
    FROM '.TAGS_TABLE.'
    WHERE name LIKE \'%'.$word.'%\'
  ;';
          $tag_ids = functions_mysqli::query2array($query, null, 'id');
          $tag_ids_by_word[$word] = $tag_ids;
          if (count($tag_ids) > 0)
          {
            $query = '
  SELECT
      image_id
    FROM '.IMAGE_TAG_TABLE.'
    WHERE tag_id IN ('.implode(',', $tag_ids).')
  ;';
            $tag_image_ids = functions_mysqli::query2array($query, null, 'image_id');

            if (count($tag_image_ids) > 0)
            {
              $field_clauses[] = 'id IN ('.implode(',', $tag_image_ids).')';
            }
          }
        }

        if (count($field_clauses) > 0)
        {
          // adds brackets around where clauses
          $word_clauses[] = implode(
            "\n          OR ",
            $field_clauses
          );
        }
      }

      if (count($word_clauses) > 0)
      {
        array_walk(
          $word_clauses,
          function(&$s){ $s = "(".$s.")"; }
        );
      }

      // make sure the "mode" is either OR or AND
      if (!in_array($search['fields']['allwords']['mode'], array('OR', 'AND')))
      {
        $search['fields']['allwords']['mode'] = 'AND';
      }

      $clauses[] = "\n         ".implode(
        "\n         ". $search['fields']['allwords']['mode']. "\n         ",
        $word_clauses
      );

      if (count($cat_ids_by_word) > 0)
      {
        $matching_cat_ids = null;
        foreach ($cat_ids_by_word as $idx => $cat_ids)
        {
          if (is_null($matching_cat_ids))
          {
            // first iteration
            $matching_cat_ids = $cat_ids;
          }
          else
          {
            $matching_cat_ids = array_merge($matching_cat_ids, $cat_ids);
          }
        }

        $matching_cat_ids = array_unique($matching_cat_ids);
      }

      if (count($tag_ids_by_word) > 0)
      {
        $matching_tag_ids = null;
        foreach ($tag_ids_by_word as $idx => $tag_ids)
        {
          if (is_null($matching_tag_ids))
          {
            // first iteration
            $matching_tag_ids = $tag_ids;
          }
          else
          {
            $matching_tag_ids = array_merge($matching_tag_ids, $tag_ids);
          }
        }

        $matching_tag_ids = array_unique($matching_tag_ids);
      }
    }

    foreach (array('date_available', 'date_creation') as $datefield)
    {
      if (isset($search['fields'][$datefield]))
      {
        $clauses[] = $datefield." = '".$search['fields'][$datefield]['date']."'";
      }

      foreach (array('after','before') as $suffix)
      {
        $key = $datefield.'-'.$suffix;

        if (isset($search['fields'][$key]))
        {
          $clauses[] = $datefield.
            ($suffix == 'after'             ? ' >' : ' <').
            ($search['fields'][$key]['inc'] ? '='  : '').
            " '".$search['fields'][$key]['date']."'";
        }
      }
    }

    if (!empty($search['fields']['date_posted']))
    {
      $options = array(
        '24h' => '24 HOUR',
        '7d' => '7 DAY',
        '30d' => '30 DAY',
        '3m' => '3 MONTH',
        '6m' => '6 MONTH',
        '1y' => '1 YEAR',
      );

      if (isset($options[ $search['fields']['date_posted'] ]))
      {
        $clauses[] = 'date_available > SUBDATE(NOW(), INTERVAL '.$options[ $search['fields']['date_posted'] ].')';
      }
      elseif (preg_match('/^y(\d+)$/', $search['fields']['date_posted'], $matches))
      {
        // that is for y2023 = all photos posted in 2022
        $clauses[] = 'YEAR(date_available) = '.$matches[1];
      }
    }

    if (!empty($search['fields']['filetypes']))
    {
      $filetypes_clauses = array();
      foreach ($search['fields']['filetypes'] as $ext)
      {
        $filetypes_clauses[] = 'path LIKE \'%.'.$ext.'\'';
      }
      $clauses[] = implode(' OR ', $filetypes_clauses);
    }

    if (!empty($search['fields']['added_by']))
    {
      $clauses[] = 'added_by IN ('.implode(',', $search['fields']['added_by']).')';
    }

    if (isset($search['fields']['cat']) and !empty($search['fields']['cat']['words']))
    {
      if ($search['fields']['cat']['sub_inc'])
      {
        // searching all the categories id of sub-categories
        $cat_ids = functions_category::get_subcat_ids($search['fields']['cat']['words']);
      }
      else
      {
        $cat_ids = $search['fields']['cat']['words'];
      }

      $local_clause = 'category_id IN ('.implode(',', $cat_ids).')';
      $clauses[] = $local_clause;
    }

    // adds brackets around where clauses
    $clauses = functions::prepend_append_array_items($clauses, '(', ')');

    $where_separator =
      implode(
        "\n    ".$search['mode'].' ',
        $clauses
        );

    $search_clause = $where_separator;

    return array(
      $search_clause,
      isset($matching_cat_ids) ? array_values($matching_cat_ids) : null,
      isset($matching_tag_ids) ? array_values($matching_tag_ids) : null
    );
  }

  /**
   * Returns the list of items corresponding to the advanced search array.
   *
   * @param array $search
   * @param string $images_where optional additional restriction on images table
   * @return array
   */
  static function get_regular_search_results($search, $images_where='')
  {
    global $conf, $logger;

    $logger->debug(__FUNCTION__, $search);

    $has_filters_filled = false;

    $forbidden = functions_user::get_sql_condition_FandF(
          array
            (
              'forbidden_categories' => 'category_id',
              'visible_categories' => 'category_id',
              'visible_images' => 'id'
            ),
          "\n  AND"
      );

    $items = array();
    $tag_items = array();

    if (isset($search['fields']['tags']))
    {
      if (!empty($search['fields']['tags']['words']))
      {
        $has_filters_filled = true;
      }

      $tag_items = functions_tag::get_image_ids_for_tags(
        $search['fields']['tags']['words'],
        $search['fields']['tags']['mode']
        );

      $logger->debug(__FUNCTION__.' '.count($tag_items).' items in $tag_items');
    }

    list($search_clause, $matching_cat_ids, $matching_tag_ids) = self::get_sql_search_clause($search);

    if (!empty($search_clause))
    {
      $has_filters_filled = true;

      $query = '
  SELECT DISTINCT(id)
    FROM '.IMAGES_TABLE.' i
      INNER JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON id = ic.image_id
      LEFT JOIN '.IMAGE_TAG_TABLE.' AS it ON id = it.image_id
    WHERE '.$search_clause;
      if (!empty($images_where))
      {
        $query .= "\n  AND ".$images_where;
      }
      $query .= $forbidden.'
    '.$conf['order_by'];
      $items = functions::array_from_query($query, 'id');

      $logger->debug(__FUNCTION__.' '.count($items).' items in $items');
    }

    if ( !empty($tag_items) )
    {
      switch ($search['mode'])
      {
        case 'AND':
          if (empty($search_clause) and !isset($search_in_tags_items))
          {
            $items = $tag_items;
          }
          else
          {
            $items = array_values( array_intersect($items, $tag_items) );
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

    return array(
      'items' => $items,
      'search_details' => array(
        'matching_cat_ids' => $matching_cat_ids,
        'matching_tag_ids' => $matching_tag_ids,
        'has_filters_filled' => $has_filters_filled,
      ),
    );
  }



  const QST_QUOTED =         0x01;
  const QST_NOT =            0x02;
  const QST_OR =             0x04;
  const QST_WILDCARD_BEGIN = 0x08;
  const QST_WILDCARD_END =   0x10;
  const QST_WILDCARD = self::QST_WILDCARD_BEGIN|self::QST_WILDCARD_END;
  const QST_BREAK =          0x20;

  static function qsearch_get_text_token_search_sql($token, $fields)
  {
    global $page;

    $clauses = array();
    $variants = array_merge(array($token->term), $token->variants);
    $fts = array();
    foreach ($variants as $variant)
    {
      $use_ft = mb_strlen($variant)>3;
      if ($token->modifier & self::QST_WILDCARD_BEGIN)
        $use_ft = false;
      if ($token->modifier & (self::QST_QUOTED|self::QST_WILDCARD_END) == (self::QST_QUOTED|self::QST_WILDCARD_END))
        $use_ft = false;

      if ($use_ft)
      {
        $max = max( array_map( 'mb_strlen',
          preg_split('/['.preg_quote('-\'!"#$%&()*+,./:;<=>?@[\]^`{|}~','/').']+/', $variant)
          ) );
        if ($max<4)
          $use_ft = false;
      }

      if (!$use_ft)
      {// odd term or too short for full text search; fallback to regex but unfortunately this is diacritic/accent sensitive
        if (!isset($page['use_regexp_ICU']))
        {
          // Prior to MySQL 8.0.4, MySQL used the Henry Spencer regular expression library to support
          // regular expression operations, rather than International Components for Unicode (ICU)
          $page['use_regexp_ICU'] = false;
          $db_version = functions_mysqli::pwg_get_db_version();
          if (!preg_match('/mariadb/i', $db_version) and version_compare($db_version, '8.0.4', '>'))
          {
            $page['use_regexp_ICU'] = true;
          }
        }

        $pre = ($token->modifier & self::QST_WILDCARD_BEGIN) ? '' : ($page['use_regexp_ICU'] ? '\\\\b' : '[[:<:]]');
        $post = ($token->modifier & self::QST_WILDCARD_END) ? '' : ($page['use_regexp_ICU'] ? '\\\\b' : '[[:>:]]');
        foreach( $fields as $field)
          $clauses[] = $field.' REGEXP \''.$pre.addslashes(preg_quote($variant)).$post.'\'';
      }
      else
      {
        $ft = $variant;
        if ($token->modifier & self::QST_QUOTED)
          $ft = '"'.$ft.'"';
        if ($token->modifier & self::QST_WILDCARD_END)
          $ft .= '*';
        $fts[] = $ft;
      }
    }

    if (count($fts))
    {
      $clauses[] = 'MATCH('.implode(', ',$fields).') AGAINST( \''.addslashes(implode(' ',$fts)).'\' IN BOOLEAN MODE)';
    }
    return $clauses;
  }

  static function qsearch_get_images(QExpression $expr, QResults $qsr)
  {
    $qsr->images_iids = array_fill(0, count($expr->stokens), array());

    $query_base = 'SELECT id from '.IMAGES_TABLE.' i WHERE
  ';
    for ($i=0; $i<count($expr->stokens); $i++)
    {
      $token = $expr->stokens[$i];
      $scope_id = isset($token->scope) ? $token->scope->id : 'photo';
      $clauses = array();

      $like = addslashes($token->term);
      $like = str_replace( array('%','_'), array('\\%','\\_'), $like); // escape LIKE specials %_
      $file_like = 'CONVERT(file, CHAR) LIKE \'%'.$like.'%\'';

      switch ($scope_id)
      {
        case 'photo':
          $clauses[] = $file_like;
          $clauses = array_merge($clauses, self::qsearch_get_text_token_search_sql($token, array('name','comment')));
          break;

        case 'file':
          $clauses[] = $file_like;
          break;
        case 'author':
          if ( strlen($token->term) )
            $clauses = array_merge($clauses, self::qsearch_get_text_token_search_sql($token, array('author')));
          elseif ($token->modifier & self::QST_WILDCARD)
            $clauses[] = 'author IS NOT NULL';
          else
            $clauses[] = 'author IS NULL';
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
          $clauses = functions_plugins::trigger_change('qsearch_get_images_sql_scopes', $clauses, $token, $expr);
          break;
      }
      if (!empty($clauses))
      {
        $query = $query_base.'('.implode("\n OR ", $clauses).')';
        $qsr->images_iids[$i] = functions_mysqli::query2array($query,null,'id');
      }
    }
  }

  static function qsearch_get_tags(QExpression $expr, QResults $qsr)
  {
    $token_tag_ids = $qsr->tag_iids = array_fill(0, count($expr->stokens), array() );
    $all_tags = array();

    for ($i=0; $i<count($expr->stokens); $i++)
    {
      $token = $expr->stokens[$i];
      if (isset($token->scope) && 'tag' != $token->scope->id)
        continue;
      if (empty($token->term))
        continue;

      $clauses = self::qsearch_get_text_token_search_sql( $token, array('name'));
      $query = 'SELECT * FROM '.TAGS_TABLE.'
  WHERE ('. implode("\n OR ",$clauses) .')';
      $result = functions_mysqli::pwg_query($query);
      while ($tag = functions_mysqli::pwg_db_fetch_assoc($result))
      {
        $token_tag_ids[$i][] = $tag['id'];
        $all_tags[$tag['id']] = $tag;
      }
    }

    // check adjacent short words
    for ($i=0; $i<count($expr->stokens)-1; $i++)
    {
      if ( (strlen($expr->stokens[$i]->term)<=3 || strlen($expr->stokens[$i+1]->term)<=3)
        && (($expr->stoken_modifiers[$i] & (self::QST_QUOTED|self::QST_WILDCARD)) == 0)
        && (($expr->stoken_modifiers[$i+1] & (self::QST_BREAK|self::QST_QUOTED|self::QST_WILDCARD)) == 0) )
      {
        $common = array_intersect( $token_tag_ids[$i], $token_tag_ids[$i+1] );
        if (count($common))
        {
          $token_tag_ids[$i] = $token_tag_ids[$i+1] = $common;
        }
      }
    }

    // get images
    $positive_ids = $not_ids = array();
    for ($i=0; $i<count($expr->stokens); $i++)
    {
      $tag_ids = $token_tag_ids[$i];
      $token = $expr->stokens[$i];

      if (!empty($tag_ids))
      {
        $query = '
  SELECT image_id FROM '.IMAGE_TAG_TABLE.'
    WHERE tag_id IN ('.implode(',',$tag_ids).')
    GROUP BY image_id';
        $qsr->tag_iids[$i] = functions_mysqli::query2array($query, null, 'image_id');
        if ($expr->stoken_modifiers[$i]&self::QST_NOT)
          $not_ids = array_merge($not_ids, $tag_ids);
        else
        {
          if (strlen($token->term)>2 || count($expr->stokens)==1 || isset($token->scope) || ($token->modifier&(self::QST_WILDCARD|self::QST_QUOTED)) )
          {// add tag ids to list only if the word is not too short (such as de / la /les ...)
            $positive_ids = array_merge($positive_ids, $tag_ids);
          }
        }
      }
      elseif (isset($token->scope) && 'tag' == $token->scope->id && strlen($token->term)==0)
      {
        if ($token->modifier & self::QST_WILDCARD)
        {// eg. 'tag:*' returns all tagged images
          $qsr->tag_iids[$i] = functions_mysqli::query2array('SELECT DISTINCT image_id FROM '.IMAGE_TAG_TABLE, null, 'image_id');
        }
        else
        {// eg. 'tag:' returns all untagged images
          $qsr->tag_iids[$i] = functions_mysqli::query2array('SELECT id FROM '.IMAGES_TABLE.' LEFT JOIN '.IMAGE_TAG_TABLE.' ON id=image_id WHERE image_id IS NULL', null, 'id');
        }
      }
    }

    $all_tags = array_intersect_key($all_tags, array_flip( array_diff($positive_ids, $not_ids) ) );
    usort($all_tags, '\Piwigo\inc\functions_html::tag_alpha_compare');
    foreach ( $all_tags as &$tag )
    {
      $tag['name'] = functions_plugins::trigger_change('render_tag_name', $tag['name'], $tag);
    }
    $qsr->all_tags = $all_tags;
    $qsr->tag_ids = $token_tag_ids;
  }

  static function qsearch_get_categories(QExpression $expr, QResults $qsr)
  {
    global $user, $conf;

    $token_cat_ids = $qsr->cat_iids = array_fill(0, count($expr->stokens), array() );
    $all_cats = array();

    for ($i=0; $i<count($expr->stokens); $i++)
    {
      $token = $expr->stokens[$i];
      if (isset($token->scope) && 'category' != $token->scope->id) // not relevant yet
        continue;
      if (empty($token->term))
        continue;

      $clauses = self::qsearch_get_text_token_search_sql( $token, array('name', 'comment'));
      $query = '
  SELECT
      *
    FROM '.CATEGORIES_TABLE.'
      INNER JOIN '.USER_CACHE_CATEGORIES_TABLE.' ON id = cat_id and user_id = '.$user['id'].'
    WHERE ('. implode("\n OR ",$clauses) .')';
      $result = functions_mysqli::pwg_query($query);
      while ($cat = functions_mysqli::pwg_db_fetch_assoc($result))
      {
        $token_cat_ids[$i][] = $cat['id'];
        $all_cats[$cat['id']] = $cat;
      }
    }

    // check adjacent short words
    for ($i=0; $i<count($expr->stokens)-1; $i++)
    {
      if ( (strlen($expr->stokens[$i]->term)<=3 || strlen($expr->stokens[$i+1]->term)<=3)
        && (($expr->stoken_modifiers[$i] & (self::QST_QUOTED|self::QST_WILDCARD)) == 0)
        && (($expr->stoken_modifiers[$i+1] & (self::QST_BREAK|self::QST_QUOTED|self::QST_WILDCARD)) == 0) )
      {
        $common = array_intersect( $token_cat_ids[$i], $token_cat_ids[$i+1] );
        if (count($common))
        {
          $token_cat_ids[$i] = $token_cat_ids[$i+1] = $common;
        }
      }
    }

    // get images
    $positive_ids = $not_ids = array();
    for ($i=0; $i<count($expr->stokens); $i++)
    {
      $cat_ids = $token_cat_ids[$i];
      $token = $expr->stokens[$i];

      if (!empty($cat_ids))
      {
        if ($conf['quick_search_include_sub_albums'])
        {
          $query = '
  SELECT
      id
    FROM '.CATEGORIES_TABLE.'
      INNER JOIN '.USER_CACHE_CATEGORIES_TABLE.' ON id = cat_id and user_id = '.$user['id'].'
    WHERE id IN ('.implode(',', functions_category::get_subcat_ids($cat_ids)) .')
  ;';
          $cat_ids = functions_mysqli::query2array($query, null, 'id');
        }

        $query = '
  SELECT image_id FROM '.IMAGE_CATEGORY_TABLE.'
    WHERE category_id IN ('.implode(',',$cat_ids).')
    GROUP BY image_id';
        $qsr->cat_iids[$i] = functions_mysqli::query2array($query, null, 'image_id');
        if ($expr->stoken_modifiers[$i]&self::QST_NOT)
          $not_ids = array_merge($not_ids, $cat_ids);
        else
        {
          if (strlen($token->term)>2 || count($expr->stokens)==1 || isset($token->scope) || ($token->modifier&(self::QST_WILDCARD|self::QST_QUOTED)) )
          {// add cat ids to list only if the word is not too short (such as de / la /les ...)
            $positive_ids = array_merge($positive_ids, $cat_ids);
          }
        }
      }
      elseif (isset($token->scope) && 'category' == $token->scope->id && strlen($token->term)==0)
      {
        if ($token->modifier & self::QST_WILDCARD)
        {// eg. 'category:*' returns all images associated to an album
          $qsr->cat_iids[$i] = functions_mysqli::query2array('SELECT DISTINCT image_id FROM '.IMAGE_CATEGORY_TABLE, null, 'image_id');
        }
        else
        {// eg. 'category:' returns all orphan images
          $qsr->cat_iids[$i] = functions_mysqli::query2array('SELECT id FROM '.IMAGES_TABLE.' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' ON id=image_id WHERE image_id IS NULL', null, 'id');
        }
      }
    }

    $all_cats = array_intersect_key($all_cats, array_flip( array_diff($positive_ids, $not_ids) ) );
    usort($all_cats, '\Piwigo\inc\functions_html::tag_alpha_compare');
    foreach ( $all_cats as &$cat )
    {
      $cat['name'] = functions_plugins::trigger_change('render_category_name', $cat['name'], $cat);
    }
    $qsr->all_cats = $all_cats;
    $qsr->cat_ids = $token_cat_ids;
  }


  static function qsearch_eval(QMultiToken $expr, QResults $qsr, &$qualifies, &$ignored_terms)
  {
    $qualifies = false; // until we find at least one positive term
    $ignored_terms = array();

    $ids = $not_ids = array();

    for ($i=0; $i<count($expr->tokens); $i++)
    {
      $crt = $expr->tokens[$i];
      if ($crt->is_single)
      {
        $crt_ids = $qsr->iids[$crt->idx] = array_unique(
          array_merge(
            $qsr->images_iids[$crt->idx],
            $qsr->cat_iids[$crt->idx],
            $qsr->tag_iids[$crt->idx]
            )
          );
        $crt_qualifies = count($crt_ids)>0 || count($qsr->tag_ids[$crt->idx])>0;
        $crt_ignored_terms = $crt_qualifies ? array() : array((string)$crt);
      }
      else
        $crt_ids = self::qsearch_eval($crt, $qsr, $crt_qualifies, $crt_ignored_terms);

      $modifier = $crt->modifier;
      if ($modifier & self::QST_NOT)
        $not_ids = array_unique( array_merge($not_ids, $crt_ids));
      else
      {
        $ignored_terms = array_merge($ignored_terms, $crt_ignored_terms);
        if ($modifier & self::QST_OR)
        {
          $ids = array_unique( array_merge($ids, $crt_ids) );
          $qualifies |= $crt_qualifies;
        }
        elseif ($crt_qualifies)
        {
          if ($qualifies)
            $ids = array_intersect($ids, $crt_ids);
          else
            $ids = $crt_ids;
          $qualifies = true;
        }
      }
    }

    if (count($not_ids))
      $ids = array_diff($ids, $not_ids);
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
   *
   * @param string $q
   * @param bool $super_order_by
   * @param string $images_where optional additional restriction on images table
   * @return array
   */
  static function get_quick_search_results($q, $options)
  {
    global $persistent_cache, $conf, $user;

    $cache_key = $persistent_cache->make_key( array(
      strtolower($q),
      $conf['order_by'],
      $user['id'],$user['cache_update_time'],
      isset($options['permissions']) ? (boolean)$options['permissions'] : true,
      isset($options['images_where']) ? $options['images_where'] : '',
      ) );
    if ($persistent_cache->get($cache_key, $res))
    {
      return $res;
    }

    $res = self::get_quick_search_results_no_cache($q, $options);

    if ( count($res['items']) )
    {// cache the results only if not empty - otherwise it is useless
      $persistent_cache->set($cache_key, $res, 300);
    }
    return $res;
  }

  /**
   * @see get_quick_search_results but without result caching
   */
  static function get_quick_search_results_no_cache($q, $options)
  {
    global $conf;

    $q = trim(stripslashes($q));
    $search_results =
      array(
        'items' => array(),
        'qs' => array('q'=>$q),
      );

    $q = functions_plugins::trigger_change('qsearch_pre', $q);

    $scopes = array();
    $scopes[] = new QSearchScope('tag', array('tags'));
    $scopes[] = new QSearchScope('photo', array('photos'));
    $scopes[] = new QSearchScope('file', array('filename'));
    $scopes[] = new QSearchScope('author', array(), true);
    $scopes[] = new QNumericRangeScope('width', array());
    $scopes[] = new QNumericRangeScope('height', array());
    $scopes[] = new QNumericRangeScope('ratio', array(), false, 0.001);
    $scopes[] = new QNumericRangeScope('size', array());
    $scopes[] = new QNumericRangeScope('filesize', array());
    $scopes[] = new QNumericRangeScope('hits', array('hit', 'visit', 'visits'));
    $scopes[] = new QNumericRangeScope('score', array('rating'), true);
    $scopes[] = new QNumericRangeScope('id', array());

    $createdDateAliases = array('taken', 'shot');
    $postedDateAliases = array('added');
    if ($conf['calendar_datefield'] == 'date_creation')
      $createdDateAliases[] = 'date';
    else
      $postedDateAliases[] = 'date';
    $scopes[] = new QDateRangeScope('created', $createdDateAliases, true);
    $scopes[] = new QDateRangeScope('posted', $postedDateAliases);

    // allow plugins to add their own scopes
    $scopes = functions_plugins::trigger_change('qsearch_get_scopes', $scopes);
    $expression = new QExpression($q, $scopes);

    // get inflections for terms
    $inflector = null;
    $lang_code = substr(functions_user::get_default_language(),0,2);
    @include_once(PHPWG_ROOT_PATH.'inc/inflectors/Inflector_'.$lang_code.'.php');
    $class_name = 'Inflector_'.$lang_code;
    if (class_exists($class_name))
    {
      $inflector = new $class_name;
      foreach( $expression->stokens as $token)
      {
        if (isset($token->scope) && !$token->scope->is_text)
          continue;
        if (strlen($token->term)>2
          && ($token->modifier & (self::QST_QUOTED|self::QST_WILDCARD))==0
          && strcspn($token->term, '\'0123456789') == strlen($token->term) )
        {
          $token->variants = array_unique( array_diff( $inflector->get_variants($token->term), array($token->term) ) );
        }
      }
    }


    functions_plugins::trigger_notify('qsearch_expression_parsed', $expression);
  //var_export($expression);

    if (count($expression->stokens)==0)
    {
      return $search_results;
    }
    $qsr = new QResults;
    self::qsearch_get_tags($expression, $qsr);
    self::qsearch_get_categories($expression, $qsr);
    self::qsearch_get_images($expression, $qsr);

    // allow plugins to evaluate their own scopes
    functions_plugins::trigger_notify('qsearch_before_eval', $expression, $qsr);

    $ids = self::qsearch_eval($expression, $qsr, $tmp, $search_results['qs']['unmatched_terms']);

    $debug[] = "<!--\nparsed: ".htmlspecialchars($expression);
    $debug[] = count($expression->stokens).' tokens';
    for ($i=0; $i<count($expression->stokens); $i++)
    {
      $debug[] = htmlspecialchars($expression->stokens[$i]).': '.count($qsr->tag_ids[$i]).' tags, '.count($qsr->tag_iids[$i]).' tiids, '.count($qsr->images_iids[$i]).' iiids, '.count($qsr->iids[$i]).' iids'
        .' modifier:'.dechex($expression->stoken_modifiers[$i])
        .( !empty($expression->stokens[$i]->variants) ? ' variants: '.htmlspecialchars(implode(', ',$expression->stokens[$i]->variants)): '');
    }
    $debug[] = 'before perms '.count($ids);

    $search_results['qs']['matching_tags'] = $qsr->all_tags;
    $search_results['qs']['matching_cats'] = $qsr->all_cats;
    $search_results = functions_plugins::trigger_change('qsearch_results', $search_results, $expression, $qsr);
    if (isset($search_results['items']))
    {
      $ids = array_merge($ids, $search_results['items']);
    }
    
    global $template;

    if (empty($ids))
    {
      $debug[] = '-->';
      $template->append('footer_elements', implode("\n", $debug) );
      return $search_results;
    }

    $permissions = !isset($options['permissions']) ? true : $options['permissions'];

    $where_clauses = array();
    $where_clauses[]='i.id IN ('. implode(',', $ids) . ')';
    if (!empty($options['images_where']))
    {
      $where_clauses[]='('.$options['images_where'].')';
    }
    if ($permissions)
    {
      $where_clauses[] = functions_user::get_sql_condition_FandF(
          array
            (
              'forbidden_categories' => 'category_id',
              'forbidden_images' => 'i.id'
            ),
          null,true
        );
    }

    $query = '
  SELECT DISTINCT(id) FROM '.IMAGES_TABLE.' i';
    if ($permissions)
    {
      $query .= '
      INNER JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON id = ic.image_id';
    }
    $query .= '
    WHERE '.implode("\n AND ", $where_clauses)."\n".
    $conf['order_by'];

    $ids = functions_mysqli::query2array($query, null, 'id');

    $debug[] = count($ids).' final photo count -->';
    $template->append('footer_elements', implode("\n", $debug) );

    $search_results['items'] = $ids;
    return $search_results;
  }

  /**
   * Returns an array of 'items' corresponding to the search id.
   * It can be either a quick search or a regular search.
   *
   * @param int $search_id
   * @param bool $super_order_by
   * @param string $images_where optional aditional restriction on images table
   * @return array
   */
  static function get_search_results($search_id, $super_order_by, $images_where='')
  {
    $search = self::get_search_array($search_id);
    if ( !isset($search['q']) )
    {
      return self::get_regular_search_results($search, $images_where);
    }
    else
    {
      return self::get_quick_search_results($search['q'], array('super_order_by'=>$super_order_by, 'images_where'=>$images_where) );
    }
  }

  static function split_allwords($raw_allwords)
  {
    $words = null;

    // we specify the list of characters to trim, to add the ".". We don't want to split words
    // on "." but on ". ", and we have to deal with trailing dots.
    $raw_allwords = trim($raw_allwords, " \n\r\t\v\x00.");

    if (!preg_match('/^\s*$/', $raw_allwords))
    {
      $drop_char_match   = array(';','&','(',')','<','>','`','\'','"','|',',','@','?','%','. ','[',']','{','}',':','\\','/','=','\'','!','*');
      $drop_char_replace = array(' ',' ',' ',' ',' ',' ', '', '', ' ',' ',' ',' ',' ',' ',' ' ,' ',' ',' ',' ',' ','' , ' ',' ',' ', ' ',' ');

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

  static function get_available_search_uuid()
  {
    $candidate = 'psk-'.date('Ymd').'-'.functions_session::generate_key(10);

    $query = '
  SELECT
      COUNT(*)
    FROM '.SEARCH_TABLE.'
    WHERE search_uuid = \''.$candidate.'\'
  ;';
    list($counter) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
    if (0 == $counter)
    {
      return $candidate;
    }
    else
    {
      return self::get_available_search_uuid();
    }
  }

  static function save_search($rules, $forked_from=null)
  {
    global $user;

    list($dbnow) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query('SELECT NOW()'));
    $search_uuid = self::get_available_search_uuid();

    functions_mysqli::single_insert(
      SEARCH_TABLE,
      array(
        'rules' => functions_mysqli::pwg_db_real_escape_string(serialize($rules)),
        'created_on' => $dbnow,
        'created_by' => $user['user_id'],
        'search_uuid' => $search_uuid,
        'forked_from' => $forked_from,
      )
    );

    if (!functions_user::is_a_guest() and !functions_user::is_generic())
    {
      functions_user::userprefs_update_param('gallery_search_filters', array_keys($rules['fields'] ?? array()));
    }

    $url = functions_url::make_index_url(
      array(
        'section' => 'search',
        'search'  => $search_uuid,
      )
    );

    return array($search_uuid, $url);
  }
}

?>
