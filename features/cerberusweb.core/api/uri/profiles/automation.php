<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2019, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

class PageSection_ProfilesAutomation extends Extension_PageSection {
	function render() {
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // automation 
		@$context_id = intval(array_shift($stack)); // 123
		
		$context = CerberusContexts::CONTEXT_AUTOMATION;
		
		Page_Profiles::renderProfile($context, $context_id, $stack);
	}
	
	function handleActionForPage(string $action, string $scope=null) {
		if('profileAction' == $scope) {
			switch ($action) {
				case 'editorVisualize':
					return $this->_profileAction_editorVisualize();
				case 'getAutocompleteJson':
					return $this->_profileAction_getAutocompleteJson();
				case 'getExtensionConfig':
					return $this->_profileAction_getExtensionConfig();
				case 'invokePrompt':
					return $this->_profileAction_invokePrompt();
				case 'invokeUiFunction':
					return $this->_profileAction_invokeUiFunction();
				case 'renderEditorToolbar':
					return $this->_profileAction_renderEditorToolbar();
				case 'runAutomationEditor':
					return $this->_profileAction_runAutomationEditor();
				case 'savePeekJson':
					return $this->_profileAction_savePeekJson();
				case 'viewExplore':
					return $this->_profileAction_viewExplore();
			}
		}
		return false;
	}
	
