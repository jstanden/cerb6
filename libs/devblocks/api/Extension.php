<?php
abstract class DevblocksApplication {

}

trait DevblocksExtensionGetterTrait {
	static $_registry = [];
	
	/**
	 * @internal
	 */
	public static function getAll($as_instances=true, $with_options=null) {
		$extensions = DevblocksPlatform::getExtensions(self::POINT, $as_instances);
		
		if($as_instances)
			DevblocksPlatform::sortObjects($extensions, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($extensions, 'name');
		
		if(!empty($with_options)) {
			if(!is_array($with_options))
				$with_options = array($with_options);

			foreach($extensions as $k => $controller) {
				@$options = $controller->params['options'][0];

				if(!is_array($options) || empty($options)) {
					unset($extensions[$k]);
					continue;
				}

				if(count(array_intersect(array_keys($options), $with_options)) != count($with_options))
					unset($extensions[$k]);
			}
		}
		
		return $extensions;
	}

	/**
	 * @param string $extension_id
	 * @internal
	 */
	public static function get($extension_id, $as_instance=true) {
		if($as_instance && isset(self::$_registry[$extension_id]))
			return self::$_registry[$extension_id];
		
		$extensions = self::getAll(false);
		
		if(!isset($extensions[$extension_id]))
			return null;
		
		$manifest = $extensions[$extension_id]; /* @var $manifest DevblocksExtensionManifest */

		if($as_instance) {
			self::$_registry[$extension_id] = $manifest->createInstance();
			return self::$_registry[$extension_id];
		} else {
			return $extensions[$extension_id];
		}
		
		return null;
	}
}

/**
 * The superclass of instanced extensions.
 *
 * @abstract
 * @ingroup plugin
 */
class DevblocksExtension {
	public $manifest = null;
	public $id  = '';

	/**
	 * Constructor
	 *
	 * @private
	 * @param DevblocksExtensionManifest $manifest
	 * @return DevblocksExtension
	 */

	function __construct($manifest=null) {
		if(empty($manifest))
			return;

		$this->manifest = $manifest;
		$this->id = $manifest->id;
	}

	function getParams() {
		return $this->manifest->getParams();
	}

	function setParam($key, $value) {
		return $this->manifest->setParam($key, $value);
	}

	function getParam($key,$default=null) {
		return $this->manifest->getParam($key, $default);
	}
	
	/**
	 * 
	 * @param string $key
	 * @return boolean
	 */
	function hasOption($key) {
		if(!$this->manifest)
			return false;
		
		return $this->manifest->hasOption($key);
	}
};

class Exception_Devblocks extends Exception {};

class Exception_DevblocksAjaxError extends Exception_Devblocks {};

class Exception_DevblocksAjaxValidationError extends Exception_Devblocks {
	private $_field_name = null;
	
	function __construct($message=null, $field_name=null) {
		parent::__construct($message);
		$this->_field_name = $field_name;
	}
	
	/**
	 * 
	 * @return string
	 */
	function getFieldName() {
		return $this->_field_name;
	}
};

interface IDevblocksHandler_Session {
	static function open($save_path, $session_name);
	static function close();
	static function read($id);
	static function write($id, $session_data);
	static function destroy($id);
	static function gc($maxlifetime);
	static function getAll();
	static function destroyAll();
};

interface IDevblocksContextPeek {
	function renderPeekPopup($context_id=0, $view_id='', $edit=false);
}

interface IDevblocksContextImport {
	function importGetKeys();
	function importKeyValue($key, $value);
	function importSaveObject(array $fields, array $custom_fields, array $meta);
}

interface IDevblocksContextMerge {
	function mergeGetKeys();
}

interface IDevblocksContextBroadcast {
	function broadcastPlaceholdersGet();
	function broadcastRecipientFieldsGet();
	function broadcastRecipientFieldsToEmails(array $fields, DevblocksDictionaryDelegate $dict);
}

interface IDevblocksContextProfile {
	function profileGetUrl($context_id);
	function profileGetFields($model=null);
}

interface IDevblocksContextAutocomplete {
	function autocomplete($term, $query=null);
}

class DevblocksMenuItemPlaceholder {
	var $label = null;
	var $key = null;
	var $l = null;
	var $children = [];
}

interface IDevblocksContextExtension {
	static function isReadableByActor($actor, $models);
	static function isWriteableByActor($actor, $models);
}

abstract class Extension_DevblocksContext extends DevblocksExtension implements IDevblocksContextExtension {
	const ID = 'devblocks.context';
	
	static $_changed_contexts = [];
	
	/**
	 * @internal
	 */
	static function markContextChanged($context, $context_ids) {
		// If event are disabled, skip.
		if(!DevblocksPlatform::services()->event()->isEnabled())
			return;
		
		if(!is_array($context_ids))
			$context_ids = array($context_ids);

		if(!isset(self::$_changed_contexts[$context]))
			self::$_changed_contexts[$context] = [];

		self::$_changed_contexts[$context] = array_merge(self::$_changed_contexts[$context], $context_ids);
	}

	/**
	 * @internal
	 */
	static function flushTriggerChangedContextsEvents() {
		$eventMgr = DevblocksPlatform::services()->event();

		if(is_array(self::$_changed_contexts))
		foreach(self::$_changed_contexts as $context => $context_ids) {
			$eventMgr->trigger(
				new Model_DevblocksEvent(
					'context.update',
					array(
						'context' => $context,
						'context_ids' => $context_ids,
					)
				)
			);
		}

		self::$_changed_contexts = [];
	}

