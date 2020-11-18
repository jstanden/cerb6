<?php
class WorkspaceWidget_MapGeoPoints extends Extension_WorkspaceWidget {
	public function invoke(string $action, Model_WorkspaceWidget $model) {
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!Context_WorkspaceWidget::isReadableByActor($model, $active_worker))
			DevblocksPlatform::dieWithHttpError(null, 403);
		
		switch($action) {
			case 'mapClicked':
				return $this->_workspaceWidgetAction_mapClicked($model);
		}
		
		return false;
	}
	
	function render(Model_WorkspaceWidget $widget) {
		$tpl = DevblocksPlatform::services()->template();
		$kata = DevblocksPlatform::services()->kata();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$error = null;
		
		$map = [
			'resource' => [
				'uri' => 'uri:resource:map/world/countries',
			],
			'projection' => [
				'type' => 'mercator',
				'scale' => 90,
				'center' => [
					'longitude' => 0,
					'latitude' => 25,
				]
			],
			'regions' => [
				'label_key' => 'NAME',
			]
		];
		
		$dict = new DevblocksDictionaryDelegate([
			'current_worker__context' => CerberusContexts::CONTEXT_WORKER,
			'current_worker_id' => $active_worker->id,
			'widget__context' => CerberusContexts::CONTEXT_WORKSPACE_WIDGET,
			'widget_id' => $widget->id,
		]);
		
		$map_data = $kata->parse($widget->params['map_kata'] ?? '', $error);
		
		if(is_array($map_data) && array_key_exists('map', $map_data)) {
			$map_data = $kata->formatTree($map_data, $dict);
			$map = array_merge($map, $map_data['map'] ?? []);
			unset($map_data);
		}
		
		$resource_keys = [];
		
		if(@$map['resource']['uri']) {
			$uri_parts = DevblocksPlatform::services()->ui()->parseURI($map['resource']['uri']);
			$resource_keys[] = $uri_parts['context_id'];
			$map['resource']['uri'] = $uri_parts['context_id'];
		}
		
		if(@$map['regions']['properties']['resource']['uri']) {
			$uri_parts = DevblocksPlatform::services()->ui()->parseURI($map['regions']['properties']['resource']['uri']);
			$resource_keys[] = $uri_parts['context_id'];
			$map['regions']['properties']['resource']['uri'] = $uri_parts['context_id'];
		}
		
		if(@$map['points']['resource']['uri']) {
			$uri_parts = DevblocksPlatform::services()->ui()->parseURI($map['points']['resource']['uri']);
			$resource_keys[] = $uri_parts['context_id'];
			$map['points']['resource']['uri'] = $uri_parts['context_id'];
		}
		
		$resources = DAO_Resource::getByNames($resource_keys);
		$resources = array_combine(array_column($resources, 'name'), $resources);
		
		if(@$map['resource']['uri']) {
			if (false != ($resource = @$resources[$map['resource']['uri']])) {
				$map['resource']['name'] = $resource->name;
				$map['resource']['size'] = $resource->storage_size;
				$map['resource']['updated_at'] = $resource->updated_at;
			}
		}
		
		if(@$map['regions']['properties']['resource']['uri']) {
			if(false != ($regions_resource = @$resources[$map['regions']['properties']['resource']['uri']])) {
				$map['regions']['properties']['resource']['name'] = $regions_resource->name;
				$map['regions']['properties']['resource']['size'] = $regions_resource->storage_size;
				$map['regions']['properties']['resource']['updated_at'] = $regions_resource->updated_at;
			}
		}
		
		if(@$map['points']['resource']['uri']) {
			if(false != ($points_resource = @$resources[$map['points']['resource']['uri']])) {
				$map['points']['resource']['name'] = $points_resource->name;
				$map['points']['resource']['size'] = $points_resource->storage_size;
				$map['points']['resource']['updated_at'] = $points_resource->updated_at;
			}
		}
		
		// Manual points
		if(@$map['points']['data']) {
			$points = [
				'type' => 'FeatureCollection',
				'features' => []
			];
			
			foreach($map['points']['data'] as $point) {
				if(!is_array($point))
					continue;
				
				if(!array_key_exists('longitude', $point) || !array_key_exists('latitude', $point))
					continue;
				
				$points['features'][] = [
					'type' => 'Feature',
					'properties' => $point['properties'],
					'geometry' => [
						'type' => 'Point',
						'coordinates' => [
							$point['longitude'],
							$point['latitude']
						]
					]
				];
			}
			
			$tpl->assign('points_json', json_encode($points));
		}
		
		$tpl->assign('widget', $widget);
		
		if($map) {
			$tpl->assign('map', $map);
			$tpl->display('devblocks:cerberusweb.core::internal/widgets/map/geopoints/render_regions.tpl');
		}
	}
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('widget', $widget);
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/map/geopoints/config.tpl');
	}
	
	function invokeConfig($action, Model_WorkspaceWidget $model) {
		return false;
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_POST['params'], 'array', []);
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));
	}
	
	private function _workspaceWidgetAction_mapClicked(Model_WorkspaceWidget $widget) {
		@$feature_type = DevblocksPlatform::importGPC($_POST['feature_type'], 'string', []);
		@$feature_properties = DevblocksPlatform::importGPC($_POST['feature_properties'], 'array', []);
		
		$tpl = DevblocksPlatform::services()->template();
		$event_handler = DevblocksPlatform::services()->ui()->eventHandler();
		$sheets = DevblocksPlatform::services()->sheet();
		$active_worker = CerberusApplication::getActiveWorker();
		
		header('Content-Type: application/json; charset=utf-8');
		
		$error = null;
		
		$dict = DevblocksDictionaryDelegate::instance([
			'feature_type' => $feature_type,
			'feature_properties' => $feature_properties,
			'widget__context' => CerberusContexts::CONTEXT_WORKSPACE_WIDGET,
			'widget_id' => $widget->id,
			'worker__context' => CerberusContexts::CONTEXT_WORKER,
			'worker_id' => $active_worker->id,
		]);
		
		$handlers = $event_handler->parse($widget->params['automation']['map_clicked'] ?? '', $dict, $error);
		
		$initial_state = $dict->getDictionary();
		
		if(false == ($automation_results = $event_handler->handleOnce(AutomationTrigger_MapClicked::ID, $handlers, $initial_state, $error))) {
			echo json_encode([]);
			return;
		}
		
		$exit_code = $automation_results->get('__exit');
		
		$result = [
			'exit_code' => $exit_code,
		];
		
		if('error' == $exit_code) {
			$result['error'] = $automation_results->getKeyPath('__return.error') ?? 'Error in map click automation.';
			echo json_encode($result);
			return;
		}
		
		if(null != ($sheet_schema = $automation_results->getKeyPath('__return.sheet', null))) {
			$properties = DevblocksDictionaryDelegate::instance($feature_properties ?? []);
			
			$sheet = $sheets->parse(DevblocksPlatform::services()->kata()->emit($sheet_schema));
			
			$sheets->addType('card', $sheets->types()->card());
			$sheets->addType('date', $sheets->types()->date());
			$sheets->addType('icon', $sheets->types()->icon());
			$sheets->addType('link', $sheets->types()->link());
			$sheets->addType('search', $sheets->types()->search());
			$sheets->addType('slider', $sheets->types()->slider());
			$sheets->addType('text', $sheets->types()->text());
			$sheets->addType('time_elapsed', $sheets->types()->timeElapsed());
			$sheets->setDefaultType('text');
			
			$layout = $sheets->getLayout($sheet);
			$columns = $sheets->getColumns($sheet);
			$rows = $sheets->getRows($sheet, [$properties]);
			
			$tpl->assign('layout', $layout);
			$tpl->assign('columns', $columns);
			$tpl->assign('rows', $rows);
			
			$result['sheet'] = $tpl->fetch('devblocks:cerberusweb.core::ui/sheets/render_fieldsets.tpl');
		}
		
		echo json_encode($result);
	}
};