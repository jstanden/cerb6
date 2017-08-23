{$peek_context = CerberusContexts::CONTEXT_BEHAVIOR}
<form id="frmDecisionBehavior{$model->id}" onsubmit="return false;">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="behavior">
<input type="hidden" name="action" value="savePeekJson">
<input type="hidden" name="view_id" value="{$view_id}">
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="id" value="{$model->id|default:0}">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate}</legend>
	
	<table cellspacing="0" cellpadding="2" border="0" width="98%">
		{if !$model->id}
		<tr>
			<td width="100%" colspan="2">
				<label><input type="radio" name="mode" value="build" checked="checked"> Build</label>
				<label><input type="radio" name="mode" value="import"> {'common.import'|devblocks_translate|capitalize}</label>
			</td>
		</tr>
		{/if}
		
		<tr>
			<td width="1%" nowrap="nowrap"><b>{'common.bot'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{if !$model->id}
					<button type="button" class="chooser-abstract" data-field-name="bot_id" data-context="{CerberusContexts::CONTEXT_BOT}" data-single="true" data-autocomplete="" data-autocomplete-if-empty="true"><span class="glyphicons glyphicons-search"></span></button>
					
					<ul class="bubbles chooser-container">
						{if $bot}
							<li><img class="cerb-avatar" src="{devblocks_url}c=avatars&context=bot&context_id={$bot->id}{/devblocks_url}?v={$bot->updated_at}"><input type="hidden" name="bot_id" value="{$bot->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_BOT}" data-context-id="{$bot->id}">{$bot->name}</a></li>
						{/if}
					</ul>
				{else}
					{if $bot}
						<ul class="bubbles chooser-container">
							<li><img class="cerb-avatar" src="{devblocks_url}c=avatars&context=bot&context_id={$bot->id}{/devblocks_url}?v={$bot->updated_at}"><input type="hidden" name="bot_id" value="{$bot->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_BOT}" data-context-id="{$bot->id}">{$bot->name}</a></li>
						</ul>
					{/if}
				{/if}
			</td>
		</tr>
		
		<tbody class="behavior-import" style="display:none;">
			<tr>
				<td width="100%" colspan="2">
					<textarea name="import_json" style="width:100%;height:250px;white-space:pre;word-wrap:normal;" rows="10" cols="45" spellcheck="false" placeholder="Paste a behavior in JSON format"></textarea>
				</td>
			</tr>
		</tbody>
		
		<tbody class="behavior-build">
			<tr class="behavior-event">
				<td width="1%" nowrap="nowrap" valign="top">
					<b>{'common.event'|devblocks_translate|capitalize}:</b>
				</td>
				<td width="99%">
					{if $ext}
						<ul class="bubbles">
							<li>{$ext->manifest->name}</li>
						</ul>
					{else}
					<div class="events-widget" style="display:none;">
						{if $events_menu}
							{include file="devblocks:cerberusweb.core::internal/peek/menu_behavior_event.tpl"}
						{else}
							(choose a bot to see available events)
						{/if}
					</div>
					{/if}
					
					<div class="event-params">
					{if $ext && method_exists($ext,'renderEventParams')}
					{$ext->renderEventParams($model)}
					{/if}
					</div>
				</td>
			</tr>
			
			<tr>
				<td width="1%" nowrap="nowrap"><b>{'common.name'|devblocks_translate|capitalize}:</b></td>
				<td width="99%">
					<input type="text" name="title" value="{$model->title}" style="width:100%;" autocomplete="off" spellcheck="false" autofocus="autofocus"><br>
				</td>
			</tr>
			
			<tr>
				<td width="1%" nowrap="nowrap"><b>{'common.priority'|devblocks_translate|capitalize}:</b></td>
				<td width="99%">
					<input type="text" name="priority" value="{$model->priority|default:50}" placeholder="50" maxlength="2" style="width:50px" autocomplete="off" spellcheck="false">
				</td>
			</tr>
			
			<tr>
				<td width="1%" nowrap="nowrap"><b>{'common.status'|devblocks_translate|capitalize}:</b></td>
				<td width="99%">
					<label><input type="radio" name="is_disabled" value="0" {if empty($model->is_disabled)}checked="checked"{/if}> {'common.enabled'|devblocks_translate|capitalize}</label>
					<label><input type="radio" name="is_disabled" value="1" {if !empty($model->is_disabled)}checked="checked"{/if}> {'common.disabled'|devblocks_translate|capitalize}</label>
				</td>
			</tr>
			
		</tbody>
		
	</table>
</fieldset>

<fieldset class="peek behavior-variables">
	<legend style="color:inherit;">{'common.variables'|devblocks_translate|capitalize}</legend>
	
	<div id="divBehaviorVariables{$model->id}">
	{foreach from=$model->variables key=k item=var name=vars}
		{$seq = uniqid()}
		{include file="devblocks:cerberusweb.core::internal/decisions/editors/trigger_variable.tpl" seq=$seq}
	{/foreach}
	</div>
	
	<div style="margin:5px 0px 10px 20px;">
		<button type="button" class="add-variable cerb-popupmenu-trigger">{'common.add'|devblocks_translate|capitalize} &#x25be;</button>
		
		{function menu level=0}
			{foreach from=$keys item=data key=idx}
				{if is_array($data->children) && !empty($data->children)}
					<li {if $data->key}data-token="{$data->key}" data-label="{$data->label}"{/if}>
						{if $data->key}
							<div style="font-weight:bold;">{$data->l|capitalize}</div>
						{else}
							<div>{$idx|capitalize}</div>
						{/if}
						<ul style="">
							{menu keys=$data->children level=$level+1}
						</ul>
					</li>
				{elseif $data->key}
					{$item_context = explode(':', $data->key)}
					<li data-token="{$data->key}" data-label="{$data->label}">
						<div style="font-weight:bold;">
							{$data->l|capitalize}
						</div>
					</li>
				{/if}
			{/foreach}
		{/function}
		
		<ul class="chooser-container bubbles"></ul>
		
		<ul class="add-variable-menu" style="width:150px;{if $model->event_point}display:none;{/if}">
		{menu keys=$variables_menu}
		</ul>
	</div>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_BEHAVIOR context_id=$model->id}

