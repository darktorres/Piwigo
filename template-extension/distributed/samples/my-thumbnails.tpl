<!-- This is a sample of template extensions -->
{if !empty($thumbnails)}
	<ul class="thumbnails">
		{foreach $thumbnails as $thumbnail}
			<li>
				<span class="wrap1">
					<span class="wrap2">
						<a href="{$thumbnail.URL}">
							<img class="thumbnail" src="{$thumbnail.TN_SRC}" alt="{$thumbnail.TN_ALT}"
								title="{$thumbnail.TN_TITLE}">
						</a>
					</span>
					<span class="thumbLegend" style="color:#F36;">
						&copy; 2008 Piwigo<br>
						{if !empty($thumbnail.NAME)}{$thumbnail.NAME}{/if}
						{if !empty($thumbnail.ICON_TS)}{$thumbnail.ICON_TS}{/if}

						{if isset($thumbnail.NB_COMMENTS)}
							<span class="{if 0==$thumbnail.NB_COMMENTS}zero {/if}nb-comments">
								<br>
								{$thumbnail.NB_COMMENTS|translate_dec:'%d comment':'%d comments'}
							</span>
						{/if}

						{if isset($thumbnail.NB_HITS)}
							<span class="{if 0==$thumbnail.NB_HITS}zero {/if}nb-hits">
								<br>
								{$thumbnail.NB_HITS|translate_dec:'%d hit':'%d hits'}
							</span>
						{/if}
					</span>
				</span>
			</li>
		{/foreach}
	</ul>
{/if}