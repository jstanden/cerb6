<div id="worker{$worker->id}SettingsTabs">
	<ul>
		{$tabs = []}
		
		{$list = ['profile','pages','localization','search','availability','mail','security','sessions','watchers']}
		
		{foreach from=$list item=tab_name}
		{$tabs[] = $tab_name}
		<li data-alias="{$tab_name}"><a href="{devblocks_url}ajax.php?c=profiles&a=handleProfileTabAction&tab_id={$tab->id}&action=showSettingsSectionTab&worker_id={$worker->id}&tab={$tab_name}{/devblocks_url}">{"common.$tab_name"|devblocks_translate|capitalize}</a></li>
		{/foreach}
	</ul>
</div>

<script type="text/javascript">
$(function() {
	var $tabs = $('#worker{$worker->id}SettingsTabs');
	$tabs.tabs();
});
</script>
