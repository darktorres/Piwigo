<dt>{'Specials'|@translate}</dt>
<dd>
	<ul>{strip}
		{foreach $block->data as $link}
		<li><a href="{$link.URL}" title="{$link.TITLE}"{if isset($link.REL)} {$link.REL}{/if}>{$link.NAME}</a></li>
		{/foreach}
	{/strip}</ul>
</dd>
