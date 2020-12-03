{function toolbar_menu level=0}
    {foreach from=$items item=item key=item_key}
        {$item_key_parts = explode('/', $item_key)}
        {if !$item.hidden}
            {if 'menu' == $item_key_parts[0]}
                <li>
                    {if $item.icon}
                        <span class="glyphicons glyphicons-{$item.icon}"></span>
                    {/if}
                    {$item.label}
                    {if $item.items}
                    <ul>
                        {toolbar_menu items=$item.items}
                    </ul>
                    {/if}
                </li>
            {elseif 'interaction' == $item_key_parts[0]}
                <li class="cerb-bot-trigger"
                    data-interaction-uri="{$item.uri}"
                    data-interaction-params="{if is_array($item.inputs)}{DevblocksPlatform::services()->url()->arrayToQueryString($item.inputs)}{/if}"
                    data-interaction-done="{if is_array($item['after'])}{DevblocksPlatform::services()->url()->arrayToQueryString($item['after'])}{/if}"
                    {if $item.headless}data-interaction-headless="true"{/if}
                    >
                    {if $item.icon}
                        <span class="glyphicons glyphicons-{$item.icon}"></span>
                    {/if}
                    <b>{$item.label}</b>
                </li>
            {/if}
        {/if}
    {/foreach}
{/function}

{foreach from=$toolbar item=toolbar_item}
    {if !$toolbar_item.schema.hidden}
        {if 'interaction' == $toolbar_item.type}
            {if $toolbar_item.schema.uri}
                <button type="button" class="cerb-bot-trigger"
                        data-cerb-toolbar-button
                        data-interaction-uri="{$toolbar_item.schema.uri}"
                        data-interaction-params="{if is_array($toolbar_item.schema.inputs)}{DevblocksPlatform::services()->url()->arrayToQueryString($toolbar_item.schema.inputs)}{/if}"
                        data-interaction-done="{if is_array($toolbar_item.schema['after'])}{DevblocksPlatform::services()->url()->arrayToQueryString($toolbar_item.schema['after'])}{/if}"
                        {if $toolbar_item.schema.tooltip}title="{$toolbar_item.schema.tooltip}"{/if}
                        {if $toolbar_item.schema.headless}data-interaction-headless="true"{/if}
                        >
                    {if !is_null($toolbar_item.schema.badge)}
                        <div class="badge-count">{$toolbar_item.schema.badge}</div>
                    {/if}
                    {if $toolbar_item.schema.icon}
                        <span class="glyphicons glyphicons-{$toolbar_item.schema.icon}"></span>
                    {/if}
                    {$toolbar_item.schema.label}
                </button>
            {/if}
        {elseif 'menu' == $toolbar_item.type}
            {$item_key_parts = explode('/', $toolbar_item.schema.default)}
            {$default = $toolbar_item.schema.items[$toolbar_item.schema.default]}

            {* Split menu button *}
            {if $default}
                <button type="button" class="split-left cerb-bot-trigger"
                        data-cerb-toolbar-button
                        data-interaction-uri="{$default.uri}"
                        data-interaction-params="{if is_array($default.inputs)}{DevblocksPlatform::services()->url()->arrayToQueryString($default.inputs)}{/if}"
                        data-interaction-done="{if is_array($default['after'])}{DevblocksPlatform::services()->url()->arrayToQueryString($default['after'])}{/if}"
                        {if $default.label}title="{$default.label}"{/if}
                        >
                    {if !is_null($default.schema.badge)}
                    <div class="badge-count">{$toolbar_item.schema.badge}</div>
                    {/if}
                    {if $toolbar_item.schema.icon}
                    <span class="glyphicons glyphicons-{$toolbar_item.schema.icon}"></span>
                    {/if}
                    {$toolbar_item.schema.label}
                </button><button type="button" class="split-right" data-cerb-toolbar-menu {if $toolbar_item.schema.hover}data-cerb-toolbar-menu-hover{/if}>
                    <span class="glyphicons glyphicons-chevron-down" style="font-size:12px;color:white;"></span>
                </button>
            {else}
                <button type="button" 
                        data-cerb-toolbar-menu 
                        {if $toolbar_item.schema.tooltip}title="{$toolbar_item.schema.tooltip}"{/if} 
                        {if $toolbar_item.schema.hover}data-cerb-toolbar-menu-hover{/if}
                        >
                    {if !is_null($toolbar_item.schema.badge)}
                        <div class="badge-count">{$toolbar_item.schema.badge}</div>
                    {/if}
                    {if $toolbar_item.schema.icon}
                        <span class="glyphicons glyphicons-{$toolbar_item.schema.icon}"></span>
                    {/if}
                    {$toolbar_item.schema.label}
                </button>
            {/if}
            <ul class="cerb-float" style="display:none;text-align:left;">
                {toolbar_menu items=$toolbar_item.schema.items}
            </ul>
        {/if}
    {/if}
{/foreach}