	/**
	 * @internal
	 * 
	 * @param boolean $as_instances
	 * @param array $with_options
	 * @return Extension_DevblocksContext[]
	 */
	public static function getAll($as_instances=false, $with_options=null) {
		$contexts = DevblocksPlatform::getExtensions('devblocks.context', $as_instances, false);
		
		if(
			class_exists('DAO_CustomRecord', true)
			&& false != ($custom_records = DAO_CustomRecord::getAll()) 
			&& is_array($custom_records)
			) {
			foreach($custom_records as $custom_record) {
				$options = [
					'autocomplete' => '',
					'cards' => '',
					'custom_fields' => '',
					'links' => '',
					'records' => '',
					'search' => '',
					'snippets' => '',
					'va_variable' => '',
					'watchers' => '',
					'workspace' => '',
				];
				
				if(is_array(@$custom_record->params['options']) && in_array('hide_search', $custom_record->params['options']))
					unset($options['search']);
				
				if(is_array(@$custom_record->params['options']) && in_array('avatars', $custom_record->params['options']))
					$options['avatars'] = '';
				
				$context_id = sprintf('contexts.custom_record.%d', $custom_record->id);
				$manifest = new DevblocksExtensionManifest();
				$manifest->id = $context_id;
				$manifest->plugin_id = 'cerberusweb.core';
				$manifest->point = Extension_DevblocksContext::ID;
				$manifest->name = $custom_record->name;
				$manifest->file = 'api/dao/abstract_custom_record.php';
				$manifest->class = 'Context_AbstractCustomRecord_' . $custom_record->id;
				$manifest->params = [
					//'alias' => 'custom_record_' . $custom_record->id,
					'alias' => $custom_record->uri,
					'dao_class' => 'DAO_AbstractCustomRecord_' . $custom_record->id,
					'view_class' => 'View_AbstractCustomRecord_' . $custom_record->id,
					'acl' => [
						0 => [
							'broadcast' => '',
							'comment' => '',
							'create' => '',
							'delete' => '',
							'export' => '',
							'import' => '',
							'merge' => '',
							'update' => '',
							'update.bulk' => '',
						],
					],
					'options' => [
						0 => $options,
					],
					'names' => [
						0 => [
							DevblocksPlatform::strLower($custom_record->name) => 'singular',
							DevblocksPlatform::strLower($custom_record->name_plural) => 'plural',
						]
					],
				];
				
				if($as_instances) {
					$contexts[$context_id] = $manifest->createInstance();
				} else {
					$contexts[$context_id] = $manifest;
				}
			}
			
			if(!empty($with_options)) {
				if(!is_array($with_options))
					$with_options = array($with_options);
	
				foreach($contexts as $k => $context) {
					@$options = $context->params['options'][0];
	
					if(!is_array($options) || empty($options)) {
						unset($contexts[$k]);
						continue;
					}
	
					if(count(array_intersect(array_keys($options), $with_options)) != count($with_options))
						unset($contexts[$k]);
				}
			}
		}
		
		if($as_instances)
			DevblocksPlatform::sortObjects($contexts, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($contexts, 'name');
		
		return $contexts;
	}
	
	public static function getUris() {
		$uris = array_map(function($mft) {
			return $mft->params['alias'];
		}, Extension_DevblocksContext::getAll(false));
		
		asort($uris);
		
		return $uris;
	}
	
	/**
	 * @internal
	 */
	public static function getAliasesForAllContexts() {
		$cache = DevblocksPlatform::services()->cache();
		
		if(null !== ($results = $cache->load(DevblocksPlatform::CACHE_CONTEXT_ALIASES)))
			return $results;
		
		$contexts = self::getAll(false);
		$results = [];
		
		if(is_array($contexts))
		foreach($contexts as $ctx_id => $ctx) { /* @var $ctx DevblocksExtensionManifest */
			$ctx_aliases = self::getAliasesForContext($ctx);
			
			@$uri = $ctx_aliases['uri'];
			$results[$uri] = $ctx_id;
			
			if(isset($ctx_aliases['aliases']) && is_array($ctx_aliases['aliases']))
			foreach(array_keys($ctx_aliases['aliases']) as $alias) {
				// If this alias is already defined and it's not the priority URI for this context, skip
				if(isset($results[$alias]) && $alias != $uri)
					continue;
				
				$results[$alias] = $ctx_id;
			}
		}
		
		$cache->save($results, DevblocksPlatform::CACHE_CONTEXT_ALIASES);
		return $results;
	}

	/**
	 * @internal
	 */
	public static function getAliasesForContext(DevblocksExtensionManifest $ctx_manifest) {
		@$names = $ctx_manifest->params['names'][0];
		@$uri = $ctx_manifest->params['alias'];
		
		$results = array(
			'singular' => '',
			'plural' => '',
			'singular_short' => '',
			'plural_short' => '',
			'uri' => $uri,
			'aliases' => [],
		);
		
		if(!empty($uri))
			$results['aliases'][$uri] = array('uri');
		
		if(is_array($names) && !empty($names))
		foreach($names as $name => $meta) {
			$name = mb_convert_case($name, MB_CASE_LOWER);
			@$meta = explode(' ', $meta) ?: [];
			
			$is_plural = in_array('plural', $meta);
			$is_short = in_array('short', $meta);
			
			if(!$is_plural && !$is_short && empty($results['singular']))
				$results['singular'] = $name;
			else if($is_plural && !$is_short && empty($results['plural']))
				$results['plural'] = $name;
			else if(!$is_plural && $is_short && empty($results['singular_short']))
				$results['singular_short'] = $name;
			else if($is_plural && $is_short && empty($results['plural_short']))
				$results['plural_short'] = $name;
			
			$results['aliases'][$name] = $meta;
		}
		
		if(empty($results['singular']))
			$results['singular'] = mb_convert_case($ctx_manifest->name, MB_CASE_LOWER);
		
		return $results;
	}
	
	/**
	 * @internal
	 * 
	 * @param string $alias
	 * @param bool $as_instance
	 * @return Extension_DevblocksContext|DevblocksExtensionManifest
	 */
	public static function getByAlias($alias, $as_instance=false) {
		$aliases = self::getAliasesForAllContexts();
		
		// First, try the fully-qualified ID
		if($alias && false != ($ctx = Extension_DevblocksContext::get($alias, $as_instance))) {
			return $ctx;
		}
		
		// Otherwise, try it as an alias
		@$ctx_id = $aliases[$alias];
		
		// If this is a valid context, return it
		if($ctx_id && false != ($ctx = Extension_DevblocksContext::get($ctx_id, $as_instance))) {
			return $ctx;
		}
		
		return null;
	}
	
	/**
	 * @internal
	 */
	public static function getByViewClass($view_class, $as_instance=false) {
		$contexts = self::getAll(false);

		if(is_array($contexts))
		foreach($contexts as $ctx) { /* @var $ctx DevblocksExtensionManifest */
			if(isset($ctx->params['view_class']) && 0 == strcasecmp($ctx->params['view_class'], $view_class)) {
				if($as_instance) {
					return $ctx->createInstance();
				} else {
					return $ctx;
				}
			}
		}

		return null;
	}
	
	/**
	 * @internal
	 */
	public static function getByMacros($as_instances=false) {
		$contexts = Extension_DevblocksContext::getAll(false);
		$macro_contexts = Extension_DevblocksEvent::getWithMacroContexts();
		$results = [];
		
		array_walk($macro_contexts, function($macro) use ($contexts, &$results, $as_instances) {
			$macro_context = $macro->params['macro_context'];
			
			if(isset($contexts[$macro_context])) {
				if($as_instances) {
					$results[$macro->id] = $contexts[$macro_context]->createInstance();
				} else {
					$results[$macro->id] = $contexts[$macro_context];
				}
			}
		});
		
		return $results;
	}

	/**
	 * @internal
	 * 
	 * @param string $context
	 * @return Extension_DevblocksContext
	 */
	public static function get($context, $as_instance=true) {
		static $_cache = [];
		
		if($as_instance && isset($_cache[$context]))
			return $_cache[$context];
		
		$contexts = self::getAll(false);
		
		if(isset($contexts[$context])) {
			$manifest = $contexts[$context]; /* @var $manifest DevblocksExtensionManifest */
			
			if(!$as_instance) {
				return $manifest;
				
			} else {
				$_cache[$context] = $manifest->createInstance();
				return $_cache[$context];
			}
		}

		return false;
	}
	
	/**
	 * @internal
	 */
	static function getOwnerTree(array $contexts=[CerberusContexts::CONTEXT_APPLICATION, CerberusContexts::CONTEXT_ROLE, CerberusContexts::CONTEXT_GROUP, CerberusContexts::CONTEXT_BOT, CerberusContexts::CONTEXT_WORKER]) {
		$active_worker = CerberusApplication::getActiveWorker();
		
		$bots = DAO_Bot::getWriteableByActor($active_worker);
		$groups = DAO_Group::getAll();
		$roles = DAO_WorkerRole::getAll();
		$workers = DAO_Worker::getAllActive();

		$owners = [];

		if(in_array(CerberusContexts::CONTEXT_WORKER, $contexts)) {
			$item = new DevblocksMenuItemPlaceholder();
			$item->label = 'Me';
			$item->l = 'Me';
			$item->key = CerberusContexts::CONTEXT_WORKER . ':' . $active_worker->id;
			
			$owners['Me'] = $item;
		}
		
		// Apps
		
		if(in_array(CerberusContexts::CONTEXT_APPLICATION, $contexts) && $active_worker->is_superuser) {
			$item = new DevblocksMenuItemPlaceholder();
			$item->label = 'Cerb';
			$item->l = 'Cerb';
			$item->key = CerberusContexts::CONTEXT_APPLICATION . ':' . 0;
			$owners['App'] = $item;
		}
		
		// Bots
		
		if(in_array(CerberusContexts::CONTEXT_BOT, $contexts)) {
			$bots_menu = new DevblocksMenuItemPlaceholder();
			
			foreach($bots as $bot) {
				$item = new DevblocksMenuItemPlaceholder();
				$item->label = $bot->name;
				$item->l = $bot->name;
				$item->key = CerberusContexts::CONTEXT_BOT . ':' . $bot->id;
				$bots_menu->children[$item->l] = $item;
			}
			
			$owners['Bot'] = $bots_menu;
		}
		
		// Groups
		
		if(in_array(CerberusContexts::CONTEXT_GROUP, $contexts)) {
			$groups_menu = new DevblocksMenuItemPlaceholder();
			
			foreach($groups as $group) {
				if(!$active_worker->isGroupManager($group->id))
					continue;
				
				$item = new DevblocksMenuItemPlaceholder();
				$item->label = $group->name;
				$item->l = $item->label;
				$item->key = CerberusContexts::CONTEXT_GROUP . ':' . $group->id;
				$groups_menu->children[$item->l] = $item;
			}
			
			$owners['Group'] = $groups_menu;
		}
		
		// Roles
		
		if(in_array(CerberusContexts::CONTEXT_ROLE, $contexts)) {
			$roles_menu = new DevblocksMenuItemPlaceholder();
			$role_ownerships = DAO_WorkerRole::getEditableBy($active_worker->id);
			
			// Include roles if the current worker is a role owner or an admin
			foreach($roles as $role) {
				if(!$active_worker->is_superuser && !array_key_exists($role->id, $role_ownerships))
					continue;
				
				$item = new DevblocksMenuItemPlaceholder();
				$item->label = $role->name;
				$item->l = $item->label;
				$item->key = CerberusContexts::CONTEXT_ROLE . ':' . $role->id;
				$roles_menu->children[$item->l] = $item;
			}
			
			$owners['Role'] = $roles_menu;
		}
		
		// Workers
		
		if(in_array(CerberusContexts::CONTEXT_WORKER, $contexts)) {
			$workers_menu = new DevblocksMenuItemPlaceholder();
			
			foreach($workers as $worker) {
				if(!($active_worker->is_superuser || $active_worker->id == $worker->id))
					continue;
				
				$item = new DevblocksMenuItemPlaceholder();
				$item->label = $worker->getName();
				$item->l = $item->label;
				$item->key = CerberusContexts::CONTEXT_WORKER . ':' . $worker->id;
				$workers_menu->children[$item->l] = $item;
			}
			
			$owners['Worker'] = $workers_menu;
		}
		
		return $owners;
	}
	
	/**
	 * @internal
	 */
	static function getPlaceholderTree($labels, $label_separator=' ', $key_separator=' ', $condense=true) {
		natcasesort($labels);
		
		$keys = new DevblocksMenuItemPlaceholder();
		
		// Tokenize the placeholders
		foreach($labels as &$label) {
			$label = trim($label);
			
			$parts = explode($label_separator, $label);
			
			$ptr =& $keys->children;
			
			while($part = array_shift($parts)) {
				if(!isset($ptr[$part])) {
					$ptr[$part] = new DevblocksMenuItemPlaceholder();
				}
				
				$ptr =& $ptr[''.$part]->children;
			}
		}
		
		// Convert the flat tokens into a tree
		$forward_recurse = null;
		$forward_recurse = function(&$node, $node_key, &$stack=null) use (&$keys, &$forward_recurse, &$labels, $label_separator) {
			if(is_null($stack))
				$stack = [];
			
			if(!empty($node_key))
				array_push($stack, ''.$node_key);

			$label = implode($label_separator, $stack);
			
			if(false != ($key = array_search($label, $labels))) {
				$node->label = $label;
				$node->key = $key;
				$node->l = $node_key;
			}
			
			if(is_array($node->children))
			foreach($node->children as $k => &$n) {
				$forward_recurse($n, $k, $stack);
			}
			
			array_pop($stack);
		};
		
		$forward_recurse($keys, '');
		
		$condense_func = null;
		$condense_func = function(&$node, $key=null, &$parent=null) use (&$condense_func, $label_separator, $key_separator) {
			// If this node has exactly one child
			if(is_array($node->children) && 1 == count($node->children) && $parent && is_null($node->label)) {
				reset($node->children);
				
				// Replace the current node with its only child
				$k = key($node->children);
				$n = array_pop($node->children);
				
				if(is_object($n))
					$n->l = $key . $label_separator . $n->l;
				
				// Deconstruct our parent
				$keys = array_keys($parent->children);
				$vals = array_values($parent->children);
				
				// Replace this node's key and value in the parent
				$idx = array_search($key, $keys);
				$keys[$idx] = $key.$key_separator.$k;
				$vals[$idx] = $n;
				
				// Reconstruct the parent
				$parent->children = array_combine($keys, $vals);
				
				// Recurse through the parent again
				foreach($parent->children as $k => &$n)
					$condense_func($n, $k, $parent);
				
			} else {
				// If this node still has children, recurse into them
				if(is_array($node->children))
				foreach($node->children as $k => &$n)
					$condense_func($n, $k, $node);
			}
			
		};
		
		if($condense)
			$condense_func($keys);
		
		return $keys->children;
	}

	abstract function getRandom();
	abstract function getMeta($context_id);
	abstract function getContext($object, &$token_labels, &$token_values, $prefix=null);
	
	function getKeyToDaoFieldMap() {
		$map = [];
		
		if($this->hasOption('custom_fields')) {
			$map['fieldsets'] = '_fieldsets';
		}
		
		if($this->hasOption('links')) {
			$map['links'] = '_links';
		}

		return $map;
	}
	
	function getKeyMeta() {
		$field_map = $this->getKeyToDaoFieldMap();
		$dao_class = $this->getDaoClass();
		$dao_fields = $dao_class::getFields();
		
		$keys = [];
		
		foreach($field_map as $record_key => $dao_key) {
			if(false == (@$dao_field = $dao_fields[$dao_key]))
				continue;
			
			// Only editable fields
			if(!$dao_field->_type->isEditable())
				continue;
			
			$type = $dao_field->_type->getName();
			$notes = [];
				
			switch($type) {
				case 'context':
					//$type = 'record type';
					break;
					
				case 'number':
				case 'uint':
					if(
						array_key_exists('min', $dao_field->_type->_data)
						&& array_key_exists('max', $dao_field->_type->_data)
					) {
						$notes[] = sprintf("(%d-%d)",
							$dao_field->_type->_data['min'],
							$dao_field->_type->_data['max']
						);
					}
					break;
					
				case 'string':
					if(
						array_key_exists('possible_values', $dao_field->_type->_data)
					) {
						$notes[] = sprintf("[%s]",
							implode(', ', array_map(function($v) {
								if(empty($v))
									return '""';
								
								return $v;
							}, $dao_field->_type->_data['possible_values']))
						);
					}
					break;
			}
			
			$keys[$record_key] = [
				'dao_field' => $dao_field,
				'is_immutable' => !$dao_field->_type->isEditable(),
				'is_required' => $dao_field->_type->isRequired(),
				'notes' => implode('; ', $notes),
				'type' => $type,
			];
		}
		
		if(array_key_exists('name', $keys)) {
			$aliases = Extension_DevblocksContext::getAliasesForContext($this->manifest);
			$keys['name']['notes'] = "The name of this " . $aliases['singular'];
		}
		
		if(array_key_exists('created', $keys)) {
			$keys['created']['notes'] = "The date/time when this record was created";
		}
		if(array_key_exists('created_at', $keys)) {
			$keys['created_at']['notes'] = "The date/time when this record was created";
		}
		
		if(array_key_exists('owner__context', $keys)) {
			$aliases = Extension_DevblocksContext::getAliasesForContext($this->manifest);
			$keys['owner__context']['notes'] = "The [record type](/docs/records/types/) of this " . $aliases['singular'] . "'s owner: `app`, `role`, `group`, or `worker`";
		}
		if(array_key_exists('owner_id', $keys)) {
			$aliases = Extension_DevblocksContext::getAliasesForContext($this->manifest);
			$keys['owner_id']['notes'] = "The ID of this " . $aliases['singular'] . "'s owner";
		}
		
		if(array_key_exists('updated', $keys)) {
			$keys['updated']['notes'] = "The date/time when this record was last modified";
		}
		if(array_key_exists('updated_at', $keys)) {
			$keys['updated_at']['notes'] = "The date/time when this record was last modified";
		}
		
		if(array_key_exists('fieldsets', $keys)) {
			$keys['fieldsets']['type'] = 'fieldsets';
			$keys['fieldsets']['notes'] = 'An array or comma-separated list of [custom fieldset](/docs/records/types/custom_fieldset/) IDs';
		}
		
		if(array_key_exists('links', $keys)) {
			$keys['links']['type'] = 'links';
			$keys['links']['notes'] = 'An array of record `type:id` tuples to link to. Prefix with `-` to unlink.';
		}
		
		return $keys;
	}
	
	function getDaoFieldsFromKeyAndValue($key, $value, &$out_fields, &$error) {
		return true;
	}
	
	function getDaoFieldsFromKeysAndValues($data, &$out_fields, &$out_custom_fields, &$error) {
		$out_fields = $out_custom_fields = [];
		$error = null;
		
		$context = $this->id;
		
		$map = $this->getKeyToDaoFieldMap();
		
		if(!$this->_getDaoCustomFieldsFromKeysAndValues($context, $data, $out_custom_fields, $error))
			return false;
		
		// Remove custom fields from data
		if(is_array($out_custom_fields))
		foreach($out_custom_fields as $field_id => $value)
			unset($data['custom_' . $field_id]);
		
		if(is_array($data))
		foreach($data as $key => $value) {
			$fields = [];
			
			if(!$this->getDaoFieldsFromKeyAndValue($key, $value, $fields, $error))
				return false;
			
			if(!empty($fields)) {
				$out_fields = array_merge($out_fields, $fields);
				continue;
			}
			
			if(!isset($map[$key])) {
				$error = sprintf("'%s' is not an editable field.", $key);
				return false;
			}
			
			$out_fields[$map[$key]] = $value;
		}
		
		// Links
		
		if(!$this->_getDaoLinksForContext($context, $data, $out_fields, $error))
			return false;
		
		// Custom fieldsets
		
		if(!$this->_getDaoCustomFieldsetsForContext($context, $data, $out_fields, $error))
			return false;
		
		return true;
	}
	
	function getDefaultProperties() {
		return [];
	}
	
	/**
	 * @internal
	 * 
	 * @return array
	 */
	function getCardProperties() {
		// Load cascading properties
		$properties = DevblocksPlatform::getPluginSetting('cerberusweb.core', 'card:' . $this->id, [], true);
		
		if(empty($properties))
			$properties = $this->getDefaultProperties();
		
		return $properties;
	}

	/**
	 * @internal
	 */
	function getCardSearchButtons(DevblocksDictionaryDelegate $dict, array $search_buttons=[]) {
		$tpl_builder = DevblocksPlatform::services()->templateBuilder();
		
		$search_buttons = array_merge(
			$search_buttons,
			DevblocksPlatform::getPluginSetting('cerberusweb.core', 'card:search:' . $this->id, [], true)
		);
		
		$results = [];
		
		if(is_array($search_buttons))
		foreach($search_buttons as $search_button) {
			if(false == ($search_button_context = Extension_DevblocksContext::get($search_button['context'], true)))
				continue;
			
			if(false == ($view = $search_button_context->getTempView()))
				continue;
			
			$label_aliases = Extension_DevblocksContext::getAliasesForContext($search_button_context->manifest);
			$label_singular = @$search_button['label_singular'] ?: $label_aliases['singular'];
			$label_plural = @$search_button['label_plural'] ?: $label_aliases['plural'];
			
			$search_button_query = $tpl_builder->build($search_button['query'], $dict);
			$view->addParamsWithQuickSearch($search_button_query);
			
			$total = $view->getData()[1];
			
			$results[] = [
				'label' => ($total == 1 ? $label_singular : $label_plural),
				'context' => $search_button_context->id,
				'count' => $total,
				'query' => $search_button_query,
			];
		}
		
		return $results;
	}
	
	/*
	 * @return Cerb_ORMHelper
	 */
	function getDaoClass() {
		$class = str_replace('Context_','DAO_', get_called_class());
		
		if(!class_exists($class))
			return false;
		
		return $class;
	}
	
	/*
	 * @return DevblocksSearchFields
	 */
	function getSearchClass() {
		$class = str_replace('Context_','SearchFields_', get_called_class());
		
		if(!class_exists($class))
			return false;
		
		return $class;
	}

	function getViewClass() {
		$class = str_replace('Context_','View_', get_called_class());
		
		if(!class_exists($class))
			return false;
		
		return $class;
	}

	/**
	 * @internal
	 */
	function getModelObjects(array $ids) {
		$ids = DevblocksPlatform::importVar($ids, 'array:integer');
		$models = [];

		if(null == ($dao_class = $this->getDaoClass()))
			return $models;

		if(method_exists($dao_class, 'getIds')) {
			$models = $dao_class::getIds($ids);

		} elseif(method_exists($dao_class, 'getWhere')) {
			$where = sprintf("id IN (%s)",
				implode(',', $ids)
			);

			// Get without sorting (optimization, no file sort)
			$models = $dao_class::getWhere($where, null);
		}

		return $models;
	}

	/**
	 * @internal
	 */
	public function formatDictionaryValue($key, DevblocksDictionaryDelegate $dict) {
		$translate = DevblocksPlatform::getTranslationService();

		@$type = $dict->_types[$key];
		$value = $dict->$key;

		switch($type) {
			case 'context_url':
				// Try to find the context+id pair for this key
				$parts = explode('_', str_replace('__','_',$key));

				// Start with the longest sub-token, and decrease until found
				while(array_pop($parts)) {
					$prefix = implode('_', $parts);
					$test_key = $prefix . '__context';

					@$context = $dict->$test_key;

					if(!empty($context)) {
						$id_key = $prefix . '_id';
						$context_id = $dict->$id_key;

						if(!empty($context_id)) {
							$context_url = sprintf("ctx://%s:%d/%s",
								$context,
								$context_id,
								$value
							);
							return $context_url;

						} else {
							return $value;

						}
					}
				}

				break;

			case 'percent':
				if(is_float($value)) {
					$value = sprintf("%0.2f%%",
						($value * 100)
					);

				} elseif(is_numeric($value)) {
					$value = sprintf("%d%%",
						$value
					);
				}
				break;

			case 'size_bytes':
				$value = DevblocksPlatform::strPrettyBytes($value);
				break;

			case 'time_secs':
				//$value = DevblocksPlatform::strPrettyTime($value, true);
				break;

			case 'time_mins':
				$secs = intval($value) * 60;
				$value = DevblocksPlatform::strSecsToString($secs, 2);
				break;

			case Model_CustomField::TYPE_CHECKBOX:
				$value = (!empty($value)) ? $translate->_('common.yes') : $translate->_('common.no');
				break;

			case Model_CustomField::TYPE_DATE:
				$value = DevblocksPlatform::strPrettyTime($value);
				break;
		}

		return $value;
	}

	/**
	 * @internal
	 * 
	 * @param string $view_id
	 * @return C4_AbstractView
	 */
	public function getTempView($view_id=null) {
		if(false == ($defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass())))
			return NULL;
		
		$defaults->id = $view_id ?: uniqid();
		$defaults->is_ephemeral = true;
		$defaults->options = [];
		
		if(null != ($view = C4_AbstractViewLoader::unserializeAbstractView($defaults, false))) {
			$view->setAutoPersist(false);
			return $view;
		}
		
		return NULL;
	}
	
