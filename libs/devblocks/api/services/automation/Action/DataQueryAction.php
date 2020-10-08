<?php
namespace Cerb\AutomationBuilder\Action;

use DevblocksDictionaryDelegate;
use DevblocksPlatform;
use Exception_DevblocksAutomationError;

class DataQueryAction extends AbstractAction {
	const ID = 'data.query';
	
	function activate(\DevblocksDictionaryDelegate $dict, array &$node_memory, \CerbAutomationPolicy $policy, string &$error=null) {
		$validation = DevblocksPlatform::services()->validation();
		$data = DevblocksPlatform::services()->data();
		
		$params = $this->node->getParams($dict);
		
		$inputs = $params['inputs'] ?? [];
		$output = $params['output'] ?? null;
		
		try {
			// Params validation
			
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
			
			$validation->addField('query', 'inputs:query:')
				->string()
				->setMaxLength('16 bits')
				->setRequired(true)
			;
			
			if(false === ($validation->validateAll($inputs, $error)))
				throw new Exception_DevblocksAutomationError($error);
			
			@$query = $inputs['query'];
			
			$query_data = $data->parseQuery($query);
			
			$policy_dict = DevblocksDictionaryDelegate::instance([
				'node' => [
					'id' => $this->node->getId(),
					'type' => self::ID,
				],
				'query' => [
					'type' => $query_data['type'],
				],
				'inputs' => $inputs,
				'output' => $output,
			]);
			
			if(!$policy->isAllowed(self::ID, $policy_dict)) {
				$error = sprintf(
					"The automation policy does not permit this action (%s).",
					self::ID
				);
				throw new Exception_DevblocksAutomationError($error);
			}
			
			if(false == ($results = $data->executeQuery($query, $error))) {
				throw new Exception_DevblocksAutomationError($error);
				
			} else {
				if($output) {
					$dict->set($output, $results);
				}
			}
			
		} catch (Exception_DevblocksAutomationError $e) {
			$error = $e->getMessage();
			
			if(null != ($event_error = $this->node->getChildBySuffix(':on_error'))) {
				$dict->set($output, [
					'error' => $error,
				]);
				
				return $event_error->getId();
			}
			
			return false;
		}
		
		if(null != ($event_success = $this->node->getChildBySuffix(':on_success'))) {
			return $event_success->getId();
		}
		
		return $this->node->getParent()->getId();
	}
}