<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2014, Webgroup Media LLC
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

class PageSection_ProfilesCustomField extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // custom_field 
		$id = array_shift($stack); // 123
		
		@$id = intval($id);
		
		if(null == ($custom_field = DAO_CustomField::get($id))) {
			return;
		}
		$tpl->assign('custom_field', $custom_field);
		
		// Tab persistence
		
		$point = 'profiles.custom_field.tab';
		$tpl->assign('point', $point);
		
		if(null == (@$tab_selected = $stack[0])) {
			$tab_selected = $visit->get($point, '');
		}
		$tpl->assign('tab_selected', $tab_selected);
		
		// Properties
		
		$properties = [];
		
		$properties['name'] = array(
			'label' => mb_ucfirst($translate->_('common.name')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $custom_field->name,
		);
		
		$properties['updated'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.updated'),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $custom_field->updated_at,
		);
		
		// Link counts
		
		$properties_links = array(
			CerberusContexts::CONTEXT_CUSTOM_FIELD => array(
				$custom_field->id => 
					DAO_ContextLink::getContextLinkCounts(
						CerberusContexts::CONTEXT_CUSTOM_FIELD,
						$custom_field->id,
						array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
		);
		
		$tpl->assign('properties_links', $properties_links);
		
		// Properties
		
		$tpl->assign('properties', $properties);
		
		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, CerberusContexts::CONTEXT_CUSTOM_FIELD);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.core::profiles/custom_field.tpl');
	}
	
	function savePeekJsonAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if(!empty($id) && !empty($do_delete)) { // Delete
				if(!$active_worker->hasPriv(sprintf("contexts.%s.delete", CerberusContexts::CONTEXT_CUSTOM_FIELD)))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.delete'));
				
				DAO_CustomField::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else {
				@$context = DevblocksPlatform::importGPC($_REQUEST['context'], 'string', '');
				@$custom_fieldset_id = DevblocksPlatform::importGPC($_REQUEST['custom_fieldset_id'], 'integer', 0);
				@$name = DevblocksPlatform::importGPC($_REQUEST['name'], 'string', '');
				@$pos = DevblocksPlatform::importGPC($_REQUEST['pos'], 'integer', 0);
				@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', []);
				@$type = DevblocksPlatform::importGPC($_REQUEST['type'], 'string', '');
				
				// Only admins can create global custom fields
				if(!$custom_fieldset_id) {
					if(!$active_worker->is_superuser)
						throw new Exception_DevblocksAjaxValidationError("You don't have permission to create global custom fields.");
					
				// Check fieldset privs
				} else {
					if(false == ($custom_fieldset = DAO_CustomFieldset::get($custom_fieldset_id)))
						throw new Exception_DevblocksAjaxValidationError("Invalid custom fieldset.");
					
					if(!Context_CustomFieldset::isWriteableByActor($custom_fieldset, $active_worker)) {
						throw new Exception_DevblocksAjaxValidationError("You don't have permission to modify this fieldset.");
					}
				}
				
				// [TODO] Validate param keys by type
				if(isset($params['options']))
					$params['options'] = DevblocksPlatform::parseCrlfString($params['options']);
				
				if(empty($id)) { // New
					if(!$active_worker->hasPriv(sprintf("contexts.%s.create", CerberusContexts::CONTEXT_CUSTOM_FIELD)))
						throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.create'));
					
					$fields = array(
						DAO_CustomField::CONTEXT => $context,
						DAO_CustomField::CUSTOM_FIELDSET_ID => $custom_fieldset_id,
						DAO_CustomField::NAME => $name,
						DAO_CustomField::PARAMS_JSON => json_encode($params),
						DAO_CustomField::POS => $pos,
						DAO_CustomField::TYPE => $type,
						DAO_CustomField::UPDATED_AT => time(),
					);
					
					if(!DAO_CustomField::validate($fields, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					$id = DAO_CustomField::create($fields);
					
					if(!empty($view_id) && !empty($id))
						C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_CUSTOM_FIELD, $id);
					
				} else { // Edit
					if(!$active_worker->hasPriv(sprintf("contexts.%s.update", CerberusContexts::CONTEXT_CUSTOM_FIELD)))
						throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.edit'));
						
					$fields = array(
						DAO_CustomField::CUSTOM_FIELDSET_ID => $custom_fieldset_id,
						DAO_CustomField::NAME => $name,
						DAO_CustomField::PARAMS_JSON => json_encode($params),
						DAO_CustomField::POS => $pos,
						DAO_CustomField::UPDATED_AT => time(),
					);
					
					if(!DAO_CustomField::validate($fields, $error, $id))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					DAO_CustomField::update($id, $fields);
					
				}
	
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'label' => $name,
					'view_id' => $view_id,
				));
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
	
	function getFieldParamsAction() {
		@$type = DevblocksPlatform::importGPC($_REQUEST['type'],'string',null);
		
		$tpl = DevblocksPlatform::services()->template();
		
		$model = new Model_CustomField();
		$model->type = $type;
		$tpl->assign('model', $model);
		
		$tpl->display('devblocks:cerberusweb.core::internal/custom_fields/field_params.tpl');
	}
	
	function viewExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::services()->url();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
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
//					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=custom_field', true),
					'toolbar_extension_id' => 'cerberusweb.contexts.custom.field.explore.toolbar',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=custom_field&id=%d-%s", $row[SearchFields_CustomField::ID], DevblocksPlatform::strToPermalink($row[SearchFields_CustomField::NAME])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_CustomField::ID],
					'url' => $url,
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
};