	/**
	 * @internal
	 * 
	 * @param string $view_id
	 * @return C4_AbstractView
	 */
	public function getSearchView($view_id=null) {
		$translate = DevblocksPlatform::getTranslationService();
		if(empty($view_id)) {
			$view_id = sprintf("search_%s",
				str_replace('.','_',DevblocksPlatform::strToPermalink($this->id,'_'))
			);
		}
		
		if(null == ($view = C4_AbstractViewLoader::getView($view_id))) {
			if(null == ($view = $this->getChooserView($view_id))) /* @var $view C4_AbstractViewModel */
				return;
		}
		
		$view->name = $translate->_('common.search_results');
		$view->is_ephemeral = false;

		return $view;
	}

	/**
	 * 
	 * @param string $view_id
	 * @return C4_AbstractView
	 */
	abstract function getChooserView($view_id=null);
	abstract function getView($context=null, $context_id=null, $options=[], $view_id=null);

	function lazyLoadGetKeys() {
		$context_mft = Extension_DevblocksContext::get(static::ID, false);
		
		$lazy_keys = [];
		
		if($context_mft->hasOption('custom_fields')) {
			$lazy_keys['custom_<id>'] = [
				'label' => 'Custom Fields',
				'type' => 'Mixed',
			];
		}
		
		if($context_mft->hasOption('links')) {
			$lazy_keys['links'] = [
				'label' => 'Links',
				'type' => 'Links',
			];
		}
		
		if($context_mft->hasOption('watchers')) {
			$lazy_keys['watchers'] = [
				'label' => 'Watchers',
				'type' => 'Watchers',
			];
		}
		
		return $lazy_keys;
	}
	
	function lazyLoadContextValues($token, $dictionary) { return []; }

	/**
	 * @internal
	 */
	protected function _broadcastRecipientFieldsGet($context, $context_label, array $use=[]) {
		$token_labels = $token_values = [];
		CerberusContexts::getContext($context, $context_label, $token_labels, $token_values, null, true);
		
		$labels = $token_values['_labels'];
		
		$custom_fields = DAO_CustomField::getAll();
		
		// Include any known email addresses or workers
		$results = [
			'links.address' => $context_label . ' linked email addresses',
			'links.contacts' => $context_label . ' linked contacts',
			'links.orgs' => $context_label . ' linked organizations',
			'links.worker' => $context_label . ' watchers',
		];
		
		// Append specified keys
		foreach($use as $k)
			if(isset($labels[$k]))
				$results[$k] = $labels[$k];
		
		array_walk($labels, function($label, $key) use ($custom_fields, $labels, &$results) {
			$matches = [];
			if(preg_match('#^(.*?)\_*custom\_(\d+)$#', $key, $matches)) {
				$field_id = $matches[2];
				
				if(false == (@$field = $custom_fields[$field_id]))
					return;
				
				switch($field->type) {
					case Model_CustomField::TYPE_LINK:
						switch($field->params['context']) {
							case CerberusContexts::CONTEXT_ADDRESS:
							case CerberusContexts::CONTEXT_CONTACT:
							case CerberusContexts::CONTEXT_ORG:
							case CerberusContexts::CONTEXT_WORKER:
								$results[$key] = $labels[$key . '__label'];
								break;
						}
						break;
						
					case Model_CustomField::TYPE_WORKER:
						$results[$key] = $label;
						break;
				}
			}
		});
		
		return $results;
	}
	
	/**
	 * @internal
	 */
	protected function _broadcastPlaceholdersGet($context) {
		$token_labels = $token_values = [];
		CerberusContexts::getContext($context, null, $token_labels, $token_values, null, true);
		
		$merge_token_labels = $merge_token_values = [];
		CerberusContexts::getContext(CerberusContexts::CONTEXT_ADDRESS, null, $merge_token_labels, $merge_token_values, null, true);

		CerberusContexts::merge(
			'broadcast_email_',
			'Broadcast ',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);
		
		return $token_values;
	}
	
	/**
	 * @internal
	 */
	protected function _broadcastRecipientFieldsToEmails(array $fields, DevblocksDictionaryDelegate $dict) {
		$emails = [];
		$custom_fields = DAO_CustomField::getAll();
		
		foreach($fields as $field) {
			switch($field) {
				case 'links.address':
					@$links = array_shift(DAO_ContextLink::getContextLinks($dict->_context, $dict->id, CerberusContexts::CONTEXT_ADDRESS));
					
					if(is_array($links) && !empty($links)) {
						$addresses = DAO_Address::getIds(array_keys($links));
						$addresses = array_column(DevblocksPlatform::objectsToArrays($addresses), 'email', 'id');
						$emails = array_merge($emails, array_values($addresses));
					}
					break;
					
				case 'links.contacts':
					@$links = array_shift(DAO_ContextLink::getContextLinks($dict->_context, $dict->id, CerberusContexts::CONTEXT_CONTACT));
					
					if(is_array($links) && !empty($links)) {
						$contacts = DAO_Contact::getIds(array_keys($links));
						$address_ids = array_column(DevblocksPlatform::objectsToArrays($contacts), 'primary_email_id', 'id');
						$addresses = DAO_Address::getIds($address_ids);
						$addresses = array_column(DevblocksPlatform::objectsToArrays($addresses), 'email', 'id');
						$emails = array_merge($emails, array_values($addresses));
					}
					break;
					
				case 'links.orgs':
					@$links = array_shift(DAO_ContextLink::getContextLinks($dict->_context, $dict->id, CerberusContexts::CONTEXT_ORG));
					
					if(is_array($links) && !empty($links)) {
						$orgs = DAO_ContactOrg::getIds(array_keys($links));
						$address_ids = array_column(DevblocksPlatform::objectsToArrays($orgs), 'email_id', 'id');
						$addresses = DAO_Address::getIds($address_ids);
						$addresses = array_column(DevblocksPlatform::objectsToArrays($addresses), 'email', 'id');
						$emails = array_merge($emails, array_values($addresses));
					}
					break;
					
				case 'links.worker':
					@$links = array_shift(DAO_ContextLink::getContextLinks($dict->_context, $dict->id, CerberusContexts::CONTEXT_WORKER));
					
					if(is_array($links) && !empty($links)) {
						$workers = DAO_Worker::getIds(array_keys($links));
						$address_ids = array_column(DevblocksPlatform::objectsToArrays($workers), 'email_id', 'id');
						$addresses = DAO_Address::getIds($address_ids);
						$addresses = array_column(DevblocksPlatform::objectsToArrays($addresses), 'email', 'id');
						$emails = array_merge($emails, array_values($addresses));
					}
					break;
					
				default:
					$matches = [];
					if(preg_match('#^(.*?)\_*custom\_(\d+)$#', $field, $matches)) {
						$field_id = $matches[2];
						
						if(false == ($custom_field = $custom_fields[$field_id]))
							break;
						
						switch($custom_field->type) {
							case Model_CustomField::TYPE_LINK:
								switch($custom_field->params['context']) {
									case CerberusContexts::CONTEXT_ADDRESS:
										if(false != ($email = $dict->get($field . '__label')))
											$emails[] = $email;
										break;
										
									case CerberusContexts::CONTEXT_CONTACT:
										$field_key = $field . '_email_address_id';
										$dict->$field_key;
										
										if(false != ($email = $dict->get($field . '_email_address')))
											$emails[] = $email;
										break;
										
									case CerberusContexts::CONTEXT_ORG:
										$field_key = $field . '_email_address_id';
										$dict->$field_key;
										
										if(false != ($email = $dict->get($field . '_email_address')))
											$emails[] = $email;
										break;
										
									case CerberusContexts::CONTEXT_WORKER:
										$field_key = $field . '_address_id';
										$dict->$field_key;
										
										if(false != ($email = $dict->get($field . '_address_address')))
											$emails[] = $email;
										break;
								}
								break;
								
							case Model_CustomField::TYPE_WORKER:
								$field_key = $field . '_address_id';
								$dict->$field_key;
						
								$email = $dict->get($field . '_address_address');
								if($email)
									$emails[] = $email;
								break;
						}
						
					} else {
						if(isset($dict->$field) && !empty($dict->$field)) {
							$emails[] = $dict->$field;
						}
					}
					break;
			}
		}
		
		return $emails;
	}
	
	/**
	 * @internal
	 */
	protected function _getDaoCustomFieldsetsForContext($context, array &$data, &$out_fields, &$error=null) {
		$error = null;
		
		if(!array_key_exists('fieldsets', $data))
			return true;
		
		@$value = $data['fieldsets'];
		
		if($this->hasOption('custom_fields')) {
			if(false == ($this->_getDaoFieldsets($value, $out_fields, $error)))
				return false;
		}
		
		return true;
	}
	
	/**
	 * @internal
	 */
	protected function _getDaoLinksForContext($context, array &$data, &$out_fields, &$error=null) {
		$error = null;
		
		if(!array_key_exists('links', $data))
			return true;
		
		@$value = $data['links'];
		
		if($this->hasOption('links')) {
			if(false == ($this->_getDaoFieldsLinks($value, $out_fields, $error)))
				return false;
		}
		
		return true;
	}
	
	/**
	 * @internal
	 */
	protected function _getDaoCustomFieldsFromKeysAndValues($context, array &$data, &$out_custom_fields, &$error=null) {
		$error = null;
		$custom_fields = null;
		
		if(is_array($data))
		foreach($data as $key => $value) {
			if(DevblocksPlatform::strStartsWith($key, 'custom_') 
				&& false !== ($custom_field_id = mb_substr($key,strrpos($key,'_')+1))
				&& is_numeric($custom_field_id)
				) {
				if(is_null($custom_fields))
					$custom_fields = DAO_CustomField::getByContext($context);
				
				if(!isset($custom_fields[$custom_field_id])) {
					$error = sprintf("'%s' is not a valid custom field", $key);
					return false;
				}
				
				$out_custom_fields[$custom_field_id] = $value;
			}
		}
		
		return true;
	}
	
	/**
	 * @internal
	 */
	protected function _getDaoFieldsets($value, &$out_fields, &$error) {
		$fieldset_ids = [];
		
		if(!is_string($value) && !is_array($value)) {
			$error = 'must be an array or comma-separated list of fieldset IDs.';
			return false;
		}
		
		if(is_array($value)) {
			$fieldset_ids = $value;
		} else if(is_string($value)) {
			$fieldset_ids = DevblocksPlatform::parseCsvString($value);
		}
		
		$custom_fieldsets = DAO_CustomFieldset::getIds($fieldset_ids);
		
		$fieldset_ids = array_keys($custom_fieldsets);
		
		if(false == ($json = json_encode($fieldset_ids))) {
			$error = 'could not be JSON encoded.';
			return false;
		}
		
		$out_fields['_fieldsets'] = $json;
		
		return true;
	}
	
	/**
	 * @internal
	 */
	protected function _getDaoFieldsLinks($value, &$out_fields, &$error) {
		if(!is_array($value)) {
			$error = 'must be an array of context:id pairs.';
			return false;
		}
		
		$links = [];
		
		if(is_array($value))
		foreach($value as &$tuple) {
			$is_remove = false;
			
			if(DevblocksPlatform::strStartsWith($tuple, ['-'])) {
				$is_remove = true;
				$tuple = ltrim($tuple,'-');
			}
			
			@list($context, $id) = explode(':', $tuple, 2);
			
			if(false == ($context_ext = Extension_DevblocksContext::getByAlias($context, false))) {
				$error = sprintf("has a link with an invalid context (%s)", $tuple);
				return false;
			}
			
			$context = $context_ext->id;
			
			$tuple = sprintf("%s%s:%d",
				$is_remove ? '-' : '',
				$context,
				$id
			);
			
			$links[] = $tuple;
		}
		
		
		if(false == ($json = json_encode($links))) {
			$error = 'could not be JSON encoded.';
			return false;
		}
		
		$out_fields['_links'] = $json;
		
		return true;
	}
	
	/**
	 * @internal
	 */
	protected function _importModelCustomFieldsAsValues($model, $token_values) {
		@$custom_fields = $model->custom_fields;
		
		if($custom_fields) {
			$custom_values = $this->_lazyLoadCustomFields(
				'custom_',
				$token_values['_context'],
				$token_values['id'],
				$custom_fields
			);
			$token_values = array_merge($token_values, $custom_values);
		}

		return $token_values;
	}
	
	/**
	 * @internal
	 */
	protected function _lazyLoadLinks($context, $context_id) {
		$results = DAO_ContextLink::getAllContextLinks($context, $context_id);
		$token_values = [];
		$token_values['links'] = [];
		
		foreach($results as $result) {
			if(!isset($token_values['links'][$result->context]))
				$token_values['links'][$result->context] = [];
			
			$token_values['links'][$result->context][] = intval($result->context_id);
		}
		
		return $token_values;
	}

