{$element_id = uniqid('prompt_')}
<div class="cerb-interaction-popup--form-elements--prompt cerb-interaction-popup--form-elements-sheet" id="{$element_id}">
	{if is_string($label) && $label}
	<h6>{$label}</h6>
	{/if}

	<div>
		{$selection_key = uniqid('selection_')}

		<div data-cerb-sheet-container>
			{if $layout.style == 'buttons'}
				{include file="devblocks:cerberusweb.core::automations/triggers/interaction.website/await/sheet/render_buttons.tpl" sheet_selection_key=$selection_key default=$default}
			{elseif $layout.style == 'scale'}
				{include file="devblocks:cerberusweb.core::automations/triggers/interaction.website/await/sheet/render_scale.tpl" sheet_selection_key=$selection_key default=$default}
			{else}
				{include file="devblocks:cerberusweb.core::automations/triggers/interaction.website/await/sheet/render.tpl" sheet_selection_key=$selection_key default=$default}
			{/if}
		</div>

		<div data-cerb-sheet-selections style="display:none;">
			<ul class="bubbles chooser-container">
				{if is_array($default) && $default}
					{foreach from=$default item=v}
						<li>
							<input type="hidden" name="prompts[{$var}][]" value="{$v}">
							{$v}
						</li>
					{/foreach}
				{elseif is_string($default) && $default}
					<li>
						<input type="hidden" name="prompts[{$var}]" value="{$default}">
						{$default}
					</li>
				{/if}
			</ul>
		</div>
	</div>
</div>

<script type="text/javascript">
{
	var $prompt = document.querySelector('#{$element_id}');
	var $popup = $prompt.closest('.cerb-interaction-popup');
	var $sheet = $prompt.querySelector('[data-cerb-sheet-container]');
	var $sheet_selections = $prompt.querySelector('[data-cerb-sheet-selections]').querySelector('ul');

	var $remove = document.createElement('span');
	$remove.classList.add(['glyphicons','glyphicons-circle-remove']);
	$remove.style.position = 'absolute';
	$remove.style.top = '-5px';
	$remove.style.right = '-5px';
	$remove.addEventListener('click', function(e) {
		e.stopPropagation();
		var $parent = $remove.closest('li');
		$parent.removeChild($remove);
		$parent.remove();
		
		$prompt.dispatchEvent($$.createEvent('cerb-sheet--update-selections'));
	});

	$$.forEach($sheet_selections, function(index, $sel) {
		$sel.addEventListener('mouseover', function(e) {
			if('li' !== e.target.nodeName.toLowerCase())
				return;
			
			e.stopPropagation();
			$sel.appendChild($remove);
		});
	});
	
	$prompt.addEventListener('cerb-sheet--update-selections', function(e) {
		e.stopPropagation();
		
		var $checkboxes = $sheet.querySelectorAll('input[type=checkbox],input[type=radio]');
		var $selections = $sheet_selections.querySelectorAll('input[type=hidden]');

		$$.forEach($checkboxes, function(index, $el) {
			$el.checked = false;
		});
		
		$$.forEach($selections, function(index, $hidden) {
			var $el = $sheet.querySelector('input[value="' + $hidden.value + '"]');
			if($el) $el.checked = true;
		});
	});

	$prompt.addEventListener('cerb-sheet--selections-clear', function(e) {
		e.stopPropagation();
		$sheet_selections.innerHTML = '';
	});

	$prompt.addEventListener('cerb-sheet--refresh', function(e) {
		e.stopPropagation();
	});

	$prompt.addEventListener('cerb-sheet--page-changed', function(e) {
		e.stopPropagation();
	});

	$sheet.addEventListener('cerb-sheet--selection', function(e) {
		e.stopPropagation();
		
		var $li = null;
		var $clone = null;
		var is_multiple = e.detail.hasOwnProperty('is_multiple') ? e.detail.is_multiple : false;
		var item = e.detail.hasOwnProperty('ui') ? e.detail.ui.item : null;
		
		if(!item) return;

		var $checkbox = $sheet_selections.querySelector('input[value="' + item.value + '"]');

		if(is_multiple) {
			if($checkbox) {
				$checkbox.closest('li').remove();
			} else {
				$li = document.createElement('li');
				$li.innerText = item.closest('.cerb-sheet--row').innerText;
				
				$clone = item.cloneNode(true);
				$clone.setAttribute('type', 'hidden');
				$clone.setAttribute('name', 'prompts[{$var}][]');
				$li.prepend($clone);
				
				$sheet_selections.appendChild($li);
			}

		} else {
			$sheet_selections.innerHTML = '';
			
			$li = document.createElement('li');
			$li.innerText = item.closest('.cerb-sheet--row').innerText;
			
			$clone = item.cloneNode(true);
			$clone.setAttribute('type', 'hidden');
			$clone.setAttribute('name', 'prompts[{$var}]');
			$li.appendChild($clone);
			
			$sheet_selections.appendChild($li);
		}
		
		{if $layout.style == 'buttons'}
			if(0 === $popup.querySelectorAll('.cerb-interaction-popup--form-elements-continue').length) {
				$popup.dispatchEvent($$.createEvent('cerb-interaction-event--submit'));
			}
		{/if}
	});

	$sheet.addEventListener('cerb-sheet--selections-changed', function(e) {
		e.stopPropagation();
	});

	{if $layout.style != 'buttons'}
	$prompt.dispatchEvent($$.createEvent('cerb-sheet--update-selections'));
	{/if}
}
</script>