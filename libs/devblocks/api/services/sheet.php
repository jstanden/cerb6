<?php
class _DevblocksSheetService {
	private static $_instance = null;
	
	private $_types = [];
	private $_default_type = null;
	private $_type_funcs = null;
	
	static function getInstance() {
		if(is_null(self::$_instance))
			self::$_instance = new _DevblocksSheetService();
		
		return self::$_instance;
	}
	
	function newInstance() {
		return new _DevblocksSheetService();
	}
	
	private function __construct() {
		$this->_type_funcs = new _DevblocksSheetServiceTypes();
	}
	
	function parseYaml($yaml, &$error=null) {
		$sheet = DevblocksPlatform::services()->string()->yamlParse($yaml, 0);
		return $sheet;
	}
	
	function setDefaultType($type) {
		$this->_default_type = $type;
	}
	
	function addType($name, callable $func) {
		$this->_types[$name] = $func;
	}
	
	function types() {
		return $this->_type_funcs;
	}
	
	function getRows(array $sheet, array $sheet_dicts) {
		// Sanitize
		$columns = $sheet['columns'];
		
		$rows = [];
		
		foreach($sheet_dicts as $sheet_dict_id => $sheet_dict) {
			$row = [];
			
			foreach($columns as $column) {
				if(false == (@$column_key = $column['key']))
					continue;
				
				if(false == ($column_type = @$column['type']))
					$column_type = $this->_default_type;
				
				if(!array_key_exists($column_type, $this->_types))
					continue;
				
				$row[$column_key] = $this->_types[$column_type]($column, $sheet_dict);
			}
			
			$rows[$sheet_dict_id] = $row;
		}
		
		return $rows;
	}
}

class _DevblocksSheetServiceTypes {
	function card() {
		return function($column, $sheet_dict) {
			$url_writer = DevblocksPlatform::services()->url();
			
			@$card_label = $column['params']['label'];
			@$card_context = $column['params']['context'];
			@$card_id = $column['params']['id'];
			$value = '';
			
			if(!$card_label) {
				$card_label_key = @$column['params']['label_key'] ?: '_label';
				$card_label = $sheet_dict->get($card_label_key);
			}
			
			if(!$card_context) {
				$card_context_key = @$column['params']['context_key'] ?: '_context';
				$card_context = $sheet_dict->get($card_context_key);
			}
			
			if(!$card_id) {
				$card_id_key = @$column['params']['id_key'] ?: 'id';
				$card_id = $sheet_dict->get($card_id_key);
			}
			
			if($card_id) {
				// Avatar image?
				if(array_key_exists('params', $column) && array_key_exists('image', $column['params'])) {
					$avatar_size = @$column['params']['image']['size'] ?: "1.5em";
					
					$value .= sprintf('<img src="%s?v=%s" style="{$avatar_size};width:%s;border-radius:%s;margin-right:0.25em;vertical-align:middle;">',
						$url_writer->write(sprintf("c=avatars&ctx=%s&id=%d",
							DevblocksPlatform::strEscapeHtml($card_context),
							$card_id
						)),
						DevblocksPlatform::strEscapeHtml($sheet_dict->get('updated_at', $sheet_dict->get('updated'))),
						DevblocksPlatform::strEscapeHtml($avatar_size),
						DevblocksPlatform::strEscapeHtml($avatar_size)
					);
				}
				
				// Card link
				$value .= sprintf('<span class="cerb-peek-trigger" data-context="%s" data-context-id="%d" style="text-decoration:underline;cursor:pointer;">%s</span>',
					DevblocksPlatform::strEscapeHtml($card_context),
					$card_id,
					DevblocksPlatform::strEscapeHtml($card_label)
				);
				
			} else {
				$value = $card_label;
			}
			
			return $value;
		};
	}
	
	function custom() {
		return function($column, $sheet_dict) {
			$tpl_builder = DevblocksPlatform::services()->templateBuilder();
			@$template = $column['params']['template'];
			
			$value = $tpl_builder->build($template, $sheet_dict);
			
			return DevblocksPlatform::purifyHTML($value, false, true);
		};
	}
	
	function date() {
		return function($column, $sheet_dict) {
			$value = $sheet_dict->get($column['key']);
			$value = DevblocksPlatform::strPrettyTime($value);
			
			return DevblocksPlatform::strEscapeHtml($value);
		};
	}
	
	function link() {
		return function($column, $sheet_dict) {
			$tpl_builder = DevblocksPlatform::services()->templateBuilder();
			
			@$href_template = $column['params']['href'];
			@$text_template = $column['params']['text'];
			
			$href = $tpl_builder->build($href_template, $sheet_dict);
			$value = '';
			
			if($text_template) {
				$text = $tpl_builder->build($text_template, $sheet_dict);
			} else {
				$text = $sheet_dict->get($column['key']);
			}
			
			$url = '';
			
			if(DevblocksPlatform::strStartsWith($href, ['http:','https:'])) {
				$url = $href;
			} else if(DevblocksPlatform::strStartsWith($href, '/')) {
				$href = trim($href, '/\\');
				$url = DevblocksPlatform::services()->url()->write(explode('/', $href), true);
			}
			
			if($url) {
				$value = sprintf('<a href="%s">%s</a>',
					$url,
					DevblocksPlatform::strEscapeHtml($text)
				);
			}
			
			return $value;
		};
	}
	
	function searchButton() {
		return function($column, $sheet_dict) {
			$tpl_builder = DevblocksPlatform::services()->templateBuilder();
			
			@$context_alias = $column['params']['context'] ?: $sheet_dict->get($column['params']['context_key']);
			@$query = $column['params']['query'];
			
			if(false == ($query = $tpl_builder->build($query, $sheet_dict)))
				return;
			
			if(!$context_alias || false == ($context_ext = Extension_DevblocksContext::getByAlias($context_alias, true)))
				return;
			
			return sprintf('<button type="button" class="cerb-search-trigger" data-context="%s" data-query="%s"><span class="glyphicons glyphicons-search"></span></button>',
				DevblocksPlatform::strEscapeHtml($context_ext->id),
				DevblocksPlatform::strEscapeHtml($query)
			);
		};
	}
	
	function slider() {
		return function($column, $sheet_dict) {
			$value_min = @$column['params']['min'] ?: 0;
			$value_max = @$column['params']['max'] ?: 100;
			$value_mid = ($value_max + $value_min)/2;
			
			if(array_key_exists('value', $column['params'])) {
				$value = $column['params']['value'];
				
			} else if(array_key_exists('value_key', $column['params'])) {
				$value = $sheet_dict->get($column['params']['value_key']);
				
			} else {
				$value = $sheet_dict->get($column['key']);
			}
			
			if($value > $value_mid) {
				$color = 'rgb(230,70,70)';
			} else if($value < $value_mid) {
				$color = 'rgb(0,200,0)';
			} else {
				$color = 'rgb(175,175,175)';
			}
			
			return sprintf(
				'<div title="%d" style="width:60px;height:8px;background-color:rgb(220,220,220);border-radius:8px;text-align:center;">'.
					'<div style="position:relative;margin-left:5px;width:50px;height:8px;">'.
						'<div style="position:absolute;margin-left:-5px;top:-1px;left:%d%%;width:10px;height:10px;border-radius:10px;background-color:%s;"></div>'.
					'</div>'.
				'</div>',
				$value,
				($value/$value_max)*100,
				DevblocksPlatform::strEscapeHtml($color)
			);
			
			return $value;
		};
	}
	
	}
}