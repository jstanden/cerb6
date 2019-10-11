<form action="{devblocks_url}{/devblocks_url}" id="frmWorkspacePage{$page->id}" method="POST" style="margin-top:5px;">
	<input type="hidden" name="c" value="internal">
	<input type="hidden" name="a" value="">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

	{$menu = DAO_WorkerPref::getAsJson($active_worker->id, 'menu_json', '[]')}
	{$in_menu = in_array($page->id, $menu)}
	
	<div style="float:left;">
		<h2>{$page->name}</h2>
	</div>
	
	<div style="float:right;">
		{$page_owner_meta = $page->getOwnerMeta()}
		{if !empty($page_owner_meta)}
			<div style="display:inline-block;margin-right:5px;">
				{'common.workspace.page.owner'|devblocks_translate|capitalize}
				<img src="{devblocks_url}c=avatars&context={$page->owner_context}&context_id={$page->owner_context_id}{/devblocks_url}?v={$page_owner_meta.updated}" style="height:1.5em;width:1.5em;border-radius:0.75em;vertical-align:middle;">
				<b>
				{if $page->owner_context_id} 
				<a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{$page->owner_context}" data-context-id="{$page->owner_context_id}">{$page_owner_meta.name}</a>
				{else}
				{$page_owner_meta.name}
				{/if}
				</b>
			</div>
		{/if}
	
		<button class="add" type="button" page_id="{$page->id}" page_label="{$page->name|lower}" page_url="{devblocks_url}c=pages&page={$page->id}-{$page->name|devblocks_permalink}{/devblocks_url}">{if $in_menu}<span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span>{else}<span class="glyphicons glyphicons-circle-plus" style="color:rgb(0,180,0);"></span>{/if} Menu</button>
	
		{if Context_WorkspacePage::isWriteableByActor($page, $active_worker)}
		<div style="display:inline-block;">
			<button class="config-page split-left" type="button"><span class="glyphicons glyphicons-cogwheel"></span></button><!--
			--><button class="config-page split-right" type="button"><span class="glyphicons glyphicons-chevron-down" style="font-size:12px;color:white;"></span></button>
			<ul class="cerb-popupmenu cerb-float">
				{if Context_WorkspacePage::isWriteableByActor($page, $active_worker)}
					{if $active_worker->hasPriv("contexts.{CerberusContexts::CONTEXT_WORKSPACE_PAGE}.update")}<li><a href="javascript:;" class="edit-page" data-context="{CerberusContexts::CONTEXT_WORKSPACE_PAGE}" data-context-id="{$page->id}" data-edit="true">{'common.workspace.page.edit'|devblocks_translate|capitalize}</a></li>{/if}
					{if $page->extension_id == 'core.workspace.page.workspace' && $active_worker->hasPriv("contexts.{CerberusContexts::CONTEXT_WORKSPACE_TAB}.update")}<li><a href="javascript:;" class="edit-tab" data-context="{CerberusContexts::CONTEXT_WORKSPACE_TAB}" data-context-id="" data-edit="true">{'common.workspace.tab.edit'|devblocks_translate|capitalize}</a></li>{/if}
				{/if}
				{if $active_worker->hasPriv("contexts.{CerberusContexts::CONTEXT_WORKSPACE_PAGE}.export")}<li><a href="javascript:;" class="export-page">{'common.workspace.page.export'|devblocks_translate|capitalize}</a></li>{/if}
				{if $page->extension_id == 'core.workspace.page.workspace' && $active_worker->hasPriv("contexts.{CerberusContexts::CONTEXT_WORKSPACE_TAB}.export")}<li><a href="javascript:;" class="export-tab">{'common.workspace.tab.export'|devblocks_translate|capitalize}</a></li>{/if}
			</ul>
		</div>
		{/if}
	</div>

	<div style="clear:both;"></div>
</form>

<div style="margin-top:5px;">
	{if $page_extension instanceof Extension_WorkspacePage}
		{$page_extension->renderPage($page)}
	{/if}
</div>