	// [TODO] This is setting the wrong type on all linked fields
	/**
	 * @internal
	 */
	protected function _lazyLoadCustomFields($token, $context, $context_id, $field_values=null) {
		$fields = DAO_CustomField::getByContext($context);
		$token_values = [];
		$token_values['custom'] = [];
		
		// If (0 == $context_id), we need to null out all the fields and return w/o queries
		if(empty($context_id))
			return $token_values;
			
		// If we weren't passed values
		if(is_null($field_values)) {
			$results = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(is_array($results))
				$field_values = array_shift($results);
			unset($results);
		}
		
		foreach(array_keys($fields) as $cf_id) {
			$token_values['custom'][$cf_id] = '';
			$token_values['custom_' . $cf_id] = '';
			
			if(isset($field_values[$cf_id])) {
				// The literal value
				$token_values['custom'][$cf_id] = $field_values[$cf_id];

				// Stringify
				if(is_array($field_values[$cf_id])) {
					$token_values['custom_'.$cf_id] = implode(', ', $field_values[$cf_id]);
				} elseif(is_string($field_values[$cf_id])) {
					$token_values['custom_'.$cf_id] = $field_values[$cf_id];
				}
			}

			switch($fields[$cf_id]->type) {
				case Model_CustomField::TYPE_CURRENCY:
					@$currency_id = intval($fields[$cf_id]->params['currency_id']);
					@$token_values['custom_' . $cf_id . '_currency__context'] = CerberusContexts::CONTEXT_CURRENCY;
					@$token_values['custom_' . $cf_id . '_currency_id'] = $currency_id;
					if(false != ($currency = DAO_Currency::get($currency_id))) {
						@$token_values['custom_' . $cf_id . '_label'] = $currency->format($field_values[$cf_id], true);
						@$token_values['custom_' . $cf_id . '_decimal'] = $currency->format($field_values[$cf_id], false);
					}
					break;
					
				case Model_CustomField::TYPE_DECIMAL:
					@$token_values['custom_' . $cf_id . '_decimal_at'] = intval(@$fields[$cf_id]->params['decimal_at']);
					break;
					
				case Model_CustomField::TYPE_LINK:
					@$token_values['custom_' . $cf_id . '_id'] = $field_values[$cf_id];
					@$token_values['custom_' . $cf_id . '__context'] = $fields[$cf_id]->params['context'];

					if(!isset($token_values[$token])) {
						$dict = new DevblocksDictionaryDelegate($token_values);
						$dict->$token;
						$token_values = $dict->getDictionary();
					}
					break;
					
				case Model_CustomField::TYPE_WORKER:
					@$token_values['custom_' . $cf_id . '_id'] = $field_values[$cf_id];
					@$token_values['custom_' . $cf_id . '__context'] = CerberusContexts::CONTEXT_WORKER;

					if(!isset($token_values[$token])) {
						$dict = new DevblocksDictionaryDelegate($token_values);
						$dict->$token;
						$token_values = $dict->getDictionary();
					}
					break;
					
				default:
					if(false != ($field_ext = $fields[$cf_id]->getTypeExtension())) {
						$value = $field_ext->getValue($field_values[$cf_id]);
						$token_values['custom'][$cf_id] = $value;
						$token_values['custom_' . $cf_id] = $value;
					}
					break;
			}
		}
		
		return $token_values;
	}

	/**
	 * @internal
	 */
	protected function _getTokenLabelsFromCustomFields($fields, $prefix) {
		$context_stack = CerberusContexts::getStack();

		$labels = [];
		$fieldsets = DAO_CustomFieldset::getAll();
		
		if(is_array($fields))
		foreach($fields as $cf_id => $field) {
			$fieldset = $field->custom_fieldset_id ? @$fieldsets[$field->custom_fieldset_id] : null;

			$suffix = '';

			switch($field->type) {
				case Model_CustomField::TYPE_LINK:
					if(!isset($field->params['context']))
						break;

					$field_prefix = $prefix . ($fieldset ? ($fieldset->name . ' ') : '') . $field->name . ' ';
					
					// Control infinite recursion
					if(count($context_stack) > 2 && $field->type == Model_CustomField::TYPE_LINK) {
						$labels['custom_'.$cf_id] = $field_prefix;
						
					} else {
						$merge_labels = $merge_values = [];
						CerberusContexts::getContext($field->params['context'], null, $merge_labels, $merge_values, $field_prefix, true);
	
						// Unset redundant id
						unset($merge_labels['id']);
	
						$labels['custom_'.$cf_id] = sprintf("%s%s",
							$field_prefix,
							'ID'
						);
						
						if(is_array($merge_labels))
						foreach($merge_labels as $label_key => $label) {
							$labels['custom_'.$cf_id.'_'.$label_key] = $label;
						}
					}
					break;
					
				default:
					$labels['custom_'.$cf_id] = sprintf("%s%s%s%s",
						$prefix,
						($fieldset ? ($fieldset->name . ':') : ''),
						$field->name,
						$suffix
					);
					break;
			}
		}
		
		return $labels;
	}

	/**
	 * @internal
	 */
	protected function _getTokenTypesFromCustomFields($fields, $prefix) {
		$context_stack = CerberusContexts::getStack();
		$types = [];
		
		if(is_array($fields))
		foreach($fields as $cf_id => $field) {

			$types['custom_'.$cf_id] = $field->type;
			
			switch($field->type) {
				case Model_CustomField::TYPE_LINK:
					if(!isset($field->params['context']))
						break;
						
					// Control infinite recursion
					if(count($context_stack) > 2 && $field->type == Model_CustomField::TYPE_LINK) {
						
					} else {
						$merge_labels = $merge_values = [];
						CerberusContexts::getContext($field->params['context'], null, $merge_labels, $merge_values, null, true, true);
						
						if(isset($merge_values['_types']) && is_array($merge_values['_types']))
						foreach($merge_values['_types'] as $type_key => $type) {
							$types['custom_'.$cf_id.'_'.$type_key] = $type;
						}
						
						$types['custom_'.$cf_id.'__label'] = 'context_url';
					}
					break;
					
				default:
					break;
			}
		}
		
		return $types;
	}

	/**
	 * @internal
	 */
	protected function _getImportCustomFields($fields, &$keys) {
		if(is_array($fields))
		foreach($fields as $token => $cfield) {
			if(!DevblocksPlatform::strStartsWith($token, 'cf_'))
				continue;

			$cfield_id = intval(substr($token, 3));

			$keys['cf_' . $cfield_id] = array(
				'label' => $cfield->db_label,
				'type' => $cfield->type,
				'param' => $cfield->token,
			);
		}

		return true;
	}
	
	/**
	 * @internal
	 */
	static function getTimelineComments($context, $context_id, $is_ascending=true) {
		$timeline = [];
		
		if(false != ($comments = DAO_Comment::getByContext($context, $context_id)))
			$timeline = array_merge($timeline, $comments);
		
		usort($timeline, function($a, $b) use ($is_ascending) {
			if($a instanceof Model_Comment) {
				$a_time = intval($a->created);
			} else {
				$a_time = 0;
			}
			
			if($b instanceof Model_Comment) {
				$b_time = intval($b->created);
			} else {
				$b_time = 0;
			}
			
			if($a_time > $b_time) {
				return ($is_ascending) ? 1 : -1;
			} else if ($a_time < $b_time) {
				return ($is_ascending) ? -1 : 1;
			} else {
				return 0;
			}
		});
		
		return $timeline;
	}
};

abstract class Extension_DevblocksEvent extends DevblocksExtension {
	const POINT = 'devblocks.event';

	private $_labels = [];
	private $_types = [];
	private $_values = [];
	
	private $_conditions_cache = [];
	private $_conditions_extensions_cache = [];

