{if !empty($block.MAX_WIDTH) or !empty($block.MAX_HEIGHT) or !empty($block.NB_COMMENTS_LINE)}
    {html_head}
    <style>
        {if !empty($block.MAX_WIDTH)}
            .stuffs #comments img { max-width:{$block.MAX_WIDTH}px; }
        {/if}
        {if !empty($block.MAX_HEIGHT)}
            .stuffs #comments img { max-height:{$block.MAX_HEIGHT}px; }
        {/if}
        {if !empty($block.NB_COMMENTS_LINE)}
            .stuffs #comments li { width:{$block.NB_COMMENTS_LINE} !important; }
        {/if}
    </style>
    {/html_head}
{/if}

<div id="comments">
    {assign var=comments value=$block.comments}
    {assign var='derivative_params' value=$block.derivative_params}
    {include file='comment_list.tpl'}
</div>