{if isset($model->id)}
<fieldset class="delete" style="display:none;">
	<legend>Delete this behavior?</legend>
	<p>Are you sure you want to permanently delete this behavior and all of its effects?</p>
	
	<button type="button" class="delete red"></span> {'common.yes'|devblocks_translate|capitalize}</button>
	<button type="button" onclick="$(this).closest('form').find('div.buttons').fadeIn();$(this).closest('fieldset.delete').fadeOut();"></span> {'common.no'|devblocks_translate|capitalize}</button>
</fieldset>
{/if}

<div class="config"></div>

<div class="status"></div>

<div class="buttons">
	<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
	{if $model->id && $active_worker->hasPriv("contexts.{$peek_context}.delete")}<button type="button" onclick="$(this).parent().siblings('fieldset.delete').fadeIn();$(this).closest('div').fadeOut();"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}
</div>

</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#frmDecisionBehavior{$model->id}');
	var $popup = genericAjaxPopupFind($frm);
	
	$popup.one('popup_open', function(event,ui) {
		$popup.dialog('option','title',"{'common.behavior'|devblocks_translate|capitalize|escape:'javascript'}");
		$popup.css('overflow', 'inherit');
		
		$popup.find('.chooser-abstract')
			.cerbChooserTrigger()
			.on('cerb-chooser-saved', function(e) {
				var $btn = $(e.target);
				var $ul = $btn.siblings('ul.chooser-container');
				
				// We're adding or swapping bots
				if($ul.find('li').length > 0) {
					// Load the events from Ajax by bot ID
					var $hidden = $ul.find('li input[name=bot_id]');
					var bot_id = $hidden.val();
					
					genericAjaxGet('', 'c=profiles&a=handleSectionAction&section=behavior&action=getEventsMenuByBot&bot_id=' + bot_id, function(html) {
						$popup.find('div.events-widget').html(html).fadeIn();
						$popup.trigger('events-menu-refresh');
					});
				
				// We removed all bots
				} else {
					var $events_menu = $popup.find('ul.events-menu').hide();
					$events_menu.siblings('ul.chooser-container').hide();
					$frm.find('div.event-params').hide();
				}
			})
			;
		
		$popup.find('.cerb-peek-trigger').cerbPeekTrigger();
		
		var checkForConfigForm = function(json) {
			if(json.config_html) {
				//$frm.find('div.import').hide();
				$frm.find('div.config').hide().html(json.config_html).fadeIn();
			}
		};
		
		$popup.find('button.submit').click({ after: checkForConfigForm }, Devblocks.callbackPeekEditSave);
		$popup.find('button.delete').click({ mode: 'delete' }, Devblocks.callbackPeekEditSave);
		
		$popup.find('fieldset.behavior-variables')
			.sortable({ 'items':'FIELDSET', 'placeholder':'ui-state-highlight', 'handle':'legend' })
			;
		
		var $variables = $('#divBehaviorVariables{$model->id}');
		
		var $menu_variables = $popup.find('ul.add-variable-menu')
			.menu({
				'select': function(event, ui) {
					var $li = $(ui.item);
					var field_type = $li.attr('data-token');
					
					if(null != field_type) {
						genericAjaxGet('', 'c=internal&a=addTriggerVariable&type=' +  encodeURIComponent(field_type), function(o) {
							var $html = $(o).appendTo($variables);
						});
					}
				}
			})
		;
		
		$popup.find('BUTTON.add-variable').click(function() {
			var $button = $(this);
			$menu_variables.toggle();
		});
		
		$popup.find('input:radio[name=mode]').change(function() {
			var $radio = $(this);
			var mode = $radio.val();
			
			if(mode == 'import') {
				$frm.find('fieldset.behavior-variables').hide();
				$frm.find('tbody.behavior-build').hide();
				$frm.find('tbody.behavior-import').fadeIn();
			} else {
				$frm.find('tbody.behavior-import').hide();
				$frm.find('tbody.behavior-build').fadeIn();
				$frm.find('fieldset.behavior-variables').fadeIn();
			}
		});

		// Events

		$popup.on('events-bubble-remove', function(e, ui) {
			var $events_menu = $popup.find('ul.events-menu');
			var $events_ul = $events_menu.siblings('ul.chooser-container');
			
			e.stopPropagation();
			$(e.target).closest('li').remove();
			$events_ul.hide();
			$events_menu.show();
			$frm.find('div.event-params').hide();
		});
		
		$popup.on('events-menu-refresh', function(e, ui) {
			var $events_menu = $popup.find('ul.events-menu');
			var $events_ul = $events_menu.siblings('ul.chooser-container');
			
			$events_menu.menu({
				select: function(event, ui) {
					var token = ui.item.attr('data-token');
					var label = ui.item.attr('data-label');
					
					if(undefined == token || undefined == label)
						return;
					
					$events_menu.hide();
					
					// Build bubble
					
					var $li = $('<li/>');
					var $label = $('<span/>').attr('data-event',token).text(label);
					$label.appendTo($li);
					var $hidden = $('<input type="hidden">').attr('name', 'event_point').attr('value',token).appendTo($li);
					var $a = $('<a href="javascript:;" onclick="$(this).trigger(\'events-bubble-remove\');"><span class="glyphicons glyphicons-circle-remove"></span></a>').appendTo($li);
					
					$events_ul.find('> *').remove();
					$events_ul.append($li);
					$events_ul.show();
					
					genericAjaxGet('', 'c=internal&a=getTriggerEventParams&id=' + encodeURIComponent(token), function(o) {
						var $params = $frm.find('div.event-params');
						$params.html(o).fadeIn();
					});
				}
			})
			.fadeIn()
			;
		});
		
		{if $events_menu}
		$popup.trigger('events-menu-refresh');
		$popup.find('div.events-widget').fadeIn();
		{/if}
	});
});
</script>