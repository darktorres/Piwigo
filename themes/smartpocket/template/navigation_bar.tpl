<div data-role="footer" class="ui-bar ui-bar-a" style="text-align:center;">
  <div data-role="controlgroup" data-type="horizontal">
    {if isset($navbar.URL_PREV)}
      <a href="{$navbar.URL_PREV}" rel="prev" data-role="button" data-icon="arrow-l"
        data-inline="true">{'Previous'|@translate}</a>
    {/if}
    <a href="#" data-role="button" data-inline="true">{$navbar.CURRENT_PAGE} / {$navbar.NB_PAGE}&nbsp;</a>
    {if isset($navbar.URL_NEXT)}
      <a href="{$navbar.URL_NEXT}" rel="next" data-role="button" data-icon="arrow-r" data-iconpos="right"
        data-inline="true">{'Next'|@translate}</a>
    {/if}
  </div>
</div>