	private function _profileAction_savePeekJson() {
		$kata = DevblocksPlatform::services()->kata();
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id'], 'string', '');
		
		@$id = DevblocksPlatform::importGPC($_POST['id'], 'integer', 0);
		@$do_delete = DevblocksPlatform::importGPC($_POST['do_delete'], 'string', '');
		
		if('POST' != DevblocksPlatform::getHttpMethod())
			DevblocksPlatform::dieWithHttpError(null, 403);
		
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if(!empty($id) && !empty($do_delete)) { // Delete
				if(!$active_worker->hasPriv(sprintf("contexts.%s.delete", CerberusContexts::CONTEXT_AUTOMATION)))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.delete'));
				
				if(false == ($model = DAO_Automation::get($id)))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.record.not_found'));
				
				if(!Context_Automation::isDeletableByActor($model, $active_worker))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.delete'));
				
				CerberusContexts::logActivityRecordDelete(CerberusContexts::CONTEXT_AUTOMATION, $model->id, $model->name);
				
				DAO_Automation::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else {
				@$name = DevblocksPlatform::importGPC($_POST['name'], 'string', '');
				@$description = DevblocksPlatform::importGPC($_POST['description'], 'string', '');
				@$extension_id = DevblocksPlatform::importGPC($_POST['extension_id'], 'string', '');
				@$params = DevblocksPlatform::importGPC($_POST['params'], 'array', []);
				@$script = DevblocksPlatform::importGPC($_POST['automation_script'], 'string', '');
				
				$error = null;
				
				$fields = [];
				
				// Only admins
				if($active_worker->is_superuser) {
					@$policy_kata = DevblocksPlatform::importGPC($_POST['automation_policy_kata'], 'string', '');
					
					if(false === $kata->parse($policy_kata, $error)) {
						throw new Exception_DevblocksAjaxValidationError('Policy: ' . $error);
					}
					
					$fields[DAO_Automation::POLICY_KATA] = $policy_kata;
				}
				
				if($active_worker->is_superuser) {
					if(false == ($trigger_ext = Extension_AutomationTrigger::get($extension_id))) {
						throw new Exception_DevblocksAjaxValidationError('Invalid trigger extension.');
					}
					
					/* @var $trigger_ext Extension_AutomationTrigger */
					
					$fields[DAO_Automation::EXTENSION_ID] = $trigger_ext->id;
					
					if(false === ($trigger_ext->validateConfig($params, $error))) {
						throw new Exception_DevblocksAjaxValidationError($error);
					}
					
					$fields[DAO_Automation::EXTENSION_PARAMS_JSON] = json_encode($params);
				}
				
				if(empty($id)) { // New
					$fields[DAO_Automation::NAME] = $name;
					$fields[DAO_Automation::DESCRIPTION] = $description;
					$fields[DAO_Automation::SCRIPT] = $script;
					$fields[DAO_Automation::UPDATED_AT] = time();
					
					if(!DAO_Automation::validate($fields, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					if(!DAO_Automation::onBeforeUpdateByActor($active_worker, $fields, null, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					$id = DAO_Automation::create($fields);
					
				} else { // Edit
					$fields[DAO_Automation::NAME] = $name;
					$fields[DAO_Automation::DESCRIPTION] = $description;
					$fields[DAO_Automation::SCRIPT] = $script;
					$fields[DAO_Automation::UPDATED_AT] = time();
					
					if(!DAO_Automation::validate($fields, $error, $id))
						throw new Exception_DevblocksAjaxValidationError($error);
						
					if(!DAO_Automation::onBeforeUpdateByActor($active_worker, $fields, $id, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					DAO_Automation::update($id, $fields);
				}
				
				DAO_Automation::onUpdateByActor($active_worker, $fields, $id);
				
				if($id) {
					// Custom field saves
					@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', []);
					if(!DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_AUTOMATION, $id, $field_ids, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
				}
				
				echo json_encode([
					'status' => true,
					'id' => $id,
					'label' => $name,
					'view_id' => $view_id,
				]);
				return;
			}
			
		} catch (Exception_DevblocksAjaxValidationError $e) {
			echo json_encode(array(
				'status' => false,
				'error' => $e->getMessage(),
				'field' => $e->getFieldName(),
			));
			return;
			
		} catch (Exception $e) {
			echo json_encode(array(
				'status' => false,
				'error' => 'An error occurred.',
			));
			return;
		}
	}
	
	private function _profileAction_editorVisualize() {
		$automator = DevblocksPlatform::services()->automation();
		$tpl = DevblocksPlatform::services()->template();
		
		@$script = DevblocksPlatform::importGPC($_POST['script'], 'string', null);
		
		$automation = new Model_Automation();
		$automation->script = $script;
		
		$error = null;
		$symbol_meta = [];
		
		$tree = DevblocksPlatform::services()->kata()->parse($automation->script, $error, true, $symbol_meta);
		
		unset($tree['inputs']);

		$ast = $automator->buildAstFromKata($tree, $error);

		$ast2json = function(CerbAutomationAstNode $node, $depth=0) use (&$ast2json, $symbol_meta) {
			$path = explode(':', $node->getId());
			$id = end($path);
			
			@list($node_type, $node_key) = explode('/', $id, 2);
			
			$node_name = $node_key ?: $node_type;
			
			if(in_array($node_type, ['decision']) && !$node_key)
				$node_name = '';
			
			$e = [
				'name' => $node_name,
				'path' => $node->getId(),
				'line' => $symbol_meta[$node->getId()] ?? false,
				'type' => $node_type,
				'children' => [],
			];
			
			if ($node->hasChildren()) {
				$siblings = $node->getChildren();
				
				while($child = current($siblings)) {
					$child_path = explode(':', $child->getId());
					$child_id = end($child_path);
					@list($child_node_type, $child_node_key) = explode('/', $child_id, 2);
					
					if('yield' == $child_node_type) {
						$child_node_name = $child_node_key ?: ''; //$child_node_type
						
						// Reassign siblings as my children
						$yield = [
							'name' => $child_node_name,
							'path' => $child->getId(),
							'line' => $symbol_meta[$child->getId()] ?? false,
							'type' => $child_node_type,
							'children' => [],
						];
						
						next($siblings);
						
						// Drain remaining siblings
						while($new_child = current($siblings)) {
							// [TODO] if multiple yields in a row (add to last child)
							$yield['children'][] = $ast2json($new_child, $depth + 1);
							next($siblings);
						}
						
						$e['children'][] = $yield;
						
					} else {
						$e['children'][] = $ast2json($child, $depth + 1);
					}
					
					next($siblings);
				}
			}
			
			return $e;
		};
		
		if($ast instanceof CerbAutomationAstNode && $ast->hasChildren()) {
			$tpl->assign('ast_json', json_encode($ast2json($ast->getChildren()[0])));
			
		} else {
			echo $error;
			return;
		}
		
		$tpl->display('devblocks:cerberusweb.core::internal/automation/editor/tab_visualize.tpl');
	}
	
	function _profileAction_invokePrompt() {
		if('POST' != DevblocksPlatform::getHttpMethod())
			DevblocksPlatform::dieWithHttpError(null, 405);
		
		@$execution_token = DevblocksPlatform::importGPC($_POST['execution_token'], 'string', '');
		@$prompt_key = DevblocksPlatform::importGPC($_POST['prompt_key'], 'string', '');
		@$prompt_action = DevblocksPlatform::importGPC($_POST['prompt_action'], 'string', '');
		@$invoke = DevblocksPlatform::importGPC($_POST['invoke'], 'string', '');
		
		if(!$prompt_key)
			return;
		
		// Load the execution
		if(false == ($execution = DAO_AutomationExecution::getByToken($execution_token)))
			DevblocksPlatform::dieWithHttpError(null, 404);
		
		// Check actor
		
		// [TODO] Do this better
		$session_actor = [
			'context' => @$execution->state_data['actor']['context'],
			'context_id' => @$execution->state_data['actor']['id'],
		];
		
		if(!CerberusContexts::isSameActor(CerberusApplication::getActiveWorker(), $session_actor))
			DevblocksPlatform::dieWithHttpError(null, 403);
		
		$dict = $execution->state_data['dict'];
		@$form = $dict['__return']['form'];
		
		if(!array_key_exists($prompt_key, $form))
			DevblocksPlatform::dieWithHttpError(null, 404);
		
		$form_components = AutomationTrigger_UiInteraction::getFormComponentMeta();
		
		list($prompt_type, $prompt_name) = explode('/', $prompt_key, 2);
		
		if(!array_key_exists($prompt_type, $form_components))
			DevblocksPlatform::dieWithHttpError(null, 404);
		
		$component = new $form_components[$prompt_type]($prompt_name, null, $form[$prompt_key]);
		
		$component->invoke($prompt_key, $prompt_action, $execution);
	}
	
	private function _profileAction_invokeUiFunction() {
		$automator = DevblocksPlatform::services()->automation();
		$active_worker = CerberusApplication::getActiveWorker();
		
		@$function_uri = DevblocksPlatform::importGPC($_POST['function_uri'], 'string', null);
		@$function_params = DevblocksPlatform::importGPC($_POST['params'], 'array', []);
		
		$error = null;
		
		header('Content-Type: application/json; charset=utf-8');
		
		if(false == ($automation = DAO_Automation::getByNameAndTrigger($function_uri, AutomationTrigger_UiFunction::ID)))
			DevblocksPlatform::dieWithHttpError(null, 404);
		
		$initial_state = [
			'worker__context' => CerberusContexts::CONTEXT_WORKER,
			'worker_id' => $active_worker->id,
			'inputs' => $function_params,
		];
		
		if(false === ($automation_result = $automator->executeScript($automation, $initial_state, $error))) {
			echo json_encode([
				'exit' => 'error',
				'exit_state' => null,
				'dict' => DevblocksPlatform::services()->string()->yamlEmit([
					'__exit' => 'error',
					'error' => $error,
				], false),
			]);
			return;
		}
		
		$exit_code = $automation_result->get('__exit');
		$exit_state = $automation_result->getKeyPath('__state.next', null);
		
		$return = $automation_result->get('__return', []);
		
		echo json_encode([
			'exit' => $exit_code,
			'exit_state' => $exit_state,
			'return' => $return,
		]);
	}
	
	private function _profileAction_renderEditorToolbar() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		@$trigger = DevblocksPlatform::importGPC($_POST['trigger'], 'string', null);
		
		$toolbar_dict = DevblocksDictionaryDelegate::instance([
			'worker__context' => CerberusContexts::CONTEXT_WORKER,
			'worker_id' => $active_worker->id
		]);
		
		if(false == ($trigger_ext = Extension_AutomationTrigger::get($trigger, true)))
			return;
		
		/** @var $trigger_ext Extension_AutomationTrigger */
		
		$toolbar = $trigger_ext->getEditorToolbar();

		$toolbar = DevblocksPlatform::services()->ui()->toolbar()->parse($toolbar, $toolbar_dict);
		
		DevblocksPlatform::services()->ui()->toolbar()->render($toolbar);
	}
	
	private function _profileAction_runAutomationEditor() {
		$automator = DevblocksPlatform::services()->automation();
		$active_worker = CerberusApplication::getActiveWorker();
		
		@$automation_id = DevblocksPlatform::importGPC($_POST['id'], 'integer', 0);
		@$is_simulator = DevblocksPlatform::importGPC($_POST['is_simulator'], 'integer', 0);
		@$automation_script = DevblocksPlatform::importGPC($_POST['automation_script'], 'string', null);
		@$start_state = DevblocksPlatform::importGPC($_POST['start_state_yaml'], 'string', null);
		@$extension_id = DevblocksPlatform::importGPC($_POST['extension_id'], 'string', null);
		
		$error = null;
		
		header('Content-Type: application/json; charset=utf-8');
		
		// Only admins
		if(!$active_worker->is_superuser) {
			echo json_encode([
				'exit' => 'error',
				'exit_state' => null,
				'dict' => DevblocksPlatform::services()->string()->yamlEmit([
					'__exit' => 'error',
					'error' => "Only administrators are allowed to use the automation editor.",
				], false),
			]);
			return;
		}
		
		if(false == ($automation = DAO_Automation::get($automation_id))) {
			$automation = new Model_Automation();
		}
		
		if($extension_id)
			$automation->extension_id = $extension_id;
		
		$automation->script = $automation_script;
		
		// Override policies on testing
		if($active_worker->is_superuser) {
			@$automation->policy_kata = DevblocksPlatform::importGPC($_POST['automation_policy_kata'], 'string', null);
		}
		
		if(false === ($initial_state = DevblocksPlatform::services()->string()->yamlParse($start_state, 0, $error))) {
			echo json_encode([
				'exit' => 'error',
				'exit_state' => null,
				'dict' => DevblocksPlatform::services()->string()->yamlEmit([
					'__exit' => 'error',
					'error' => $error,
				], false),
			]);
			return;
		}
		
		$initial_state['__simulate'] = $is_simulator;
		
		if(false === ($automation_result = $automator->executeScript($automation, $initial_state, $error))) {
			echo json_encode([
				'exit' => 'error',
				'exit_state' => null,
				'dict' => DevblocksPlatform::services()->string()->yamlEmit([
					'__exit' => 'error',
					'error' => $error,
				], false),
			]);
			return;
		}
		
		$exit_code = $automation_result->get('__exit');
		$exit_state = $automation_result->getKeyPath('__state.next', null);
		
		$end_state = $automation_result->getDictionary();
		ksort($end_state);
		
		// Move the state info to the end
		$state = $end_state['__state'];
		unset($end_state['__state']);
		$end_state['__state'] = $state;
		unset($state);
		
		// Move expandable to the end
		if(array_key_exists('__expandable', $end_state)) {
			$expandable = $end_state['__expandable'];
			unset($end_state['__expandable']);
			$end_state['__expandable'] = $expandable;
			unset($expandable);
		}
		
		unset($end_state['__simulate']);
		
		$yaml_out = DevblocksPlatform::services()->string()->yamlEmit($end_state, false);
		
		echo json_encode([
			'exit' => $exit_code,
			'exit_state' => $exit_state,
			'dict' => $yaml_out,
		]);
	}
	
	private function _profileAction_viewExplore() {
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::services()->url();
		
		if('POST' != DevblocksPlatform::getHttpMethod())
			DevblocksPlatform::dieWithHttpError(null, 403);
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_POST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}

		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = [];
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=automation', true),
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=automation&id=%d-%s", $row[SearchFields_Automation::ID], DevblocksPlatform::strToPermalink($row[SearchFields_Automation::NAME])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_Automation::ID],
					'url' => $url,
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
	
	private function _profileAction_getExtensionConfig() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		if('POST' != DevblocksPlatform::getHttpMethod())
			DevblocksPlatform::dieWithHttpError(null, 405);
		
		// Must be an admin
		if(!$active_worker->is_superuser)
			DevblocksPlatform::dieWithHttpError(null, 403);
		
		@$extension_id = DevblocksPlatform::importGPC($_POST['extension_id'], 'string', null);
		
		if(!$extension_id)
			return;
		
		if(false == ($trigger_ext = Extension_AutomationTrigger::get($extension_id, true)))
			DevblocksPlatform::dieWithHttpError(null, 404);
		
		/* @var $trigger_ext Extension_AutomationTrigger */
		
		$model = new Model_Automation();
		$model->extension_id = $extension_id;
		
		$trigger_ext->renderConfig($model);
	}
	
	private function _profileAction_getAutocompleteJson() {
		if('POST' != DevblocksPlatform::getHttpMethod())
			DevblocksPlatform::dieWithHttpError(null, 405);
		
		@$extension_id = DevblocksPlatform::importGPC($_POST['extension_id'], 'string', null);
		
		if(!$extension_id)
			return;
		
		if(false == ($trigger_ext = Extension_AutomationTrigger::get($extension_id, true)))
			DevblocksPlatform::dieWithHttpError(null, 404);
		
		/* @var $trigger_ext Extension_AutomationTrigger */
		
		header('Content-Type: application/json; charset=utf-8');
		
		echo $trigger_ext->getAutocompleteSuggestionsJson();
	}
}