	/**
	 * @internal
	 */
	public static function getAll($as_instances=false) {
		$events = DevblocksPlatform::getExtensions('devblocks.event', $as_instances);
		
		if(
			class_exists('DAO_CustomRecord', true)
			&& false != ($custom_records = DAO_CustomRecord::getAll()) 
			&& is_array($custom_records)
			) {
			foreach($custom_records as $custom_record) {
				$context_id = sprintf('contexts.custom_record.%d', $custom_record->id);
				$event_id = sprintf('event.macro.custom_record.%d', $custom_record->id);
				$manifest = new DevblocksExtensionManifest();
				$manifest->id = $event_id;
				$manifest->plugin_id = 'cerberusweb.core';
				$manifest->point = Extension_DevblocksEvent::POINT;
				$manifest->name = 'Record custom behavior on ' . DevblocksPlatform::strUpperFirst($custom_record->name, true);
				$manifest->file = 'api/events/macro/abstract_custom_record_macro.php';
				$manifest->class = 'Event_AbstractCustomRecord_' . $custom_record->id;
				$manifest->params = [
					'macro_context' => $context_id,
					'contexts' => [
						0 => [
							'cerberusweb.contexts.app' => '',
							'cerberusweb.contexts.group' => '',
							'cerberusweb.contexts.role' => '',
							'cerberusweb.contexts.worker' => '',
						],
					],
					'menu_key' => 'Records:Custom Behavior:' . DevblocksPlatform::strUpperFirst($custom_record->name, true),
					'options' => [
						0 => [
							'visibility' => '',
						],
					]
				];
				
				if($as_instances) {
					$events[$event_id] = $manifest->createInstance();
				} else {
					$events[$event_id] = $manifest;
				}
			}
		}
		
		if($as_instances)
			DevblocksPlatform::sortObjects($events, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($events, 'name');
		return $events;
	}

	/**
	 * @internal
	 */
	public static function get($id, $as_instance=true) {
		$events = self::getAll(false);
		
		if(!isset($events[$id]))
			return null;
		
		$manifest = $events[$id]; /* @var $manifest DevblocksExtensionManifest */

		if($as_instance) {
			return $manifest->createInstance();
		} else {
			return $events[$id];
		}
		
		return null;
	}

	/**
	 * @internal
	 */
	public static function getByContext($context, $as_instances=false) {
		$events = self::getAll(false);

		foreach($events as $event_id => $event) {
			if(isset($event->params['contexts'][0])) {
				$contexts = $event->params['contexts'][0]; // keys
				if(!isset($contexts[$context]))
					unset($events[$event_id]);
			}
		}

		if($as_instances) {
			foreach($events as $event_id => $event)
				$events[$event_id] = $event->createInstance();
		}

		return $events;
	}
	
	/**
	 * @internal
	 */
	public static function getWithMacroContexts() {
		$macros = Extension_DevblocksEvent::getAll();
		
		$macros = array_filter($macros, function($event) {
			return array_key_exists('macro_context', $event->params);
		});
		
		return $macros;
	}

	/**
	 * @internal
	 */
	protected function _importLabelsTypesAsConditions($labels, $types) {
		$conditions = [];
		$custom_fields = DAO_CustomField::getAll();

		foreach($types as $token => $type) {
			if(!isset($labels[$token]))
				continue;

			// [TODO] This could be implemented
			if($type == 'context_url')
				continue;

			$label = $labels[$token];

			// Strip any modifiers
			if(false !== ($pos = strpos($token,'|')))
				$token = substr($token,0,$pos);

			$conditions[$token] = array('label' => $label, 'type' => $type);
		}
		
		foreach($labels as $token => $label) {
			if(false !== ($pos = strrpos($token, 'custom_'))) {
				$cfield_id = intval(substr($token, $pos + 7));

				if(null == ($cfield = @$custom_fields[$cfield_id]))
					continue;
				
				if(!isset($conditions[$token]))
					$conditions[$token] = array('label' => $label, 'type' => $cfield->type);

				// [TODO] Can we load these option a different way so this foreach isn't needed?
				switch($cfield->type) {
					case Model_CustomField::TYPE_DROPDOWN:
					case Model_CustomField::TYPE_MULTI_CHECKBOX:
						$conditions[$token]['options'] = @$cfield->params['options'];
						break;
				}
			}
		}

		return $conditions;
	}

	abstract function setEvent(Model_DevblocksEvent $event_model=null, Model_TriggerEvent $trigger=null);
	
	/**
	 *
	 * @param Model_TriggerEvent $trigger
	 * @return Model_DevblocksEvent
	 */
	function generateSampleEventModel(Model_TriggerEvent $trigger) {
		$actions = null;
		
		return new Model_DevblocksEvent(
			static::ID,
			[
				'key' => 'value',
				'actions' => &$actions,
			]
		);
	}

	/**
	 * @internal
	 */
	function setLabels($labels) {
		natcasesort($labels);
		$this->_labels = $labels;
	}

	/**
	 * @internal
	 */
	function setValues($values) {
		$this->_values = $values;

		if(isset($values['_types']))
			$this->_setTypes($values['_types']);
	}

	/**
	 * @internal
	 */
	function getValues() {
		return $this->_values;
	}

	/**
	 * @internal
	 */
	function getLabels(Model_TriggerEvent $trigger = null) {
		// Lazy load
		if(empty($this->_labels))
			$this->setEvent(null, $trigger);

		if(null != $trigger && !empty($trigger->variables)) {
			foreach($trigger->variables as $k => $var) {
				$this->_labels[$k] = '(variable) ' . $var['label'];
			}
		}

		// Sort
		asort($this->_labels);

		return $this->_labels;
	}

	/**
	 * @internal
	 */
	private function _setTypes($types) {
		$this->_types = $types;
	}

	/**
	 * @internal
	 */
	function getTypes() {
		if(!isset($this->_values['_types']))
			return [];

		return $this->_values['_types'];
	}

	/**
	 * @internal
	 */
	function getValuesContexts($trigger) {
		// Custom fields

		$cfields = [];
		$custom_fields = DAO_CustomField::getAll();
		$vars = [];

		// cfields
		$labels = $this->getLabels($trigger);

		if(is_array($labels))
		foreach($labels as $token => $label) {
			$matches = [];
			if(preg_match('#.*?_{0,1}custom_(\d+)$#', $token, $matches)) {
				@$cfield_id = $matches[1];

				if(empty($cfield_id))
					continue;

				if(!isset($custom_fields[$cfield_id]))
					continue;

				switch($custom_fields[$cfield_id]->type) {
					case Model_CustomField::TYPE_LINK:
						@$link_context = $custom_fields[$cfield_id]->params['context'];

						if(empty($link_context))
							break;

						$cfields[$token] = array(
							'label' => $label,
							'context' => $link_context,
						);

						// Include deep context links from this custom field link
						$link_labels = $link_values = [];
						CerberusContexts::getContext($link_context, null, $link_labels, $link_values, null, true);

						foreach($labels as $link_token => $link_label) {
							$link_matches = [];
							if(preg_match('#^'.$token.'_(.*?)__label$#', $link_token, $link_matches)) {
								@$link_key = $link_matches[1];

								if(empty($link_key))
									continue;

								if(isset($link_values[$link_key.'__context'])) {
									$cfields[$token . '_' . $link_key . '_id'] = array(
										'label' => $link_label,
										'context' => $link_values[$link_key.'__context'],
									);
								}
							}
						}

						break;

					case Model_CustomField::TYPE_WORKER:
						$cfields[$token] = array(
							'label' => $label,
							'context' => CerberusContexts::CONTEXT_WORKER,
						);
						break;

					default:
						continue 2;
				}
			}
		}

		// Behavior Vars
		$vars = DevblocksEventHelper::getVarValueToContextMap($trigger);

		return array_merge($cfields, $vars);
	}

	function renderEventParams(Model_TriggerEvent $trigger=null) {}

	/**
	 * @internal
	 */
	function getConditions($trigger, $sorted=true) {
		if(isset($this->_conditions_cache[$trigger->id])) {
			return $this->_conditions_cache[$trigger->id];
		}
		
		$conditions = array(
			'_calendar_availability' => array('label' => 'Calendar availability', 'type' => ''),
			'_custom_script' => array('label' => 'Custom script', 'type' => ''),
			'_day_of_week' => array('label' => 'Calendar day of week', 'type' => ''),
			'_day_of_month' => array('label' => 'Calendar day of month', 'type' => ''),
			'_month_of_year' => array('label' => 'Calendar month of year', 'type' => ''),
			'_time_of_day' => array('label' => 'Calendar time of day', 'type' => ''),
		);
		$custom = $this->getConditionExtensions($trigger);
		
		if(!empty($custom) && is_array($custom))
			$conditions = array_merge($conditions, $custom);
		
		// Trigger variables
		if(is_array($trigger->variables))
		foreach($trigger->variables as $key => $var) {
			$conditions[$key] = array(
				'label' => '(variable) ' . $var['label'],
				'type' => $var['type']
			);

			if($var['type'] == Model_CustomField::TYPE_DROPDOWN)
				@$conditions[$key]['options'] = DevblocksPlatform::parseCrlfString($var['params']['options']);
		}
		
		// Plugins
		// [TODO] This should filter by event type
		$manifests = Extension_DevblocksEventCondition::getAll(false);
		foreach($manifests as $manifest) {
			$conditions[$manifest->id] = array('label' => $manifest->params['label']);
		}
		
		if($sorted) {
			DevblocksPlatform::sortObjects($conditions, '[label]');
			$this->_conditions_cache[$trigger->id] = $conditions;
		}
		
		return $conditions;
	}

	/**
	 * @param array $event_params
	 * @param string $error
	 * @return boolean
	 */
	function prepareEventParams(Model_TriggerEvent $behavior=null, &$new_params, &$error) {
		$error = null;
		return true;
	}
	
	abstract function getConditionExtensions(Model_TriggerEvent $trigger);
	abstract function renderConditionExtension($token, $as_token, $trigger, $params=[], $seq=null);
	abstract function runConditionExtension($token, $as_token, $trigger, $params, DevblocksDictionaryDelegate $dict);

	/**
	 * @internal
	 */
	function renderCondition($token, $trigger, $params=[], $seq=null) {
		$conditions = $this->getConditions($trigger, false);
		$condition_extensions = $this->getConditionExtensions($trigger);

		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','condition'.$seq);

		switch($token) {
			case '_calendar_availability':
				// Get readable by VA
				$calendars = DAO_Calendar::getReadableByActor(array(CerberusContexts::CONTEXT_BOT, $trigger->bot_id));
				$tpl->assign('calendars', $calendars);

				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_calendar_availability.tpl');
				break;

			case '_custom_script':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_custom_script.tpl');
				break;

			case '_month_of_year':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_month_of_year.tpl');
				break;

			case '_day_of_month':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');
				break;
				
			case '_day_of_week':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_day_of_week.tpl');
				break;
				
			case '_time_of_day':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_time_of_day.tpl');
				break;

			default:
				if(null != (@$condition = $conditions[$token])) {
					// Automatic types
					switch(@$condition['type']) {
						case Model_CustomField::TYPE_CHECKBOX:
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_bool.tpl');
							break;
						case Model_CustomField::TYPE_DATE:
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_date.tpl');
							break;
						case Model_CustomField::TYPE_MULTI_LINE:
						case Model_CustomField::TYPE_SINGLE_LINE:
						case Model_CustomField::TYPE_URL:
						case 'phone':
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_string.tpl');
							break;
						case Model_CustomField::TYPE_LIST:
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_string_list.tpl');
							break;
						case Model_CustomField::TYPE_NUMBER:
						//case 'percent':
						case 'id':
						case 'size_bytes':
						case 'time_mins':
						case 'time_secs':
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');
							break;
						case Model_CustomField::TYPE_DROPDOWN:
						case Model_CustomField::TYPE_MULTI_CHECKBOX:
							$tpl->assign('condition', $condition);
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_dropdown.tpl');
							break;
						case Model_CustomField::TYPE_WORKER:
							$tpl->assign('workers', DAO_Worker::getAll());
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_worker.tpl');
							break;
						default:
							if(@substr($condition['type'],0,4) == 'ctx_') {
								return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');

							} else {
								// Custom
								if(isset($condition_extensions[$token])) {
									return $this->renderConditionExtension($token, $token, $trigger, $params, $seq);

								} else {
									// Plugins
									if(null != ($ext = DevblocksPlatform::getExtension($token, true))
										&& $ext instanceof Extension_DevblocksEventCondition) { /* @var $ext Extension_DevblocksEventCondition */
										return $ext->render($this, $trigger, $params, $seq);
									}
								}
							}

							break;
					}
				}
				break;
		}
	}

	/**
	 * @internal
	 */
	function runCondition($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$logger = DevblocksPlatform::services()->log('Bot');
		$conditions = $this->getConditions($trigger, false);
		
		// Cache the extensions
		if(!isset($this->_conditions_extensions_cache[$trigger->id])) {
			$this->_conditions_extensions_cache[$trigger->id] = $this->getConditionExtensions($trigger);
		}
		
		$extensions = @$this->_conditions_extensions_cache[$trigger->id] ?: [];
		
		$not = false;
		$pass = true;

		$now = time();

		// Overload the current time? (simulate)
		if(isset($dict->_current_time)) {
			$now = $dict->_current_time;
		}

		$logger->info('');
		$logger->info(sprintf("Checking condition `%s`...", $token));
		
		// Built-in conditions
		switch($token) {
			case '_calendar_availability':
				if(false == (@$calendar_id = $params['calendar_id']))
					return false;

				@$is_available = $params['is_available'];
				@$from = $params['from'];
				@$to = $params['to'];

				if(false == ($calendar = DAO_Calendar::get($calendar_id)))
					return false;

				@$cal_from = strtotime("today", strtotime($from));
				@$cal_to = strtotime("tomorrow", strtotime($to));

				$calendar_events = $calendar->getEvents($cal_from, $cal_to);
				$availability = $calendar->computeAvailability($cal_from, $cal_to, $calendar_events);

				$pass = ($is_available == $availability->isAvailableBetween(strtotime($from), strtotime($to)));
				break;

			case '_custom_script':
				@$tpl = DevblocksPlatform::importVar($params['tpl'],'string','');

				$tpl_builder = DevblocksPlatform::services()->templateBuilder();
				$value = $tpl_builder->build($tpl, $dict);

				if(false === $value) {
					$logger->error(sprintf("[Script] Syntax error:\n\n%s",
						implode("\n", $tpl_builder->getErrors())
					));
					return false;
				}

				$value = trim($value);

				@$not = (substr($params['oper'],0,1) == '!');
				@$oper = ltrim($params['oper'],'!');
				@$param_value = $params['value'];

				$logger->info(sprintf("Script: `%s` %s%s `%s`",
					$value,
					(!empty($not) ? 'not ' : ''),
					$oper,
					$param_value
				));

				switch($oper) {
					case 'is':
						$pass = (0==strcasecmp($value,$param_value));
						break;
					case 'like':
						$regexp = DevblocksPlatform::strToRegExp($param_value);
						$pass = @preg_match($regexp, $value);
						break;
					case 'contains':
						$pass = (false !== stripos($value, $param_value)) ? true : false;
						break;
					case 'regexp':
						$pass = @preg_match($param_value, $value);
						break;
				}
				break;

			case '_month_of_year':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				@$months = DevblocksPlatform::importVar($params['month'],'array',[]);

				switch($oper) {
					case 'is':
						$month = date('n', $now);
						$pass = in_array($month, $months);
						break;
				}
				break;
			case '_day_of_month':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				@$dom_expected = DevblocksPlatform::importVar($params['value'],'integer',0);
				$dom_actual = date('d', $now);

				switch($oper) {
					case 'is':
						$pass = $dom_expected == $dom_actual;
						break;
					case 'gt':
						$pass = $dom_actual > $dom_expected;
						break;
					case 'lt':
						$pass = $dom_actual < $dom_expected;
						break;
				}
				break;
			case '_day_of_week':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				@$days = DevblocksPlatform::importVar($params['day'],'array',[]);

				switch($oper) {
					case 'is':
						$today = date('N', $now);
						$pass = in_array($today, $days);
						break;
				}
				break;
			case '_time_of_day':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				@$from = DevblocksPlatform::importVar($params['from'],'string','now');
				@$to = DevblocksPlatform::importVar($params['to'],'string','now');

				switch($oper) {
					case 'between':
						@$from = strtotime($from, $now);
						@$to = strtotime($to, $now);
						if($to < $from)
							$to += 86400; // +1 day
						$pass = ($now >= $from && $now <= $to) ? true : false;
						break;
				}
				break;

			default:
				// Operators
				if(null != (@$condition = $conditions[$token])) {
					if(null == (@$value = $dict->$token)) {
						$value = '';
					}
					
					// Automatic types
					switch(@$condition['type']) {
						case Model_CustomField::TYPE_CHECKBOX:
							$bool = intval($params['bool']);
							$pass = !empty($value) == $bool;
							$logger->info(sprintf("Checkbox: %s = %s",
								(!empty($value) ? 'true' : 'false'),
								(!empty($bool) ? 'true' : 'false')
							));
							break;

						case Model_CustomField::TYPE_DATE:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							$oper = 'between';

							$from = strtotime($params['from']);
							$to = strtotime($params['to']);

							$logger->info(sprintf("Date: `%s` %s%s `%s` and `%s`",
								DevblocksPlatform::strPrettyTime($value),
								(!empty($not) ? 'not ' : ''),
								$oper,
								DevblocksPlatform::strPrettyTime($from),
								DevblocksPlatform::strPrettyTime($to)
							));

							switch($oper) {
								case 'between':
									if($to < $from)
										$to += 86400; // +1 day
									$pass = ($value >= $from && $value <= $to) ? true : false;
									break;
							}
							break;

						case Model_CustomField::TYPE_MULTI_LINE:
						case Model_CustomField::TYPE_SINGLE_LINE:
						case Model_CustomField::TYPE_URL:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							@$param_value = $params['value'];

							$logger->info(sprintf("Text: `%s` %s%s `%s`",
								$value,
								(!empty($not) ? 'not ' : ''),
								$oper,
								$param_value
							));

							switch($oper) {
								case 'is':
									$pass = (0==strcasecmp($value,$param_value));
									break;
								case 'like':
									$regexp = DevblocksPlatform::strToRegExp($param_value);
									$pass = @preg_match($regexp, $value);
									break;
								case 'contains':
									$pass = (false !== stripos($value, $param_value)) ? true : false;
									break;
								case 'regexp':
									$pass = @preg_match($param_value, $value);
									break;
							}
							break;
							
						case Model_CustomField::TYPE_LIST:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							
							$tpl_builder = DevblocksPlatform::services()->templateBuilder();
							@$param_value = $tpl_builder->build($params['value'], $dict);

							$logger->info(sprintf("Text: `%s` %s%s `%s`",
								$value,
								(!empty($not) ? 'not ' : ''),
								$oper,
								$param_value
							));
							
							$token_parts = explode('_', $token);
							$field_id = array_pop($token_parts);
							$token_cfields = implode('_', $token_parts);
							$contains = false;
							
							switch($oper) {
								case 'contains':
									if(!isset($dict->$token_cfields) 
										|| !is_array($dict->$token_cfields) 
										|| !isset($dict->$token_cfields[$field_id])) {
										$contains = false;
										break;
									}
									
									foreach($dict->$token_cfields[$field_id] as $value) {
										if(!$contains && 0 == strcasecmp($param_value, $value)) {
											$contains = true;
											break;
										}
									}
									
									$pass = $contains;
									break;
							}
							break;

						case Model_CustomField::TYPE_NUMBER:
						case 'id':
						case 'time_mins':
						case 'time_secs':
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							@$desired_value = intval($params['value']);

							$logger->info(sprintf("Number: %d %s%s %d",
								$value,
								(!empty($not) ? 'not ' : ''),
								$oper,
								$desired_value
							));

							switch($oper) {
								case 'is':
									$pass = intval($value)==$desired_value;
									break;
								case 'gt':
									$pass = intval($value) > $desired_value;
									break;
								case 'lt':
									$pass = intval($value) < $desired_value;
									break;
							}
							break;

						case Model_CustomField::TYPE_DROPDOWN:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							$desired_values = isset($params['values']) ? $params['values'] : [];

							$logger->info(sprintf("`%s` %s%s `%s`",
								$value,
								(!empty($not) ? 'not ' : ''),
								$oper,
								implode('; ', $desired_values)
							));

							if(!isset($desired_values) || !is_array($desired_values)) {
								$pass = false;
								break;
							}

							switch($oper) {
								case 'in':
									$pass = false;
									if(in_array($value, $desired_values)) {
										$pass = true;
									}
									break;
							}
							break;

						case Model_CustomField::TYPE_MULTI_CHECKBOX:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');

							$matches = [];
							if(preg_match("#(.*?_custom)_(\d+)#", $token, $matches) && 3 == count($matches)) {
								$value_token = $matches[1];
								$value_field = $dict->$value_token;
								@$value = $value_field[$matches[2]];
							}

							if(!is_array($value) || !isset($params['values']) || !is_array($params['values'])) {
								$pass = false;
								break;
							}

							$logger->info(sprintf("Multi-checkbox: `%s` %s%s `%s`",
								implode('; ', $params['values']),
								(!empty($not) ? 'not ' : ''),
								$oper,
								implode('; ', $value)
							));

							switch($oper) {
								// Is all of
								case 'is':
									$hits = array_intersect($value, $params['values']);
									$pass = (count($hits) == count($value));
									break;
								
								// Is any of
								case 'in':
									$hits = array_intersect($value, $params['values']);
									$pass = !empty($hits);
									break;
							}
							break;

						case Model_CustomField::TYPE_WORKER:
							@$worker_ids = $params['worker_id'];
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');

							if(!is_array($value))
								$value = empty($value) ? [] : array($value);

							if(is_null($worker_ids))
								$worker_ids = [];

							if(empty($worker_ids) && empty($value)) {
								$pass = true;
								break;
							}

							switch($oper) {
								case 'in':
									$pass = false;
									foreach($worker_ids as $v) {
										if(in_array($v, $value)) {
											$pass = true;
											break;
										}
									}
									break;
							}
							break;

						default:
							if(@substr($condition['type'],0,4) == 'ctx_') {
								$count = (isset($dict->$token) && is_array($dict->$token)) ? count($dict->$token) : 0;

								$not = (substr($params['oper'],0,1) == '!');
								$oper = ltrim($params['oper'],'!');
								@$desired_count = intval($params['value']);

								$logger->info(sprintf("Count: %d %s%s %d",
									$count,
									(!empty($not) ? 'not ' : ''),
									$oper,
									$desired_count
								));

								switch($oper) {
									case 'is':
										$pass = $count==$desired_count;
										break;
									case 'gt':
										$pass = $count > $desired_count;
										break;
									case 'lt':
										$pass = $count < $desired_count;
										break;
								}

							} else {
								if(isset($extensions[$token])) {
									$pass = $this->runConditionExtension($token, $token, $trigger, $params, $dict);
								} else {
									if(null != ($ext = DevblocksPlatform::getExtension($token, true))
										&& $ext instanceof Extension_DevblocksEventCondition) { /* @var $ext Extension_DevblocksEventCondition */
										$pass = $ext->run($token, $trigger, $params, $dict);
									}
								}
							}
							break;
					}
			} else {
				$logger->info("  ... FAIL (invalid condition)");
				return false;
			}
			break;
		}

		// Inverse operator?
		if($not)
			$pass = !$pass;

		$logger->info(sprintf("  ... %s", ($pass ? 'PASS' : 'FAIL')));

		return $pass;
	}

	/**
	 * @internal
	 */
	function getActions($trigger) { /* @var $trigger Model_TriggerEvent */
		$actions = [
			'_create_calendar_event' => [
				'label' => 'Create calendar event',
				'notes' => 'Use [Record create](/docs/bots/events/actions/core.bot.action.record.create/) instead.',
				'deprecated' => true,
				'params' => [
					'calendar_id' => [
						'type' => 'id',
						'required' => true,
						'notes' => 'The ID of the [calendar](/docs/records/types/calendar/) to add the event to',
					],
					'title' => [
						'type' => 'text',
						'required' => true,
						'notes' => 'The name of the event',
					],
					'when' => [
						'type' => 'timestamp',
						'required' => true,
						'notes' => 'The start datetime of the event',
					],
					'until' => [
						'type' => 'timestamp',
						'required' => true,
						'notes' => 'The end of datetime of the event',
					],
					'is_available' => [
						'type' => 'bit',
						'notes' => '`0`=busy, `1`=available',
					],
					'comment' => [
						'type' => 'text',
						'notes' => 'An optional comment to add to the new record',
					],
					'run_in_simulator' => [
						'type' => 'bit',
						'notes' => 'Create new records from the simulator: `0`=no, `1`=yes',
					],
					'object_var' => [
						'type' => 'text',
						'notes' => 'Save the new record into this `var_` behavior variable',
					],
				],
			],
			'_exit' => [
				'label' => 'Behavior exit',
				'params' => [
					'mode' => [
						'type' => 'text',
						'notes' => 'may be `suspend` on resumable behaviors, otherwise omit',
					],
				],
			],
			'_get_key' => [
				'label' => 'Get persistent key',
				'params' => [
					'key' => [
						'type' => 'string',
						'required' => true,
						'notes' => 'The key of the value to retrieve from storage',
					],
					'var' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'Save the returned value to this placeholder',
					],
				],
			],
			'_get_links' => [
				'label' => 'Get links',
				'params' => [
					'on' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'The placeholder/variable containing the target record',
					],
					'links_context' => [
						'type' => 'context',
						'required' => true,
						'notes' => 'Fetch links of [record type](/docs/records/types/)',
					],
					'var' => [
						'type' => 'placeholder',
						'notes' => 'Save the link results to this placeholder',
					],
					'behavior_var' => [
						'type' => 'placeholder',
						'notes' => 'Set this behavior variable with the link results',
					],
				],
			],
			'_get_worklist_metric' => [
				'label' => 'Get worklist metric',
				'deprecated' => true,
				'notes' => 'Use [Execute Data Query](/docs/bots/events/actions/core.bot.action.data_query/) instead.',
				'params' => [],
			],
			'_run_behavior' => [
				'label' => 'Behavior run',
				'params' => [
					'on' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'The placeholder/variable containing the target record',
					],
					'behavior_id' => [
						'type' => 'id',
						'required' => true,
						'notes' => 'The ID of the [behavior](/docs/records/types/behavior/) to execute',
					],
					'var_*' => [
						'type' => 'mixed',
						'notes' => 'Input variables for the target behavior',
					],
					'run_in_simulator' => [
						'type' => 'bit',
						'notes' => 'Run the target behavior in the simulator: `0`=no, `1`=yes',
					],
					'var' => [
						'type' => 'placeholder',
						'notes' => 'Save the behavior results to this placeholder',
					],
				],
			],
			'_run_subroutine' => [
				'label' => 'Behavior call subroutine',
				'params' => [
					'subroutine' => [
						'type' => 'text',
						'required' => true,
						'notes' => 'The name of the behavior [subroutine](/docs/bots/behaviors/#subroutines) to execute',
					],
				],
			],
			'_schedule_behavior' => [
				'label' => 'Behavior schedule',
				'params' => [
					'on' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'The placeholder/variable containing the target record',
					],
					'behavior_id' => [
						'type' => 'id',
						'required' => true,
						'notes' => 'The ID of the [behavior](/docs/records/types/behavior/) to execute',
					],
					'var_*' => [
						'type' => 'mixed',
						'notes' => 'Input variables for the target behavior',
					],
					'run_date' => [
						'type' => 'template',
						'notes' => 'When to run the scheduled behavior (e.g. `now`, `+2 days`, `Friday 8am`)',
					],
					'on_dupe' => [
						'type' => 'text',
						'notes' => '`first` (only schedule earliest), `last` (only schedule latest), or omit to allow multiple occurrences',
					],
				],
			],
			'_set_custom_var' => [
				'label' => 'Set custom placeholder',
				'params' => [
					'var' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'The placeholder to set',
					],
					'value' => [
						'type' => 'string',
						'required' => true,
						'notes' => 'The new value of the placeholder',
					],
					'format' => [
						'type' => 'string',
						'notes' => 'The format of the value: `json`, or omit for text',
					],
					'is_simulator_only' => [
						'type' => 'bit',
						'notes' => 'Only set the placeholder in simulator mode: `0`=no, `1`=yes',
					],
				],
			],
			'_set_custom_var_snippet' => [
				'label' => 'Set custom placeholder using a snippet',
				'params' => [
					'var' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'Save the snippet output to this placeholder',
					],
					'on' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'The placeholder/variable containing the target record',
					],
					'snippet_id' => [
						'type' => 'id',
						'required' => true,
						'notes' => 'The ID of the [snippet](/docs/records/types/snippet/) to use',
					],
				],
			],
			'_set_key' => [
				'label' => 'Set persistent key',
				'params' => [
					'key' => [
						'type' => 'string',
						'required' => true,
						'notes' => 'The key to set in storage',
					],
					'value' => [
						'type' => 'string',
						'required' => true,
						'notes' => 'The value to set in storage',
					],
					'expires_at' => [
						'type' => 'datetime',
						'notes' => 'When to expire the key (e.g. `now`, `+2 days`, `Friday 8am`); omit to never expire',
					],
				],
			],
			'_unschedule_behavior' => [
				'label' => 'Behavior unschedule',
				'params' => [
					'on' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'The placeholder/variable containing the target record',
					],
					'behavior_id' => [
						'type' => 'id',
						'required' => true,
						'notes' => 'The ID of the [behavior](/docs/records/types/behavior/) to remove',
					],
				],
			],
			'add_watchers' => [
				'label' =>'Add watchers',
				'params' => [
					'on' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'The placeholder/variable containing the target record',
					],
					'worker_id' => [
						'type' => 'id[]',
						'required' => true,
						'notes' => 'An array of [worker](/docs/records/types/worker/) IDs to add as watchers to the target record',
					],
				],
			],
			'create_comment' => [
				'label' => 'Create comment',
				'notes' => 'Use [Record create](/docs/bots/events/actions/core.bot.action.record.create/) instead.',
				'deprecated' => true,
				'params' => [
				],
			],
			'create_notification' => [
				'label' => 'Create notification',
				'notes' => 'Use [Record create](/docs/bots/events/actions/core.bot.action.record.create/) instead.',
				'deprecated' => true,
				'params' => [
				],
			],
			'create_task' => [
				'label' => 'Create task',
				'notes' => 'Use [Record create](/docs/bots/events/actions/core.bot.action.record.create/) instead.',
				'deprecated' => true,
				'params' => [
				],
			],
			'create_ticket' => [
				'label' => 'Create ticket',
				'notes' => 'Use [Record create](/docs/bots/events/actions/core.bot.action.record.create/) or [Execute email parser](/docs/bots/events/actions/core.bot.action.email_parser/) instead.',
				'deprecated' => true,
				'params' => [
				],
			],
			'send_email' => [
				'label' => 'Send email',
				'params' => [
					'from_address_id' => [
						'type' => 'id',
						'required' => true,
						'notes' => 'The sender [email address](/docs/records/types/address/) ID to as `From:`',
					],
					'send_as' => [
						'type' => 'text',
						'notes' => 'The personalized `From:` name',
					],
					'to' => [
						'type' => 'text',
						'required' => true,
						'notes' => 'A list of `To:` recipient email addresses delimited with commas',
					],
					'cc' => [
						'type' => 'text',
						'notes' => 'A list of `Cc:` recipient email addresses delimited with commas',
					],
					'bcc' => [
						'type' => 'text',
						'notes' => 'A list of `Bcc:` recipient email addresses delimited with commas',
					],
					'subject' => [
						'type' => 'text',
						'required' => true,
						'notes' => 'The `Subject:` of the email message',
					],
					'headers' => [
						'type' => 'text',
						'notes' => 'A list of `Header: Value` pairs delimited with newlines',
					],
					'format' => [
						'type' => 'text',
						'notes' => '`parsedown` for Markdown/HTML, or omitted for plaintext',
					],
					'content' => [
						'type' => 'text',
						'notes' => 'The email message body',
					],
					'html_template_id' => [
						'type' => 'id',
						'notes' => 'The [html template](/docs/records/types/html_template/) to use with Markdown format',
					],
					'bundle_ids' => [
						'type' => 'id[]',
						'notes' => 'An array of [file bundles](/docs/records/types/file_bundle/) to attach',
					],
					'run_in_simulator' => [
						'type' => 'bit',
						'notes' => 'Send live email in the simulator: `0`=no, `1`=yes',
					],
				],
			],
			'set_links' => [
				'label' => 'Set links',
				'params' => [
					'on' => [
						'type' => 'placeholder',
						'required' => true,
						'notes' => 'The placeholder/variable containing the target record',
					],
					'is_remove' => [
						'type' => 'bit',
						'notes' => '`0` (add links), `1` (remove links)',
					],
					'context_objects' => [
						'type' => 'array',
						'required' => true,
						'notes' => 'An array of `record_type:record_id` pairs to link to the target',
					],
				],
			],
		];
		
		$actions = array_map(
			function($action) {
				$action['scope'] = 'global';
				return $action;
			},
			$actions
		);
		
		$custom = $this->getActionExtensions($trigger);
		
		if(!empty($custom) && is_array($custom)) {
			$custom = array_map(function($action) {
				$action['scope'] = 'local';
				return $action;
			}, $custom);
			
			$actions = array_merge($actions, $custom);
		}

		// Trigger variables

		if(is_array($trigger->variables))
		foreach($trigger->variables as $key => $var) {
			$actions[$key] = [
				'label' => 'Set (variable) ' . $var['label'],
				'scope' => 'local',
			];
		}

		$bot = $trigger->getBot();

		// Add plugin extensions

		$manifests = Extension_DevblocksEventAction::getAll(false, $trigger->event_point);

		// Filter extensions by VA permissions

		$manifests = $bot->filterActionManifestsByAllowed($manifests);

		if(is_array($manifests))
		foreach($manifests as $manifest) {
			$action = [];
			
			if(method_exists($manifest->class, 'getMeta')) {
				$action = call_user_func([$manifest->class, 'getMeta']);
			}
			
			$action['label'] = $manifest->params['label'];
			$action['scope'] = 'global';
			
			$actions[$manifest->id] = $action;
		}

		// Sort by label

		DevblocksPlatform::sortObjects($actions, '[label]');

		return $actions;
	}
	
	abstract function getActionExtensions(Model_TriggerEvent $trigger);
	abstract function renderActionExtension($token, $trigger, $params=[], $seq=null);
	abstract function runActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict);
	protected function simulateActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {}
	function renderSimulatorTarget($trigger, $event_model) {}

	/**
	 * @internal
	 */
	function renderAction($token, $trigger, $params=[], $seq=null) {
		$actions = $this->getActionExtensions($trigger);

		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('trigger', $trigger);
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','action'.$seq);

		// Is this an event-provided action?
		if(null != (@$actions[$token])) {
			$this->renderActionExtension($token, $trigger, $params, $seq);

		// Nope, it's a global action
		} else {
			switch($token) {
				case '_create_calendar_event':
					DevblocksEventHelper::renderActionCreateCalendarEvent($trigger);
					break;
					
				case '_exit':
					return $tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_action_exit.tpl');
					break;

				case '_get_key':
					DevblocksEventHelper::renderActionGetKey($trigger);
					break;
					
				case '_get_links':
					DevblocksEventHelper::renderActionGetLinks($trigger);
					break;

				case '_get_worklist_metric':
					DevblocksEventHelper::renderActionGetWorklistMetric($trigger);
					break;

				case '_set_custom_var':
					$tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_custom_var.tpl');
					break;

				case '_set_custom_var_snippet':
					DevblocksEventHelper::renderActionSetPlaceholderUsingSnippet($trigger, $params);
					break;
					
				case '_set_key':
					DevblocksEventHelper::renderActionSetKey($trigger);
					break;

				case '_run_behavior':
					DevblocksEventHelper::renderActionRunBehavior($trigger);
					break;

				case '_schedule_behavior':
					$dates = [];
					$conditions = $this->getConditions($trigger, false);
					foreach($conditions as $key => $data) {
						if(isset($data['type']) && $data['type'] == Model_CustomField::TYPE_DATE)
							$dates[$key] = $data['label'];
					}
					$tpl->assign('dates', $dates);

					DevblocksEventHelper::renderActionScheduleBehavior($trigger);
					break;
					
				case '_run_subroutine':
					$subroutines = $trigger->getNodes('subroutine');
					$tpl->assign('subroutines', $subroutines);
					
					$tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_action_run_subroutine.tpl');
					break;
					
				case '_unschedule_behavior':
					DevblocksEventHelper::renderActionUnscheduleBehavior($trigger);
					break;
					
				case 'add_watchers':
					DevblocksEventHelper::renderActionAddWatchers($trigger);
					break;
				
				case 'create_comment':
					DevblocksEventHelper::renderActionCreateComment($trigger);
					break;
					
				case 'create_notification':
					DevblocksEventHelper::renderActionCreateNotification($trigger);
					break;
					
				case 'create_task':
					DevblocksEventHelper::renderActionCreateTask($trigger);
					break;
					
				case 'create_ticket':
					DevblocksEventHelper::renderActionCreateTicket($trigger);
					break;
					
				case 'send_email':
					$email_recipients = method_exists($this, 'getActionEmailRecipients') ? $this->getActionEmailRecipients() : null;
					DevblocksEventHelper::renderActionSendEmail($trigger, $email_recipients);
					break;

				case 'set_links':
					DevblocksEventHelper::renderActionSetLinks($trigger);
					break;
					
				default:
					// Variables
					if(DevblocksPlatform::strStartsWith($token, 'var_')) {
						@$var = $trigger->variables[$token];
						@$var_type = $var['type'];

						switch($var_type) {
							case Model_CustomField::TYPE_CHECKBOX:
								return $tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_bool.tpl');
								break;
							case Model_CustomField::TYPE_DATE:
								// Restricted to VA-readable calendars
								$calendars = DAO_Calendar::getReadableByActor(array(CerberusContexts::CONTEXT_BOT, $trigger->bot_id));
								$tpl->assign('calendars', $calendars);
								return $tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_date.tpl');
								break;
							case Model_CustomField::TYPE_NUMBER:
								return $tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_number.tpl');
								break;
							case Model_CustomField::TYPE_SINGLE_LINE:
								return DevblocksEventHelper::renderActionSetVariableString($this->getLabels($trigger));
								break;
							case Model_CustomField::TYPE_DROPDOWN:
								return DevblocksEventHelper::renderActionSetVariablePicklist($token, $trigger, $params);
								break;
							case Model_CustomField::TYPE_WORKER:
								return DevblocksEventHelper::renderActionSetVariableWorker($token, $trigger, $params);
								break;
							case 'contexts':
								return DevblocksEventHelper::renderActionSetListAbstractVariable($token, $trigger, $params);
								break;
							default:
								if(DevblocksPlatform::strStartsWith($var_type, 'ctx_')) {
									@$list_context = substr($var['type'],4);
									if(!empty($list_context))
										return DevblocksEventHelper::renderActionSetListVariable($token, $trigger, $params, $list_context);
								}
								return;
								break;
						}

					} else {
						// Plugins
						if(null != ($ext = DevblocksPlatform::getExtension($token, true))
							&& $ext instanceof Extension_DevblocksEventAction) { /* @var $ext Extension_DevblocksEventAction */
							$ext->render($this, $trigger, $params, $seq);
						}
					}
					break;
			}
		}
	}

	/**
	 * Are we doing a dry run?
	 * 
	 * @internal
	 */
	function simulateAction($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$actions = $this->getActionExtensions($trigger);
		
		if(null != (@$actions[$token])) {
			if(method_exists($this, 'simulateActionExtension'))
				return $this->simulateActionExtension($token, $trigger, $params, $dict);

		} else {
			switch($token) {
				case '_create_calendar_event':
					return DevblocksEventHelper::simulateActionCreateCalendarEvent($params, $dict);
					break;

				case '_exit':
					@$mode = (isset($params['mode']) && $params['mode'] == 'suspend') ? 'suspend' : 'stop';
					
					return sprintf(">>> %s the behavior\n",
						($mode == 'suspend' ? 'Suspending' : 'Exiting')
					);
					break;
				
				case '_get_key':
					return DevblocksEventHelper::simulateActionGetKey($params, $dict);
					break;
					
				case '_get_links':
					return DevblocksEventHelper::simulateActionGetLinks($params, $dict);
					break;
					
				case '_get_worklist_metric':
					return DevblocksEventHelper::simulateActionGetWorklistMetric($params, $dict);
					break;

				case '_set_custom_var':
					@$var = $params['var'];
					@$format = $params['format'];

					$value = ($format == 'json') ? @DevblocksPlatform::strFormatJson(json_encode($dict->$var, true)) : $dict->$var;
					
					return sprintf(">>> Setting custom placeholder {{%s}}:\n%s\n\n",
						$var,
						$value
					);
					break;

				case '_set_custom_var_snippet':
					@$var = $params['var'];

					$value = $dict->$var;

					return sprintf(">>> Setting custom placeholder {{%s}}:\n%s\n\n",
						$var,
						$value
					);
					break;
					
				case '_set_key':
					return DevblocksEventHelper::simulateActionSetKey($params, $dict);
					break;

				case '_run_behavior':
					return DevblocksEventHelper::simulateActionRunBehavior($params, $dict);
					break;

				case '_schedule_behavior':
					return DevblocksEventHelper::simulateActionScheduleBehavior($params, $dict);
					break;

				case '_run_subroutine':
					$subroutine_node = null;
					
					foreach($trigger->getNodes('subroutine') as $node) {
						if($node->title == $params['subroutine']) {
							$subroutine_node = $node;
							break;
						}
					}
					
					if(false == $subroutine_node)
						return;
					
					return sprintf(">>> Running subroutine: %s (#%d)\n",
						$subroutine_node->title,
						$subroutine_node->id
					);
					break;
					
				case '_unschedule_behavior':
					return DevblocksEventHelper::simulateActionUnscheduleBehavior($params, $dict);
					break;
					
				case 'add_watchers':
					$on_default = method_exists($this, 'getActionDefaultOn') ? $this->getActionDefaultOn() : null;
					return DevblocksEventHelper::simulateActionAddWatchers($params, $dict, $on_default);
					break;
					
				case 'create_comment':
					$on_default = method_exists($this, 'getActionDefaultOn') ? $this->getActionDefaultOn() : null;
					return DevblocksEventHelper::simulateActionCreateComment($params, $dict, $on_default);
					break;

				case 'create_notification':
					$on_default = method_exists($this, 'getActionDefaultOn') ? $this->getActionDefaultOn() : null;
					return DevblocksEventHelper::simulateActionCreateNotification($params, $dict, $on_default);
					break;

				case 'create_task':
					$on_default = method_exists($this, 'getActionDefaultOn') ? $this->getActionDefaultOn() : null;
					return DevblocksEventHelper::simulateActionCreateTask($params, $dict, $on_default);
					break;

				case 'create_ticket':
					return DevblocksEventHelper::simulateActionCreateTicket($params, $dict);
					break;

				case 'send_email':
					return DevblocksEventHelper::simulateActionSendEmail($params, $dict);
					break;

				case 'set_links':
					return DevblocksEventHelper::simulateActionSetLinks($trigger, $params, $dict);
					break;

				default:
					// Variables
					if(substr($token,0,4) == 'var_') {
						return DevblocksEventHelper::runActionSetVariable($token, $trigger, $params, $dict);

					} else {
						// Plugins
						if(null != ($ext = DevblocksPlatform::getExtension($token, true))
							&& $ext instanceof Extension_DevblocksEventAction) { /* @var $ext Extension_DevblocksEventAction */
							//return $ext->simulate($token, $trigger, $params, $dict);
						}
					}
					break;
			}
		}
	}

	/**
	 * @internal
	 */
	function runAction($token, $trigger, $params, DevblocksDictionaryDelegate $dict, $dry_run=false) {
		$actions = $this->getActionExtensions($trigger);

		$out = '';
		
		if(null != (@$actions[$token])) {
			// Is this a dry run?  If so, don't actually change anything
			if($dry_run) {
				$out = $this->simulateAction($token, $trigger, $params, $dict);
			} else {
				$this->runActionExtension($token, $trigger, $params, $dict);
			}

		} else {
			switch($token) {
				case '_create_calendar_event':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionCreateCalendarEvent($params, $dict);

					break;
					
				case '_exit':
					@$mode = (isset($params['mode']) && $params['mode'] == 'suspend') ? 'suspend' : 'stop';
					$dict->__exit = $mode;

					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					break;
					
				case '_get_key':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionGetKey($params, $dict);
					break;
					
				case '_get_links':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionGetLinks($params, $dict);
					break;
					
				case '_get_worklist_metric':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionGetWorklistMetric($params, $dict);
					break;
					
				case '_set_custom_var':
					$tpl_builder = DevblocksPlatform::services()->templateBuilder();

					@$var = $params['var'];
					@$value = $params['value'];
					@$format = $params['format'];
					@$is_simulator_only = $params['is_simulator_only'] ? true : false;

					// If this variable is only set in the simulator, and we're not simulating, abort
					if($is_simulator_only && !$dry_run)
						return;

					if(!empty($var) && !empty($value)) {
						$value = $tpl_builder->build($value, $dict);
						$dict->$var = ($format == 'json') ? @json_decode($value, true) : $value;
					}

					if($dry_run) {
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					} else {
						return;
					}
					break;

				case '_set_custom_var_snippet':
					$tpl_builder = DevblocksPlatform::services()->templateBuilder();
					$cache = DevblocksPlatform::services()->cache();

					@$on = $params['on'];
					@$snippet_id = $params['snippet_id'];
					@$var = $params['var'];
					@$placeholder_values = $params['placeholders'];

					if(empty($on) || empty($var) || empty($snippet_id))
						return;

					// Cache the snippet in the request (multiple runs of the VA; parser, etc)
					$cache_key = sprintf('snippet_%d', $snippet_id);
					if(false == ($snippet = $cache->load($cache_key, false, true))) {
						if(false == ($snippet = DAO_Snippet::get($snippet_id)))
							return;

						$cache->save($snippet, $cache_key, [], 0, true);
					}

					if(empty($var))
						return;

					$values_to_contexts = $this->getValuesContexts($trigger);

					@$on_context = $values_to_contexts[$on];

					if(empty($on) || !is_array($on_context))
						return;

					$snippet_labels = [];
					$snippet_values = [];

					// Load snippet target dictionary
					if(!empty($snippet->context) && $snippet->context == $on_context['context']) {
						CerberusContexts::getContext($on_context['context'], $dict->$on, $snippet_labels, $snippet_values, '', false, false);
					}

					// Prompted placeholders

					// [TODO] If a required prompted placeholder is missing, abort

					if(is_array($snippet->custom_placeholders) && is_array($placeholder_values))
					foreach($snippet->custom_placeholders as $placeholder_key => $placeholder) {
						if(!isset($placeholder_values[$placeholder_key])) {
							$snippet_values[$placeholder_key] = $placeholder['default'];

						} else {
							// Convert placeholders
							$snippet_values[$placeholder_key] = $tpl_builder->build($placeholder_values[$placeholder_key], $dict);
						}
					}

					$value = $tpl_builder->build($snippet->content, $snippet_values);
					$dict->$var = $value;

					if($dry_run) {
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					} else {
						return;
					}
					break;

				case '_set_key':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionSetKey($params, $dict);
					break;
					
				case '_run_behavior':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionRunBehavior($params, $dict);
					break;

				case '_run_subroutine':
					$subroutine_node = null;
					
					foreach($trigger->getNodes('subroutine') as $node) {
						if($node->title == $params['subroutine']) {
							$subroutine_node = $node;
							break;
						}
					}
					
					if(false == $subroutine_node)
						break;
					
					$dict->__goto = $subroutine_node->id;
					
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					break;
					
				case '_schedule_behavior':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionScheduleBehavior($params, $dict);
					break;

				case '_unschedule_behavior':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionUnscheduleBehavior($params, $dict);
					break;
					
				case 'add_watchers':
					$on_default = method_exists($this, 'getActionDefaultOn') ? $this->getActionDefaultOn() : null;
					if($dry_run)
						$out = DevblocksEventHelper::simulateActionAddWatchers($params, $dict, $on_default);
					else
						DevblocksEventHelper::runActionAddWatchers($params, $dict, $on_default);
					break;
				
				case 'create_comment':
					$on_default = method_exists($this, 'getActionDefaultOn') ? $this->getActionDefaultOn() : null;
					if($dry_run)
						$out = DevblocksEventHelper::simulateActionCreateComment($params, $dict, $on_default);
					else
						DevblocksEventHelper::runActionCreateComment($params, $dict, $on_default);
					break;
					
				case 'create_notification':
					$on_default = method_exists($this, 'getActionDefaultOn') ? $this->getActionDefaultOn() : null;
					if($dry_run)
						$out = DevblocksEventHelper::simulateActionCreateNotification($params, $dict, $on_default);
					else
						DevblocksEventHelper::runActionCreateNotification($params, $dict, $on_default);
					break;
					
				case 'create_task':
					$on_default = method_exists($this, 'getActionDefaultOn') ? $this->getActionDefaultOn() : null;
					if($dry_run)
						$out = DevblocksEventHelper::simulateActionCreateTask($params, $dict, $on_default);
					else
						DevblocksEventHelper::runActionCreateTask($params, $dict, $on_default);
					break;
					
				case 'create_ticket':
					if($dry_run)
						$out = DevblocksEventHelper::simulateActionCreateTicket($params, $dict);
					else
						DevblocksEventHelper::runActionCreateTicket($params, $dict);
					break;
					
				case 'send_email':
					if($dry_run)
						$out = DevblocksEventHelper::simulateActionSendEmail($params, $dict);
					else
						DevblocksEventHelper::runActionSendEmail($params, $dict);
					break;
			
				case 'set_links':
					if($dry_run)
						$out = DevblocksEventHelper::simulateActionSetLinks($trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionSetLinks($trigger, $params, $dict);
					break;
			
				default:
					// Variables
					if(substr($token,0,4) == 'var_') {
						// Always set the action vars, even in simulation.
						DevblocksEventHelper::runActionSetVariable($token, $trigger, $params, $dict);

						if($dry_run) {
							$out = DevblocksEventHelper::simulateActionSetVariable($token, $trigger, $params, $dict);
						} else {
							return;
						}

					} else {
						// Plugins
						if(null != ($ext = DevblocksPlatform::getExtension($token, true))
							&& $ext instanceof Extension_DevblocksEventAction) { /* @var $ext Extension_DevblocksEventAction */
							if($dry_run) {
								if(method_exists($ext, 'simulate'))
									$out = $ext->simulate($token, $trigger, $params, $dict);
							} else {
								return $ext->run($token, $trigger, $params, $dict);
							}
						}
					}
					break;
			}
		}
		
		// Append to simulator output
		if(!empty($out)) {
			/* @var $trigger Model_TriggerEvent */
			$all_actions = $this->getActions($trigger);
			$log = EventListener_Triggers::getNodeLog();
			
			if(!isset($dict->__simulator_output) || !is_array($dict->__simulator_output))
				$dict->__simulator_output = [];

			$node_id = array_pop($log);
			
			if(!empty($node_id) && false !== ($node = DAO_DecisionNode::get($node_id))) {
				if(array_key_exists($token, $all_actions)) {
					$output = array(
						'action' => $node->title,
						'title' => $all_actions[$token]['label'],
						'content' => $out,
					);
					
					$previous_output = $dict->__simulator_output;
					$previous_output[] = $output;
					$dict->__simulator_output = $previous_output;
					unset($out);
				}
			}
		}
	}
};

