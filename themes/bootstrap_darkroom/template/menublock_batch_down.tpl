<li class="nav-item dropdown" id="bd_downloads">
  <a class="nav-link dropdown-toggle" data-toggle="dropdown">{$block->get_title()}</a>
  <ul class="dropdown-menu dropdown-menu-right">
    {foreach $block->data as $link}
      <li>
        <a href="{$link.URL}" title="{$link.TITLE}" rel="nofollow" class="dropdown-item">
          {$link.NAME}
          <span class="badge badge-secondary ml-2">{$link.COUNT}</span>
        </a>
      </li>
    {/foreach}
  </ul>
</li>