{$guid = uniqid()}

<form id="frm{$guid}" action="#" style="margin-bottom:5px;width:98%;">
	<div style="float:left;">
		<span style="font-weight:bold;font-size:150%;">{$calendar_properties.calendar_date|devblocks_date:'F Y'}</span>
		
		<span style="margin-left:10px;">
			<ul class="bubbles">
				<li><a href="javascript:;" class="cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_CALENDAR}" data-context-id="{$calendar->id}">{$calendar->name}</a></li>
			</ul> 
		</span>
	</div>

	<div style="float:right;">
		<button type="button" onclick="genericAjaxGet($(this).closest('div.cerb-profile-widget--content'), 'c=profiles&a=handleProfileWidgetAction&widget_id={$widget->id}&action=showCalendarTab&id={$calendar->id}&month={$calendar_properties.prev_month}&year={$calendar_properties.prev_year}');"><span class="glyphicons glyphicons-chevron-left"></span></button>
		<button type="button" onclick="genericAjaxGet($(this).closest('div.cerb-profile-widget--content'), 'c=profiles&a=handleProfileWidgetAction&widget_id={$widget->id}&action=showCalendarTab&id={$calendar->id}&month=&year=');">{'common.today'|devblocks_translate|capitalize}</button>
		<button type="button" onclick="genericAjaxGet($(this).closest('div.cerb-profile-widget--content'), 'c=profiles&a=handleProfileWidgetAction&widget_id={$widget->id}&action=showCalendarTab&id={$calendar->id}&month={$calendar_properties.next_month}&year={$calendar_properties.next_year}');"><span class="glyphicons glyphicons-chevron-right"></span></button>
	</div>
	
	<br clear="all">
</form>

<table cellspacing="0" cellpadding="0" border="0" class="calendar">
<tr class="heading">
{if $calendar->params.start_on_mon}
	<th>{'common.day.monday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.tuesday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.wednesday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.thursday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.friday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.saturday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.sunday.abbr'|devblocks_translate|capitalize}</th>
{else}
	<th>{'common.day.sunday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.monday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.tuesday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.wednesday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.thursday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.friday.abbr'|devblocks_translate|capitalize}</th>
	<th>{'common.day.saturday.abbr'|devblocks_translate|capitalize}</th>
{/if}
</tr>
{foreach from=$calendar_properties.calendar_weeks item=week name=weeks}
<tr class="week">
	{foreach from=$week item=day name=days}
		<td class="{if $calendar_properties.today == $day.timestamp}today{/if}{if $day.is_padding} inactive{/if}{if $smarty.foreach.days.last} cellborder_r{/if}{if $smarty.foreach.weeks.last} cellborder_b{/if}" style="position:relative;">
			<div class="day_header">
				{if $calendar->params.manual_disabled}
					{if $calendar_properties.today == $day.timestamp}
					<a href="javascript:;">Today, {$calendar_properties.today|devblocks_date:"M d"}</a>
					{else}
					<a href="javascript:;">{$day.dom}</a>
					{/if}
				{else}
					{if $calendar_properties.today == $day.timestamp}
					<a href="javascript:;" class="cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_CALENDAR_EVENT}" data-context="0" data-edit="calendar.id:{$calendar->id} start:{$day.timestamp}">Today, {$calendar_properties.today|devblocks_date:"M d"}</a>
					{else}
					<a href="javascript:;" class="cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_CALENDAR_EVENT}" data-context="0" data-edit="calendar.id:{$calendar->id} start:{$day.timestamp}">{$day.dom}</a>
					{/if}
				{/if}
			</div>
			<div class="day_contents">
				{if $calendar_events.{$day.timestamp}}
					{foreach from=$calendar_events.{$day.timestamp} item=event}
						<div class="event" style="background-color:{$event.color|default:'#C8C8C8'};" link="{$event.link}">
							<a href="javascript:;" class="cerb-peek-trigger" data-context="{$event.context}" data-context-id="{$event.context_id}">
							
							{$worker_prefs = DAO_WorkerPref::getByWorker($active_worker->id)}
							{$time_format = $worker_prefs.time_format|default:'D, d M Y h:i a'}
							{if $time_format = 'D, d M Y h:i a'}{$hour_format = 'g'}{else}{$hour_format = 'H'}{/if}
							
							{if !$calendar->params.hide_start_time}
							<b>
							{if $event.ts|devblocks_date:'i' == '00'}
								{$event.ts|devblocks_date:$hour_format}{if $hour_format=='g'}{$event.ts|devblocks_date:'a'|substr:0:1}{/if}
							{else}
								{$event.ts|devblocks_date:"{$hour_format}:i"}{if $hour_format=='g'}{$event.ts|devblocks_date:'a'|substr:0:1}{/if}
							{/if}
							</b>
							{/if}
							
							{$event.label}
							</a>
						</div>
					{/foreach}
				{/if}
			</div>
		</td>
	{/foreach}
</tr>
{/foreach}
</table>

<script type="text/javascript">
$(function() {
	var $frm = $('#frm{$guid}');
	var $tab = $frm.closest('div.cerb-profile-widget--content');
	
	$tab.find('.cerb-peek-trigger')
		.cerbPeekTrigger()
		.on('cerb-peek-opened', function(e) {
		})
		.on('cerb-peek-saved cerb-peek-deleted', function(e) {
			var month = (e.month) ? e.month : '{$calendar_properties.month}';
			var year = (e.year) ? e.year : '{$calendar_properties.year}';
			
			genericAjaxGet($('#frm{$guid}').closest('div.cerb-profile-widget--content'), 'c=profiles&a=handleProfileWidgetAction&widget_id={$widget->id}&action=showCalendarTab&id={$calendar->id}&month=' + month + '&year=' + year);
			event.stopPropagation();
		})
		.on('cerb-peek-closed', function(e) {
		})
		;
});
</script>