abstract class Extension_DevblocksEventCondition extends DevblocksExtension {
	/**
	 * @internal
	 */
	public static function getAll($as_instances=false, $for_event=null) {
		$extensions = DevblocksPlatform::getExtensions('devblocks.event.condition', false);
		$results = [];

		foreach($extensions as $ext_id => $ext) {
			// If the condition doesn't specify event filters, add to everything
			if(!isset($ext->params['events'][0])) {
				$results[$ext_id] = $as_instances ? $ext->createInstance() : $ext;

			} else {
				// Loop through the patterns
				foreach(array_keys($ext->params['events'][0]) as $evt_pattern) {
					$evt_pattern = DevblocksPlatform::strToRegExp($evt_pattern);

					if(preg_match($evt_pattern, $for_event))
						$results[$ext_id] = $as_instances ? $ext->createInstance() : $ext;
				}
			}
		}

		if($as_instances)
			DevblocksPlatform::sortObjects($results, 'manifest->params->[label]');
		else
			DevblocksPlatform::sortObjects($results, 'params->[label]');

		return $results;
	}

	abstract function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=[], $seq=null);
	abstract function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict);
};

abstract class Extension_DevblocksEventAction extends DevblocksExtension {
	const POINT = 'devblocks.event.action';
	
	/**
	 * @internal
	 */
	public static function getAll($as_instances=false, $for_event=null) {
		$extensions = DevblocksPlatform::getExtensions('devblocks.event.action', false);
		$results = [];

		foreach($extensions as $ext_id => $ext) {
			// If the action doesn't specify event filters, add to everything
			if(!isset($ext->params['events'][0])) {
				$results[$ext_id] = $as_instances ? $ext->createInstance() : $ext;

			} else {
				// Loop through the patterns
				foreach(array_keys($ext->params['events'][0]) as $evt_pattern) {
					$evt_pattern = DevblocksPlatform::strToRegExp($evt_pattern);

					if(preg_match($evt_pattern, $for_event))
						$results[$ext_id] = $as_instances ? $ext->createInstance() : $ext;
				}
			}
		}

		if($as_instances)
			DevblocksPlatform::sortObjects($results, 'manifest->params->[label]');
		else
			DevblocksPlatform::sortObjects($results, 'params->[label]');

		return $results;
	}

