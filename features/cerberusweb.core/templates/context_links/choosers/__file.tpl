<form action="{devblocks_url}{/devblocks_url}" method="POST" enctype="multipart/form-data" id="chooserFileUploadForm" onsubmit="return false;">

<fieldset class="peek">
	<legend>{if $single}{'common.upload.file'|devblocks_translate|capitalize}{else}{'common.upload.files'|devblocks_translate|capitalize}{/if}</legend>
	<input type="file" name="file_data[]" {if !$single}multiple="multiple"{/if}>
</fieldset>

{if !$single}
<fieldset class="peek">
	<legend>{'common.file_bundles.include'|devblocks_translate}</legend>
	<button type="button" class="chooser-file-bundle"><span class="glyphicons glyphicons-search"></span></button>
</fieldset>
{/if}

<div class="cerb-uploads"></div>

<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);vertical-align:middle;"></span> {'common.ok'|devblocks_translate|upper}</button>
</form>

<br>

<script type="text/javascript">
$(function() {
	var $popup = genericAjaxPopupFind('#chooserFileUploadForm');
	var $frm = $popup.find('FORM#chooserFileUploadForm');
	var $file_input = $frm.find('input[type=file]');
	var $uploads = $frm.find('div.cerb-uploads');
	var $submit = $frm.find('button.submit');
	
	$popup.find('UL.buffer').sortable({ placeholder: 'ui-state-highlight' });
	
	// Bundle chooser
	
	$popup.find('button.chooser-file-bundle').each(function() {
		ajax.chooser(this,'{CerberusContexts::CONTEXT_FILE_BUNDLE}','bundle_ids', { autocomplete:true });
	});
	
	var uploadFunc = function(f, labels, values, callback) {
		var xhr = new XMLHttpRequest();
		var file = f;
		var $progress = $('<p/>').appendTo($uploads);
		
		if(xhr.upload) {
			xhr.open('POST', DevblocksAppPath + 'ajax.php?c=internal&a=chooserOpenFileAjaxUpload', true);
			xhr.setRequestHeader('X-File-Name', encodeURIComponent(f.name));
			xhr.setRequestHeader('X-File-Type', f.type);
			xhr.setRequestHeader('X-File-Size', f.size);
			xhr.setRequestHeader('X-CSRF-Token', '{$session.csrf_token}');
			
			xhr.upload.addEventListener('progress', function(e) {
				var percent = parseInt(e.loaded/e.total*100);
				$progress.text('Uploading ' + file.name + ' ...' + percent + '%');
				$progress.css('background', 'linear-gradient(90deg, rgb(40,130,250) ' + percent + '%, rgb(200,200,200) ' + (100-percent) + '%);');
			});
			
			xhr.onreadystatechange = function(e) {
				if(xhr.readyState == 4) {
					var json = {};
					if(xhr.status == 200) {
						$progress
							.text(file.name)
							.addClass('success')
							;
						
						json = JSON.parse(xhr.responseText);
						labels.push(json.name + ' (' + json.size_label + ')');
						values.push(json.id);
						
					} else {
						$progress
							.text('Failed to upload ' + file.name)
							.addClass('failure')
							;
					}
					
					callback(null, json);
				}
			};
			
			xhr.send(f);
		}
	};
	
	var loadBundleFunc = function(bundle_id, labels, values, callback) {
		genericAjaxGet('', 'c=internal&a=chooserOpenFileLoadBundle&bundle_id=' + encodeURIComponent(bundle_id), function(json) {
			if(!$.isArray(json)) {
				callback();
				return;
			}
			
			for(var i = 0; i < json.length; i++) {
				labels.push(json[i].name + ' (' + json[i].size_label + ')');
				values.push(json[i].id);
			}
			
			callback(null, json);
		});
	};
	
	// Form
	
	$submit.on('click', function(event) {
		$submit.hide();
		
		var labels = [];
		var values = [];
		var jobs = [];

		// Loop through bundles
		
		var $bundles_button = $frm.find('button.chooser-file-bundle');
		var $bundles = $bundles_button.next('ul.chooser-container').find('input:hidden[name="bundle_ids[]"]');
		
		$bundles.each(function(e) {
			var $bundle = $(this);
			var bundle_id = $bundle.val();
			
			jobs.push(
				async.apply(loadBundleFunc, bundle_id, labels, values)
			);
		});
		
		// Upload individual files
		
		var files = $file_input[0].files;
		
		for(var i = 0, f; f = files[i]; i++) {
			jobs.push(
				async.apply(uploadFunc, f, labels, values)
			);
		}
		
		async.series(jobs, function(err, json) {
			// Trigger event
			var event = jQuery.Event('chooser_save');
			event.response = json;
			event.labels = labels;
			event.values = values;
			$popup.trigger(event);
			
			genericAjaxPopupDestroy('{$layer}');
		});
	});
	
	$popup.one('popup_open',function(event,ui) {
		event.stopPropagation();
		$popup.dialog('option','title','File Chooser');
	});
	
	$popup.one('dialogclose', function(event) {
		event.stopPropagation();
		genericAjaxPopupDestroy('{$layer}');
	});
});
</script>
