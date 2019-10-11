<input type="hidden" name="c" value="internal">
<input type="hidden" name="a" value="viewSaveCustomize">
<input type="hidden" name="id" value="{$view->id}">
<div class="block" style="margin:5px;">
<h1 style="margin-bottom:10px;">{'common.customize'|devblocks_translate|capitalize}</h1>

{* Custom Views *}
{$is_custom = $view->isCustom()}

{* Trigger Views *}
{if substr($view->id,0,9)=="_trigger_"}
	{$is_trigger = true}
{else}
	{$is_trigger = false}
{/if}

{if $is_custom}
{$workspace_list = $view->getCustomWorklistModel()}
{/if}

{if $is_custom || $is_trigger}
<fieldset class="peek peek-noborder black">
	<legend>{'common.title'|devblocks_translate|capitalize}</legend>
	
	<input type="text" name="title" value="{$view->name}" size="64" autocomplete="off"><br>
</fieldset>
{/if}

{if $is_custom}
<fieldset class="peek peek-noborder black" style="margin-bottom:0;">
	<legend>{'worklist.restrict_result'|devblocks_translate}:</legend>
	
	<div id="viewCustomReqQuickSearch{$view->id}" style="margin:5px 0px 0px 0px;">
		<textarea name="params_required_query" style="width:100%;padding:5px;" data-editor-mode="ace/mode/cerb_query">{$workspace_list->params_required_query}</textarea>
	</div>
</fieldset>
{/if}

<fieldset class="peek peek-noborder black">
	<legend>
		{'dashboard.columns'|devblocks_translate|capitalize} (<a href="javascript:;" onclick="$(this).closest('fieldset').find('input:checkbox').removeAttr('checked');">{'common.clear'|devblocks_translate|lower}</a>)</b>
	</legend>

	{foreach from=$columns item=column}
	<div class="column">
	<span class="ui-icon ui-icon-arrowthick-2-n-s" style="display:inline-block;vertical-align:middle;cursor:move;"></span>
	<label><input type="checkbox" name="columns[]" value="{$column->token}" {if in_array($column->token, $view->view_columns)}checked="checked"{/if}> {$column->db_label|capitalize}</label>
	</div>
	{/foreach}
</fieldset>

<fieldset class="peek peek-noborder black">
	<legend>{'common.options'|devblocks_translate|capitalize}</legend>
	
	<div>
		{'dashboard.num_rows'|devblocks_translate}: <input type="text" name="num_rows" size="3" maxlength="3" value="{$view->renderLimit}">
	</div>
	
	{if $is_custom}
	<div>
		{'common.color'|devblocks_translate|capitalize}: 
		<input type="text" name="view_options[header_color]" value="{$workspace_list->options.header_color|default:'#6A87DB'}" class="color-picker">
	</div>
	<div style="margin-top:1em;">
		<label><input type="checkbox" name="view_options[disable_sorting]" value="1" {if $view->options.disable_sorting}checked="checked"{/if}> {'worklist.prevent_changing_order'|devblocks_translate}</label>
	</div>
	{/if}
	
	<div>
		{$view->renderCustomizeOptions($is_custom)}
	</div>
</fieldset>

{if $is_custom}
	{if $workspace_list}
		{$view_params = $workspace_list->getParamsRequired()}
		{if $view_params}
		<fieldset class="peek peek-noborder black" style="margin-bottom:0;">
			<legend>(Deprecated) Require these filters on the worklist:</legend>
			
			<div id="viewCustomReqFilters{$view->id}" style="margin:5px 0px 0px 10px;">
			{include file="devblocks:cerberusweb.core::internal/views/customize_view_criteria.tpl" is_custom=true}
			</div>
		</fieldset>
		{/if}
	{/if}
{/if}

<button type="button" class="save"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
<button type="button" class="cancel" onclick="toggleDiv('customize{$view->id}','none');"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.cancel'|devblocks_translate|capitalize}</button>

</div>

<script type="text/javascript">
$(function() {
	var $container =$('#customize{$view->id}');
	
	$container.sortable({ items: 'DIV.column', placeholder:'ui-state-highlight' });
	
	$container.find('input:text.color-picker').minicolors({
		swatches: ['#6A87DB','#CF2C1D','#FEAF03','#57970A','#9669DB','#ADADAD','#34434E']
	});

	$container.find('textarea[name=params_required_query]')
		.cerbCodeEditor()
		.cerbCodeEditorAutocompleteSearchQueries({ context: "{$workspace_list->context}" })
		;
	;
	
	$container.find('button.save').on('click', function(e) {
		genericAjaxPost('customize{$view->id}','','c=internal&a=viewSaveCustomize', function(e) {
			genericAjaxGet('view{$view->id}','c=internal&a=viewRefresh&id={$view->id}');
		});
	})
});
</script>
