<dt>{'Links'|@translate}</dt>
<dd>
	<ul>
		{foreach $block->data as $link}
			<li>
				<a href="{$link.URL}" class="external" {if isset($link.new_window)}
						onclick="window.open(this.href, '{$link.new_window.NAME}','{$link.new_window.FEATURES}'); return false;"
					{/if}>
					{$link.LABEL}
				</a>
			</li>
		{/foreach}
	</ul>
</dd>