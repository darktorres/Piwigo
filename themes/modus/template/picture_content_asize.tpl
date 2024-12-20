{combine_script id='photo.autosize' path="themes/`$themeconf.id`/js/photo.autosize.js" load='footer' require='jquery'}

{footer_script}<script>
  RVAS = {
      derivatives: [
        {foreach from=$current.unique_derivatives item=derivative name=derivative_loop}
          {if 'svg' === $current.path_ext}
            {assign var='size' value=array($current.width, $current.height)}
          {else}
            {assign var='size' value=$derivative->get_size()}
          {/if}
          { w:{$size[0]},h:{$size[1]},url:'{$derivative->get_url()|@escape:'javascript'}',type:'{$derivative->get_type()}' }{if !$smarty.foreach.derivative_loop.last},{/if}
        {/foreach}],
        cp: '{$COOKIE_PATH|@escape:'javascript'}'
      }
</script>{/footer_script}
{if $RVAS_PENDING}
  <noscript><img src="{$current.selected_derivative->get_url()}" {$current.selected_derivative->get_size_htm()}
      alt="{$ALT_IMG}" id="theMainImage" usemap="#map{$current.selected_derivative->get_type()}"
      title="{if isset($COMMENT_IMG)}{$COMMENT_IMG|@strip_tags:false|@replace:'"':' '}{else}{$current.TITLE_ESC} - {$ALT_IMG}{/if}"
    itemprop=contentURL></noscript>
{footer_script}<script>
  rvas_choose();
</script>{/footer_script}
{else}
{footer_script}<script>
  rvas_choose(1);
</script>{/footer_script}
{/if}

{if isset($current.path_ext) and $current.path_ext == 'pdf' and isset($PDF_VIEWER_FILESIZE_THRESHOLD) and $current.filesize < $PDF_VIEWER_FILESIZE_THRESHOLD}
<div>
  <embed src="{$ROOT_URL}{$current.path}" type="application/pdf"
    style='width: 95%; height:calc(100vh - 200px); min-height:600px;' />
</div>
{else}
<img
  class="file-ext-{if isset($current.file_ext)}{$current.file_ext}{/if} path-ext-{if isset($current.path_ext)}{$current.path_ext}{/if}"
  {if (isset($current.path_ext) and $current.path_ext == 'svg')} src="{$current.path}"
  {else}src="{$current.selected_derivative->get_url()}" {$current.selected_derivative->get_size_htm()} loading="lazy"
  decoding="async" {/if} alt="{$ALT_IMG}" id="theMainImage" usemap="#map{$current.selected_derivative->get_type()}"
  title="{if isset($COMMENT_IMG)}{$COMMENT_IMG|@strip_tags:false|@replace:'"':' '}{else}{$current.TITLE_ESC} - {$ALT_IMG}{/if}">

  {if isset($current.path_ext) and $current.path_ext == 'pdf' and isset($PDF_VIEWER_FILESIZE_THRESHOLD) and $current.filesize > $PDF_VIEWER_FILESIZE_THRESHOLD}
    <div class="pdf-too-heavy">
      {'The PDF you requested is too large to display on this page.'|translate}</br>
      <a href="{$ROOT_URL}{$current.path}" target="_blank">{'Click here to display it'|translate}</a>
    </div>
  {/if}
{/if}

{foreach $current.unique_derivatives as $derivative_type => $derivative}
  <map name="map{$derivative->get_type()}">
    {assign var='size' value=$derivative->get_size()}
    {if isset($previous)}
      <area shape=rect coords="0,0,{($size[0]/4)|@intval},{$size[1]}" href="{$previous.U_IMG}"
        title="{'Previous'|@translate} : {$previous.TITLE_ESC}" alt="{$previous.TITLE_ESC}">
    {/if}
    <area shape=rect coords="{($size[0]/4)|@intval},0,{($size[0]/1.34)|@intval},{($size[1]/4)|@intval}" href="{$U_UP}"
      title="{'Thumbnails'|@translate}" alt="{'Thumbnails'|@translate}">
    {if isset($next)}
      <area shape=rect coords="{($size[0]/1.33)|@intval},0,{$size[0]},{$size[1]}" href="{$next.U_IMG}"
        title="{'Next'|@translate} : {$next.TITLE_ESC}" alt="{$next.TITLE_ESC}">
    {/if}
  </map>
{/foreach}