<script type="text/javascript">
$(function() {
	var $workspace = $('#frmWorkspacePage{$page->id}');
	var $frm = $('form#frmWorkspacePage{$page->id}');
	var $menu = $frm.find('ul.cerb-popupmenu');
	
	// Form
	
	$frm.find('.cerb-peek-trigger')
		.cerbPeekTrigger()
		;
	
	// Menu
	
	$menu
		.hover(
			function() {
			},
			function() {
				$(this).hide();
			}
		)
		;
	
	$menu.find('> li').click(function(e) {
		if($(e.target).is('a'))
			return;
		
		e.stopPropagation();
		$(this).find('> a').click();
	});
	
	$menu.siblings('button.config-page').click(function(e) {
		var $menu = $(this).siblings('ul.cerb-popupmenu');
		$menu.toggle();
		
		if($menu.is(':visible')) {
			var $div = $menu.closest('div');
			$menu.css('left', $div.position().left + $div.outerWidth() - $menu.outerWidth());
		}
	});
	
	// Edit workspace actions
	
	{if Context_WorkspacePage::isWriteableByActor($page, $active_worker)}
		// Edit page
		$workspace.find('a.edit-page')
			.cerbPeekTrigger()
			.on('cerb-peek-saved', function() {
				window.location.href = '{devblocks_url}c=pages&id={$page->id}-{$page->name|devblocks_permalink}{/devblocks_url}';
			})
			.on('cerb-peek-deleted', function() {
				window.location.href = '{devblocks_url}c=pages{/devblocks_url}';
			})
			;
		
		// Edit tab
		$workspace.find('a.edit-tab')
			.cerbPeekTrigger()
			.on('cerb-peek-saved', function(e) {
				e.stopPropagation();
				
				var $tabs = $("#pageTabs{$page->id}");
				var $selected_tab = $tabs.find('li.ui-tabs-active').first();
				
				if(0 == $selected_tab.length)
					return;
				
				var tab_id = $selected_tab.attr('tab_id');
				
				// On this page
				if(e.page_id == {$page->id}) {
					if(0 != $tabs) {
						var selected_idx = $tabs.tabs('option','active');
						$tabs.tabs('load', selected_idx);
						
						if(null != e.label) {
							var $selected_tab = $tabs.find('> ul > li.ui-tabs-active');
							$selected_tab.find('a').text(e.label);
						}
					}
					
				} else { // If moved to another page, remove the tab
					var evt = jQuery.Event('cerb-peek-deleted');
					evt.id = e.id;
					evt.label = e.label;
					$(this).trigger(evt);
				}
				
			})
			.on('cerb-peek-deleted', function(e) {
				e.stopPropagation();
				
				var $tabs = $("#pageTabs{$page->id}");
				var $selected_tab = $tabs.find('li.ui-tabs-active').first();
				
				if(0 == $selected_tab.length)
					return;
				
				var tab_id = $selected_tab.attr('tab_id');
				
				if(0 != $tabs.length) {
					var tab = $tabs.find('.ui-tabs-nav li:eq(' + $tabs.tabs('option','active') + ')').remove();
					var panelId = tab.attr('aria-controls');
					$('#' + panelId).remove();
					$tabs.tabs('refresh');
				}
			})
			;
	{/if}
	
	// Export page
	$workspace.find('a.export-page').click(function(e) {
		e.stopPropagation();
		genericAjaxPopup('peek','c=pages&a=showExportWorkspacePage&id={$page->id}',null,true,'600');
	});
	
	// Export tab
	$workspace.find('a.export-tab').click(function(e) {
		e.stopPropagation();
		
		var $tabs = $("#pageTabs{$page->id}");
		var $selected_tab = $tabs.find('li.ui-tabs-active').first();
		
		if(0 == $selected_tab.length)
			return;
		
		var tab_id = $selected_tab.attr('tab_id');
		
		if(null == tab_id)
			return;
		
		genericAjaxPopup('peek','c=pages&a=showExportWorkspaceTab&id=' + encodeURIComponent(tab_id),null,true,'600');
	});
	
	// Add/Remove in menu
	$workspace.find('button.add').click(function(e) {
		var $this = $(this);
		var $menu = $('BODY UL.navmenu:first');
		var $item = $menu.find('li.drag[page_id="'+$this.attr('page_id')+'"]');
		
		// Remove
		if(1 == $item.length) {
			// Is the page already in the menu?
			$item.css('visibility','hidden');
			
			$item.effect('transfer', { to:$this, className:'effects-transfer' }, 500, function() {
				$(this).remove();
			});
			
			$this.html('<span class="glyphicons glyphicons-circle-plus" style="color:rgb(0,180,0);"></span> Menu');
			genericAjaxGet('', 'c=pages&a=doToggleMenuPageJson&page_id=' + $this.attr('page_id') + '&toggle=0');
			
		// Add
		} else {
			// Add the menu item if it doesn't exist (e.g. removed on this page cycle)
			var $li = $('<li class="drag"/>').attr('page_id',$this.attr('page_id'));
			$li.append($('<a/>').attr('href',$this.attr('page_url')).text($this.attr('page_label')));
			
			$li
				.css('visibility','hidden')
				.addClass('selected')
				;
			
			var $marker = $menu.find('li.add');
			
			if(0 == $marker.length) {
				$li.prependTo($menu);
				
			} else {
				$li.insertBefore($marker);
				
			}
			
			$this.effect('transfer', { to:$li, className:'effects-transfer' }, 500, function() {
				$li.css('visibility','visible');
			});
			
			$this.html('<span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span> Menu');
			genericAjaxGet('', 'c=pages&a=doToggleMenuPageJson&page_id=' + $this.attr('page_id') + '&toggle=1');
		}
	});
	
});
</script>
