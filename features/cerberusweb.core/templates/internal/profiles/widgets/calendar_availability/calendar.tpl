{$guid = uniqid()}

<form id="frm{$guid}" action="#" style="margin-bottom:5px;width:98%;">
	<div style="float:left;">
		<span style="font-weight:bold;font-size:150%;">{$calendar_properties.calendar_date|devblocks_date:'F Y'}</span>
		{if !empty($calendar)}
			<span style="margin-left:10px;">
				<ul class="bubbles">
					<li><a href="javascript:;" class="cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_CALENDAR}" data-context-id="{$calendar->id}">{$calendar->name}</a></li>
				</ul> 
			</span>
		{else}
			{if empty($workers)}{$workers = DAO_Worker::getAll()}{/if}

			{if $context == CerberusContexts::CONTEXT_WORKER && $context_id != $active_worker->id && isset($workers.$context_id)}
			<div class="ui-widget">
				<div class="ui-state-error ui-corner-all" style="padding: 0.7em; margin: 0.2em; ">
					<strong>{$workers.$context_id->getName()} has not configured an availability calendar in their settings.</strong>
				</div>
			</div>
			{/if}
		{/if}
	</div>

	<div style="float:right;">
		<button type="button" onclick="genericAjaxGet($(this).closest('div.cerb-profile-widget--content'), 'c=profiles&a=handleProfileWidgetAction&widget_id={$widget->id}&action=showCalendarAvailabilityTab&context={$context}&context_id={$context_id}&id={$calendar->id}&month={$calendar_properties.prev_month}&year={$calendar_properties.prev_year}');"><span class="glyphicons glyphicons-chevron-left"></span></button>
		<button type="button" onclick="genericAjaxGet($(this).closest('div.cerb-profile-widget--content'), 'c=profiles&a=handleProfileWidgetAction&widget_id={$widget->id}&action=showCalendarAvailabilityTab&context={$context}&context_id={$context_id}&id={$calendar->id}&month=&year=');">{'common.today'|devblocks_translate|capitalize}</button>
		<button type="button" onclick="genericAjaxGet($(this).closest('div.cerb-profile-widget--content'), 'c=profiles&a=handleProfileWidgetAction&widget_id={$widget->id}&action=showCalendarAvailabilityTab&context={$context}&context_id={$context_id}&id={$calendar->id}&month={$calendar_properties.next_month}&year={$calendar_properties.next_year}');"><span class="glyphicons glyphicons-chevron-right"></span></button>
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
{$now = strtotime('now')}
{foreach from=$calendar_properties.calendar_weeks item=week name=weeks}
<tr class="week">
	{foreach from=$week item=day name=days}
		{$is_today = $calendar_properties.today == $day.timestamp}
		<td class="{if $is_today}{'common.today'|devblocks_translate|capitalize}{/if}{if $day.is_padding} inactive{/if}{if $smarty.foreach.days.last} cellborder_r{/if}{if $smarty.foreach.weeks.last} cellborder_b{/if}">
			<div class="day_header">
				{if $is_today}
				<a href="javascript:;" onclick="">Today, {$calendar_properties.today|devblocks_date:"M d"}</a>
				{else}
				<a href="javascript:;" onclick="">{$day.dom}</a>
				{/if}
			</div>
			<div class="day_contents">
				{if $calendar_events.{$day.timestamp}}
					{foreach from=$calendar_events.{$day.timestamp} item=event}
						<div class="event" style="background-color:{$event.color|default:'#C8C8C8'};" link="{$event.link}">
							{if $is_today && $now >= $event.ts && $now <= $event.ts_end}<span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span>{/if}
							<span style="color:rgb(0,0,0);">{$event.label}</span>
							{*<a href="javascript:;" style="color:rgb(0,0,0);">{$event.label}</a>*}
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
	
	$frm.find('.cerb-peek-trigger')
		.cerbPeekTrigger()
		;
});
</script>
