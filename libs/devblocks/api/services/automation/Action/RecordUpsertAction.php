<?php
namespace Cerb\AutomationBuilder\Action;

use DevblocksDictionaryDelegate;
use DevblocksPlatform;
use Exception_DevblocksAutomationError;
use Extension_DevblocksContext;

class RecordUpsertAction extends AbstractAction {
	const ID = 'record.upsert';
	
	function activate(\DevblocksDictionaryDelegate $dict, array &$node_memory, \CerbAutomationPolicy $policy, string &$error=null) {
		$validation = DevblocksPlatform::services()->validation();
		
		$params = $this->node->getParams($dict);
		
		$inputs = $params['inputs'] ?? [];
		$output = $params['output'] ?? null;
	
		try {
			// Validate params
			
			$validation->addField('inputs', 'inputs:')
				->array()
			;
			
			$validation->addField('output', 'output:')
				->string()
				->setRequired(true)
			;
			
			if(false === ($validation->validateAll($params, $error)))
				throw new Exception_DevblocksAutomationError($error);
			
			// Validate input
			
			$validation->reset();
			
			$validation->addField('record_type', 'inputs:record_type:')
				->context()
				->setRequired(true)
			;
			
			$validation->addField('record_query', 'inputs:record_query:')
				->string()
				->setRequired(true)
			;
			
			$validation->addField('fields', 'inputs:fields:')
				->stringOrArray()
				->setRequired(true)
			;
			
			if(false === ($validation->validateAll($inputs, $error)))
				throw new Exception_DevblocksAutomationError($error);
			
			$action_dict = DevblocksDictionaryDelegate::instance([
				'node' => [
					'id' => $this->node->getId(),
					'type' => self::ID,
				],
				'inputs' => $inputs,
				'output' => $output,
			]);
			
			if(!$policy->isAllowed(self::ID, $action_dict)) {
				$error = sprintf(
					"The automation policy does not permit this action (%s).",
					self::ID
				);
				throw new Exception_DevblocksAutomationError($error);
			}
			
			@$record_type = $inputs['record_type'];
			@$query = $inputs['record_query'];
			@$fields = $inputs['fields'] ?? [];
			
			if(false == ($context_ext = Extension_DevblocksContext::getByAlias($record_type, true))) {
				throw new Exception_DevblocksAutomationError(sprintf(
					"Unknown record type `%s`",
					$record_type
				));
			}
			
			// Make sure we can create records of this type
			if(!$context_ext->manifest->hasOption('records'))
				throw new Exception_DevblocksAutomationError("Upsert not implemented.");
			
			if(false == ($view = $context_ext->getChooserView()))
				throw new Exception_DevblocksAutomationError("Upsert not implemented.");
			
			$view->setAutoPersist(false);
			$view->addParamsWithQuickSearch($query, true);
			list($results, $total) = $view->getData();
			
			if(0 == $total) {
				unset($params['inputs']['record_query']);
				
				$action_node = clone $this->node;
				$action_node->setType('record.create');
				$action_node->setParams($params);
				$action = new RecordCreateAction($action_node);
				return $action->activate($dict, $node_memory, $policy, $error);
				
			} elseif (1 == $total) {
				$params['inputs']['record_id'] = key($results);
				unset($params['inputs']['record_query']);
				
				$action_node = clone $this->node;
				$action_node->setType('record.update');
				$action_node->setParams($params);
				$action = new RecordUpdateAction($action_node);
				return $action->activate($dict, $node_memory, $policy, $error);
				
			} else {
				throw new Exception_DevblocksAutomationError("An upsert query must match exactly one or zero records.");
			}
			
		} catch (Exception_DevblocksAutomationError $e) {
			$error = $e->getMessage();
			
			if (null != ($event_error = $this->node->getChildBySuffix(':on_error'))) {
				if ($output) {
					$dict->set($output, [
						'error' => $error,
					]);
				}
				
				return $event_error->getId();
			}
			
			return false;
		}
	}
}