	/**
	 * Return information about the action (params, notes, etc)
	 */
	static function getMeta() { return []; }
	
	/**
	 * Render the behavior action's configuration template.
	 */
	abstract function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=[], $seq=null);
	
	/**
	 * Simulate the behavior action.
	 */
	function simulate($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {}
	
	/**
	 * Run the behavior action.
	 */
	abstract function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict);
}

abstract class DevblocksHttpResponseListenerExtension extends DevblocksExtension {
	const POINT = 'devblocks.listener.http';
	
	function run(DevblocksHttpResponse $request, Smarty $tpl) {
	}
};

abstract class Extension_DevblocksCacheEngine extends DevblocksExtension {
	const POINT ='devblocks.cache.engine';
	
	protected $_config = [];

	/**
	 * @internal
	 */
	public static function getAll($as_instances=false) {
		$engines = DevblocksPlatform::getExtensions('devblocks.cache.engine', $as_instances);
		if($as_instances)
			DevblocksPlatform::sortObjects($engines, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($engines, 'name');
		return $engines;
	}

	/**
	 * @internal
	 * 
	 * @param string $id
	 * @return Extension_DevblocksCacheEngine
	 */
	public static function get($id) {
		static $extensions = null;

		if(isset($extensions[$id]))
			return $extensions[$id];

		if(!isset($extensions[$id])) {
			if(null == ($ext = DevblocksPlatform::getExtension($id, true)))
				return;

			if(!($ext instanceof Extension_DevblocksCacheEngine))
				return;

			$extensions[$id] = $ext;
			return $ext;
		}
	}

	/**
	 * @internal
	 */
	function getConfig() {
		return $this->_config;
	}

	abstract function setConfig(array $config);
	abstract function testConfig(array $config);
	abstract function renderConfig();
	abstract function renderStatus();

	abstract function isVolatile();
	abstract function save($data, $key, $tags=[], $lifetime=0);
	abstract function load($key);
	abstract function remove($key);
	abstract function clean();
};

interface IDevblocksSearchEngine {
	public function setConfig(array $config);
	public function testConfig(array $config);
	public function renderConfigForSchema(Extension_DevblocksSearchSchema $schema);

