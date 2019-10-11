<div>
	<label><input type="checkbox" name="view_options[disable_watchers]" value="1" {if $view->options.disable_watchers}checked="checked"{/if}> {'worklist.hide_watcher'|devblocks_translate}</label>
</div>

<fieldset class="peek peek-noborder black" style="margin-top:10px;">
	<legend>{'common.compose'|devblocks_translate|capitalize}</legend>
	
	{'worklist.compose.sender'|devblocks_translate}:
	<div style="margin-left:10px;">
		<button type="button" class="chooser-bucket" data-field-name="view_options[compose_bucket_id]" data-context="{CerberusContexts::CONTEXT_BUCKET}" data-single="true" data-query=""><span class="glyphicons glyphicons-search"></span></button>
		<ul class="bubbles chooser-container">
			{$bucket = DAO_Bucket::get($view->options.compose_bucket_id)}
			{if $bucket}
			<li><a href="javascript:;" class="cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_BUCKET}" data-context-id="{$bucket->id}">{$bucket->name}</a></li>
			{/if}
		</ul>
	</div>
</fieldset>

<script type="text/javascript">
$(function() {
	var $view = $('#view{$view->id}');
	
	$view
		.find('button.chooser-bucket')
		.cerbChooserTrigger()
		;
	
	$view
		.find('.cerb-peek-trigger')
		.cerbPeekTrigger()
		;
});
</script>
