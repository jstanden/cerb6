<form action="{devblocks_url}{/devblocks_url}" method="post" target="_blank" id="frm{$view_id}_export">
<input type="hidden" name="c" value="internal">
<input type="hidden" name="a" value="doViewExport">
<input type="hidden" name="view_id" value="{$view_id}">
<input type="hidden" name="cursor_key" value="">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<div class="export-settings">

<h1>{'common.export'|devblocks_translate|capitalize}</h1>

<table cellpadding="10" cellspacing="0">
	<tr>
		<td valign="top">
			<b>{'common.fields'|devblocks_translate}:</b>
			
			<ul class="bubbles sortable" style="display:block;padding:0;">
				{foreach from=$tokens item=token}
				<li style="display: block; cursor: move; margin: 5px;"><input type="hidden" name="tokens[]" value="{$token}">{$labels.$token}{if '_label' == substr($token, -6)} (Record){/if}<a href="javascript:;" style="position: absolute; visibility: hidden; top: -7px; right: -6px; display: block;"><span class="glyphicons glyphicons-circle-remove"></span></a></li>		
				{/foreach}
			</ul>
		</td>
		
		<td valign="top">
			<b>{'common.add'|devblocks_translate|capitalize}:</b>
			
			{function tree level=0}
				{foreach from=$keys item=data key=idx}
					{if is_array($data->children) && !empty($data->children)}
						<li {if $data->key}data-token="{$data->key}" data-label="{$data->label}"{/if}>
							{if $data->key}
								<div style="font-weight:bold;">{$data->l|capitalize}</div>
							{else}
								<div>{$idx|capitalize}</div>
							{/if}
							<ul>
								{tree keys=$data->children level=$level+1}
							</ul>
						</li>
					{elseif $data->key}
						<li data-token="{$data->key}" data-label="{$data->label}"><div style="font-weight:bold;">{$data->l|capitalize}</div></li>
					{/if}
				{/foreach}
			{/function}
			
			<ul class="menu" style="width:250px;">
			{tree keys=$placeholders}
			</ul>
		</td>
	</tr>
</table>

<div style="margin-bottom:10px;">
	<b>{'common.export.date_format'|devblocks_translate}</b>
	<div style="margin-left:10px;">
		<label><input type="radio" name="format_timestamps" value="1" checked="checked"> {'common.text'|devblocks_translate|capitalize}</label>
		<label><input type="radio" name="format_timestamps" value="0"> {'common.unix_timestamp'|devblocks_translate|capitalize}</label>
	</div>
</div>

<div style="margin-bottom:10px;">
	<b>{'common.export.list_format'|devblocks_translate}</b>
	<div style="margin-left:10px;">
		<select name="export_as">
			<option value="csv" selected="selected">Comma-separated values (.csv)</option>
			<option value="json">JSON (.json)</option>
			<option value="xml">XML (.xml)</option>
		</select>
	</div>
</div>

<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.export'|devblocks_translate|capitalize}</button>
<button type="button" onclick="$('#{$view_id}_tips').html('').hide();" style="cancel"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.cancel'|devblocks_translate|capitalize}</button>

</div>

<div class="export-status"></div>

</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#frm{$view_id}_export');
	var $bubbles = $frm.find('ul.bubbles');
	var $settings = $frm.find('div.export-settings');
	var $status = $frm.find('div.export-status');
	
	var $placeholder_menu = $frm.find('ul.menu').menu({
		select: function(event, ui) {
			var token = ui.item.attr('data-token');
			var label = ui.item.attr('data-label');
			
			if(undefined == token || undefined == label)
				return;
			
			var $bubble = $('<li style="display:block;"></li>')
				.css('cursor', 'move')
				.css('margin', '5px')
			;
			
			var $hidden = $('<input>');
			$hidden.attr('type', 'hidden');
			$hidden.attr('name', 'tokens[]');
			$hidden.attr('value', token);
			
			var $a = $('<a href="javascript:;" style="position: absolute; visibility: hidden; top: -7px; right: -6px; display: block;"><span class="glyphicons glyphicons-circle-remove"></span></a>');
			
			$bubble.append($hidden);
			$bubble.append(label);
			$bubble.append($a);
			$bubbles.append($bubble);
		}
	});
	
	$bubbles.on('click', function(e) {
		var $target = $(e.target);
		if($target.is('.glyphicons-circle-remove')) {
			e.stopPropagation();
			$target.closest('li').remove();
		}
	});
	
	$bubbles.on('mouseover', function(e) {
		$bubbles.find('a').css('visibility', 'visible');
	});
	
	$bubbles.on('mouseout', function(e) {
		$bubbles.find('a').css('visibility', 'hidden');
	});
	
	$frm.find('ul.bubbles.sortable').sortable({
		placeholder: 'ui-state-highlight',
		items: 'li',
		distance: 10
	});
	
	$frm.on('export_increment', function() {
		genericAjaxPost('frm{$view_id}_export', null, 'c=internal&a=doViewExport', function(json) {
			
			// If complete, display the download link
			if(json.completed) {
				$frm.find('input:hidden[name=cursor_key]').val('');
				
				var $html = $('<div><a href="javascript:;" class="close"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);font-size:16px;position:relative;float:right;"></span></a></div>')
					.append(
						$('<div style="font-size:18px;font-weight:bold;text-align:center;"/>')
							.append($('<a target="_blank" rel="noopener"/>').attr('href',json.attachment_url).text(json.attachment_name).prepend('Download: '))
					);
					
				$status.html($html).fadeIn();
					
				$status.find('a.close').click(function() {
					$('#{$view_id}_tips').html('').hide();
				});
				return;
			}
			
			$frm.find('input:hidden[name=cursor_key]').val(json.key);
			
			// If in progress, continue looping pages
			var $html = $('<div style="font-size:18px;font-weight:bold;text-align:center;padding:10px;margin:10px;"/>')
				.text('Exported ' + json.rows_exported + ' records')
				.append('<br><span class="cerb-ajax-spinner"></span>')
				;
			
			$status.html($html).fadeIn();
			$frm.trigger('export_increment');
			
		});
		
	})
	
	$frm.find('button.submit').click(function() {
		$settings.hide();
		
		// If in progress, continue looping pages
		var $html = $('<div style="font-size:18px;font-weight:bold;text-align:center;padding:10px;margin:10px;"/>')
			.text('Exporting...')
			.append('<br><span class="cerb-ajax-spinner"></span>')
			;
		
		$status.html($html).fadeIn();

		$frm.trigger('export_increment');
	});
});
</script>
