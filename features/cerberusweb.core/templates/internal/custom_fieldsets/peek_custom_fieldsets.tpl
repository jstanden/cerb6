{$custom_fieldsets_available = DAO_CustomFieldset::getUsableByActorByContext($active_worker, $context)}
{$bulk = $bulk|default:false}
{if !empty($context_id)}
	{$custom_fieldsets_linked = DAO_CustomFieldset::getUsedByContext($context, $context_id)}
	{$custom_fieldsets_editable = array_intersect_key($custom_fieldsets_available, $custom_fieldsets_linked)}
	{$custom_fieldsets_available = array_diff_key($custom_fieldsets_available, $custom_fieldsets_linked)}
	
	{foreach from=$custom_fieldsets_editable item=cf_group}
	{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/fieldset.tpl" bulk=$bulk custom_fieldset=$cf_group}
	{/foreach}
{/if}

<div class="custom-fieldset-insertion"></div>

{if !empty($custom_fieldsets_available)}
{$btn_cfield_group_domid = "cfield_sets_{uniqid()}"}
<div style="margin-left:10px;margin-bottom:10px;">
	<button type="button" id="{$btn_cfield_group_domid}" class="action">{'common.add_fieldset'|devblocks_translate|capitalize} &#x25be;</button>
	<ul class="cerb-popupmenu" style="border:0;">
		<li style="background:none;">
			<input type="text" size="32" class="input_search filter">
		</li>
		{foreach from=$custom_fieldsets_available item=cf_group}
		{$owner_ctx = Extension_DevblocksContext::get($cf_group->owner_context)}
		<li class="item" cf_group_id="{$cf_group->id}">
			<div>
				<a href="javascript:;">
					{$cf_group->name}
				</a>
			</div>
			<div style="margin-left:10px;">
				{$meta = $owner_ctx->getMeta($cf_group->owner_context_id)}
				{$meta.name} ({$owner_ctx->manifest->name})
			</div>
		</li>
		{/foreach}
	</ul>
</div>

<script type="text/javascript">
$('#{$btn_cfield_group_domid}')
	.each(function() {
		var $menu = $(this).siblings('ul.cerb-popupmenu');

		$menu
		.find('> li')
		.click(function(e) {
			e.stopPropagation();
			if(!$(e.target).is('li') && !$(e.target).is('div'))
				return;

			$(this).find('a').trigger('click');
		})
		.find('a')
		.click(function() {
			var $li = $(this).closest('li');
			var $ul = $li.closest('ul.cerb-popupmenu');
			var cf_group_id = $li.attr('cf_group_id');
			
			genericAjaxGet('', 'c=internal&a=handleSectionAction&section=custom_fieldsets&action=getCustomFieldSet&bulk={if !empty($bulk)}1{else}0{/if}&id=' + cf_group_id, function(html) {
				if(undefined == html || null == html)
					return;

				var $at = $('#{$btn_cfield_group_domid}')
					.closest('div')
					.siblings('div.custom-fieldset-insertion')
					;
				
				var $fieldset = $(html);
				
				// If the fieldset we added is removed, add its option back to the menu
				$fieldset.on('custom_fieldset_delete', function(e) {
					var $menu = $('#{$btn_cfield_group_domid}').siblings('ul.cerb-popupmenu');
					var fieldset_id = e.fieldset_id;
					
					$menu.find('li.item').each(function() {
						if($(this).attr('cf_group_id') == e.fieldset_id) {
							$(this).show();
						}
					});
					
					$menu.closest('div').show();
				});
				
				$fieldset.insertBefore($at);
			});
			
			$li.hide();
			
			if($ul.find('> li.item:visible').length == 0)
				$ul.closest('div').hide();
		})
		;
		
		$menu.find('> li > input.filter').keyup(
			function(e) {
				var term = $(this).val().toLowerCase();
				var $fs_menu = $(this).closest('ul.cerb-popupmenu');
				$fs_menu.find('> li.item').each(function(e) {
					if(-1 != $(this).text().toLowerCase().indexOf(term)) {
						$(this).show();
					} else {
						$(this).hide();
					}
				});
			}
		);
		
		$(this).data('menu', $menu);
	})
	.click(function() {
		var $ul = $(this).data('menu');

		$ul.toggle();
		
		if($ul.is(':hidden')) {
			$ul.blur();
		} else {
			$ul.find('input:text').first().focus();
		}
	})
	;
</script>
{/if}