	public function getQuickSearchExamples(Extension_DevblocksSearchSchema $schema);
	public function getIndexMeta(Extension_DevblocksSearchSchema $schema);

	public function query(Extension_DevblocksSearchSchema $schema, $query, array $attributes=[], $limit=250);
	public function index(Extension_DevblocksSearchSchema $schema, $id, array $doc, array $attributes=[]);
	public function delete(Extension_DevblocksSearchSchema $schema, $ids);
};

abstract class Extension_DevblocksSearchEngine extends DevblocksExtension implements IDevblocksSearchEngine {
	const POINT ='devblocks.search.engine';
	
	/**
	 * @internal
	 */
	public static function getAll($as_instances=false) {
		$engines = DevblocksPlatform::getExtensions('devblocks.search.engine', $as_instances);
		if($as_instances)
			DevblocksPlatform::sortObjects($engines, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($engines, 'name');
		return $engines;
	}

	/**
	 * @internal
	 * 
	 * @param string $id
	 * @return Extension_DevblocksSearchEngine
	 */
	public static function get($id) {
		static $extensions = null;

		if(isset($extensions[$id]))
			return $extensions[$id];

		if(!isset($extensions[$id])) {
			if(null == ($ext = DevblocksPlatform::getExtension($id, true)))
				return;

			if(!($ext instanceof Extension_DevblocksSearchEngine))
				return;

			$extensions[$id] = $ext;
			return $ext;
		}
	}
	
	/**
	 * @internal
	 */
	protected function escapeNamespace($namespace) {
		return DevblocksPlatform::strLower(DevblocksPlatform::strAlphaNum($namespace, '\_'));
	}

	/**
	 * @internal
	 */
	public function _getTextFromDoc(array $doc) {
		$output = [];

		// Find all text content and append it together
		array_walk_recursive($doc, function($e) use (&$output) {
			if(is_string($e))
				$output[] = $e;
		});

		return implode(' ', $output);
	}

	/**
	 * @internal
	 */
	public function getQueryFromParam($param) {
		$values = [];

		if(!is_array($param->value) && !is_string($param->value))
			return false;
		
		if(!is_array($param->value) && preg_match('#^\[.*\]$#', $param->value)) {
			$values = json_decode($param->value, true);
			
		} elseif(is_array($param->value)) {
			$values = $param->value;
			
		} else {
			$values = $param->value;
			
		}
		
		if(!is_array($values)) {
			$value = $values;
			
		} else {
			$value = $values[0];
		}
		
		return $value;
	}
	
	/**
	 * @internal
	 */
	public function truncateOnWhitespace($content, $length) {
		$start = 0;
		$len = mb_strlen($content);
		$end = $start + $length;
		$next_ws = $end;

		// If our offset is past EOS, use the last pos
		if($end > $len) {
			$next_ws = $len;

		} else {
			if(false === ($next_ws = mb_strpos($content, ' ', $end)))
				if(false === ($next_ws = mb_strpos($content, "\n", $end)))
					$next_ws = $end;
		}

		return mb_substr($content, $start, $next_ws-$start);
	}
};

abstract class Extension_DevblocksSearchSchema extends DevblocksExtension {
	const POINT = 'devblocks.search.schema';
	
	const INDEX_POINTER_RESET = 'reset';
	const INDEX_POINTER_CURRENT = 'current';

	/**
	 * @internal
	 */
	public static function getAll($as_instances=false) {
		$schemas = DevblocksPlatform::getExtensions('devblocks.search.schema', $as_instances);
		if($as_instances)
			DevblocksPlatform::sortObjects($schemas, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($schemas, 'name');
		return $schemas;
	}

	/**
	 * @internal
	 * 
	 * @param string $id
	 * @return Extension_DevblocksSearchSchema
	 */
	public static function get($id) {
		static $extensions = null;

		if(isset($extensions[$id]))
			return $extensions[$id];

		if(!isset($extensions[$id])) {
			if(null == ($ext = DevblocksPlatform::getExtension($id, true)))
				return;

			if(!($ext instanceof Extension_DevblocksSearchSchema))
				return;

			$extensions[$id] = $ext;
			return $ext;
		}
	}

	/**
	 * @internal
	 */
	public function getEngineParams() {
		if(false == ($engine_json = $this->getParam('engine_params_json', false))) {
			$engine_json = '{"engine_extension_id":"devblocks.search.engine.mysql_fulltext", "config":{}}';
		}

		if(false == ($engine_properties = json_decode($engine_json, true))) {
			return false;
		}

		return $engine_properties;
	}

	/**
	 * @internal
	 * 
	 * @return Extension_DevblocksSearchEngine
	 */
	public function getEngine() {
		$engine_params = $this->getEngineParams();
		
		if(false == ($_engine = Extension_DevblocksSearchEngine::get($engine_params['engine_extension_id'], true)))
			return false;

		if(isset($engine_params['config']))
			$_engine->setConfig($engine_params['config']);

		return $_engine;
	}

	/**
	 * @internal
	 */
	public function saveConfig(array $params) {
		if(!is_array($params))
			$params = [];

		// Detect if the engine changed
		$previous_engine_params = $this->getEngineParams();
		$reindex = (@$previous_engine_params['engine_extension_id'] != @$params['engine_extension_id']);

		// Save new new engine params
		$this->setParam('engine_params_json', json_encode($params));

		// If our engine changed
		if($reindex)
			$this->reindex();
	}

	/**
	 * @internal
	 */
	public function getQueryFromParam($param) {
		if(false !== ($engine = $this->getEngine()))
			return $engine->getQueryFromParam($param);

		return null;
	}

	/**
	 * @internal
	 */
	public function getIndexMeta() {
		$engine = $this->getEngine();
		return $engine->getIndexMeta($this);
	}
	
	abstract function getNamespace();
	abstract function getAttributes();
	//abstract function getFields();
	abstract function query($query, $attributes=[], $limit=1000);
	abstract function index($stop_time=null);
	abstract function reindex();
	abstract function delete($ids);
};

abstract class Extension_DevblocksStorageEngine extends DevblocksExtension {
	const POINT = 'devblocks.storage.engine';
	
	protected $_options = [];
	
	/**
	 * @internal
	 */
	public static function getAll($as_instances=false) {
		$extensions = DevblocksPlatform::getExtensions(self::POINT, false);

		if($as_instances)
			DevblocksPlatform::sortObjects($extensions, 'manifest->params->[label]');
		else
			DevblocksPlatform::sortObjects($extensions, 'params->[label]');

		return $extensions;
	}

	abstract function renderConfig(Model_DevblocksStorageProfile $profile);
	abstract function saveConfig(Model_DevblocksStorageProfile $profile);
	abstract function testConfig(Model_DevblocksStorageProfile $profile);

	abstract function exists($namespace, $key);
	abstract function put($namespace, $id, $data);
	abstract function get($namespace, $key, &$fp=null);
	abstract function delete($namespace, $key);
	
	function batchDelete($namespace, $keys) { /* override */ 
		if(is_array($keys))
		foreach($keys as $key)
			$this->delete($namespace, $key);
	}

	/**
	 * @internal
	 */
	public function setOptions($options=[]) {
		if(is_array($options))
			$this->_options = $options;
	}

	/**
	 * @internal
	 */
	protected function escapeNamespace($namespace) {
		return DevblocksPlatform::strLower(DevblocksPlatform::strAlphaNum($namespace, '\_'));
	}
};

abstract class Extension_DevblocksStorageSchema extends DevblocksExtension {
	const POINT = 'devblocks.storage.schema';
	
	abstract function render();
	abstract function renderConfig();
	abstract function saveConfig();

	public static function getActiveStorageProfile() {}

	public static function get($object, &$fp=null) {}
	public static function put($id, $contents, $profile=null) {}
	public static function delete($ids) {}
	public static function archive($stop_time=null) {}
	public static function unarchive($stop_time=null) {}

	/**
	 * @internal
	 */
	protected function _stats($table_name) {
		$db = DevblocksPlatform::services()->database();

		$stats = [];

		$results = $db->GetArraySlave(sprintf("SELECT storage_extension, storage_profile_id, count(id) as hits, sum(storage_size) as bytes FROM %s GROUP BY storage_extension, storage_profile_id ORDER BY storage_extension",
			$table_name
		));
		foreach($results as $result) {
			$stats[$result['storage_extension'].':'.intval($result['storage_profile_id'])] = array(
				'storage_extension' => $result['storage_extension'],
				'storage_profile_id' => $result['storage_profile_id'],
				'count' => intval($result['hits']),
				'bytes' => intval($result['bytes']),
			);
		}

		return $stats;
	}
};

abstract class DevblocksControllerExtension extends DevblocksExtension implements DevblocksHttpRequestHandler {
	const POINT = 'devblocks.controller';
	
	public function handleRequest(DevblocksHttpRequest $request) {}
	public function writeResponse(DevblocksHttpResponse $response) {}
	
	public function redirectRequestToLogin(DevblocksHttpIO $request) {
		$query = [];
		if(!empty($request->path)) {
			if(is_array($request->path) && !empty($request->path))
				$query = ['url'=> implode('/',$request->path)];
		}
		DevblocksPlatform::redirect(new DevblocksHttpRequest(['login'], $query));
	}
};

abstract class DevblocksEventListenerExtension extends DevblocksExtension {
	const POINT = 'devblocks.listener.event';
	
	/**
	 * @param Model_DevblocksEvent $event
	 */
	function handleEvent(Model_DevblocksEvent $event) {}
};

interface DevblocksHttpRequestHandler {
	/**
	 * @param DevblocksHttpRequest
	 * @return DevblocksHttpResponse
	 */
	public function handleRequest(DevblocksHttpRequest $request);
	public function writeResponse(DevblocksHttpResponse $response);
};

class DevblocksHttpRequest extends DevblocksHttpIO {
	public $method = null;
	public $csrf_token = null;
	
	/**
	 * @param array $path
	 */
	function __construct($path=[], $query=[], $method=null) {
		parent::__construct($path, $query);
		$this->method = $method;
	}
};

class DevblocksHttpResponse extends DevblocksHttpIO {
	/**
	 * @param array $path
	 */
	function __construct($path=[], $query=[]) {
		parent::__construct($path, $query);
	}
};

abstract class DevblocksHttpIO {
	public $path = [];
	public $query = [];

	/**
	 *
	 * @param array $path
	 */
	function __construct($path,$query=[]) {
		$this->path = $path;
		$this->query = $query;
	}
};

class _DevblocksSortHelper {
	private static $_sortOn = '';

	static function sortByNestedMember($a, $b) {
		$props = explode('->', self::$_sortOn);

		$a_test = $a;
		$b_test = $b;

		foreach($props as $prop) {
			$is_index = false;

			if('[' == $prop[0]) {
				$is_index = true;
				$prop = trim($prop,'[]');
			}

			if($is_index) {
				if(!isset($a_test[$prop]) && !isset($b_test[$prop]))
					return 0;

				@$a_test = $a_test[$prop];
				@$b_test = $b_test[$prop];

			} else {
				if(!isset($a_test->$prop) && !isset($b_test->$prop)) {
					return 0;
				}

				@$a_test = $a_test->$prop;
				@$b_test = $b_test->$prop;
			}
		}

		if(is_numeric($a_test) && is_numeric($b_test)) {
			settype($a_test, 'float');
			settype($b_test, 'float');
			
			if($a_test==$b_test)
				return 0;

			return ($a_test > $b_test) ? 1 : -1;

		} else {
			$a_test = is_null($a_test) ? '' : $a_test;
			$b_test = is_null($b_test) ? '' : $b_test;

			if(!is_string($a_test) || !is_string($b_test))
				return 0;
			
			$result = strcasecmp($a_test, $b_test);

			return $result;
		}
	}

	static function sortObjects(&$array, $on, $ascending=true) {
		self::$_sortOn = $on;

		uasort($array, array('_DevblocksSortHelper', 'sortByNestedMember'));

		if(!$ascending)
			$array = array_reverse($array, true);
	}
};
