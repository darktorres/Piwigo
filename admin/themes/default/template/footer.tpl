{* 
          Warning : This is the admin pages footer only 
          don't be confusing with the public page footer
*}
</div>{* <!-- pwgMain --> *}

{if isset($footer_elements)}
  {foreach $footer_elements as $elt}
    {$elt}
  {/foreach}
{/if}

{if isset($debug.QUERIES_LIST)}
  <div id="debug">
    {$debug.QUERIES_LIST}
  </div>
{/if}

<div id="footer">
  <a class="externalLink tiptip piwigo-logo" href="{$PHPWG_URL}" title="{'Visit Piwigo project website'|translate}"><img
      src="admin/themes/default/images/piwigo-grey.svg"></a>
  <div id="pageInfos">
    {if isset($debug.TIME) }
      {'Page generated in'|translate} {$debug.TIME} ({$debug.NB_QUERIES} {'SQL queries in'|translate} {$debug.SQL_TIME}) -
    {/if}

    {'Contact'|translate}
    <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|escape:url}">{'Webmaster'|translate}</a>
  </div>{* <!-- pageInfos --> *}

</div>{* <!-- footer --> *}
</div>{* <!-- the_page --> *}


{combine_script id='jquery.tipTip' load='footer' require='jquery' path='https://raw.githack.com/drewwilson/TipTip/refs/heads/master/jquery.tipTip.js'}
{footer_script require='jquery.tipTip'}<script>
  jQuery('.tiptip').tipTip({
    delay: 0,
    fadeIn: 200,
    fadeOut: 200
  });

  jQuery('a.externalLink').on("click", function() {
    window.open(jQuery(this).attr("href"));
    return false;
  });
</script>{/footer_script}

<!-- BEGIN get_combined -->
{get_combined_scripts load='footer'}
<!-- END get_combined -->

</body>

</html>