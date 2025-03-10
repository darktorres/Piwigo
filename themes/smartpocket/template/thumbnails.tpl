{if !empty($thumbnails)}
	{$row_height=216}
	{$hmargin=4}
	{$vmargin=5}
	{$container_margin=-10}

	{combine_script id='jquery' path='https://cdnjs.cloudflare.com/ajax/libs/jquery/1.11.3/jquery.js'}
	{combine_script id='klass' path='https://raw.githack.com/Piwigo/piwigo-smartpocket/dea72053d0d5d3ec1ec61fccb58dac35ad9c8bf5/js/klass.min.js'}
	{combine_script id='photoswipe' path='https://raw.githack.com/Piwigo/piwigo-smartpocket/dea72053d0d5d3ec1ec61fccb58dac35ad9c8bf5/js/code.photoswipe.jquery.min.js' require='klass,jquery.mobile'}
	{combine_script id='smartpocket' path='themes/smartpocket/js/smartpocket.js' require='photoswipe' load='footer'}
	{combine_script id='sp.thumb.arrange' path='themes/smartpocket/js/thumb.arrange.js' require='jquery' load='footer'}
	{footer_script}<script>
		var var_loop = {if $smartpocket.loop}true{else}false{/if}, var_autohide = {$smartpocket.autohide}, var_trad = "{'More Information'|@translate}";
		var SPThumbsOpts ={ hMargin:{$hmargin},rowHeight:{$row_height}};
	</script>{/footer_script}
	{$thumb_picker->init($row_height)}
	{html_style}<style>
		.thumbnails .liEmpty {
			display: none
		}

		.thumbnails LI{ margin-left:{$hmargin}px; margin-bottom:{$vmargin}px}
		.thumbnails { margin:0 {$container_margin}px 0 {$container_margin-$hmargin}px}
	</style>{/html_style}
	<ul class="thumbnails" {if !empty($smartpocket_log_history.cat_id)}data-cat_id="{$smartpocket_log_history.cat_id}" {/if}
		{if !empty($smartpocket_log_history.section)}data-section="{$smartpocket_log_history.section}" {/if}
		{if !empty($smartpocket_log_history.tags_string)}data-tags_string="{$smartpocket_log_history.tags_string}" {/if}>
		{foreach from=$thumbnails item=thumbnail}
			{$derivative=$thumb_picker->pick($thumbnail.src_image, $row_height)}
			{if isset($page_selection[$thumbnail.id])}
				<li class="liVisible">
					{if !isset($thumbnail.representative_ext)}
						<a href="{$pwg->derivative_url($picture_derivative_params, $thumbnail.src_image)}"
							data-picture-url="{$thumbnail.URL}" data-image-id="{$thumbnail.id}" rel="external">
						{else}
							<a href="{$thumbnail.URL}" target="_blank" onClick="window.location='{$thumbnail.URL}'">
							{/if}
							<img src="{$derivative->get_url()}" {$derivative->get_size_htm()} alt="{$thumbnail.TN_ALT}">
						{else}
				<li class="liEmpty">
					<a href="{$pwg->derivative_url($picture_derivative_params, $thumbnail.src_image)}" rel="external">
					{/if}
				</a>
			</li>
		{/foreach}
	</ul>
{/if}