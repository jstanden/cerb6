<?php
$db = DevblocksPlatform::services()->database();
$tables = $db->metaTables();

// Convert tables to ID = INT4 UNSIGNED AUTO_INCREMENT
$tables_autoinc = array(
	'devblocks_storage_profile',
	'devblocks_template',
	'translation',
);
foreach($tables_autoinc as $table) {
	if(!isset($tables[$table]))
		return FALSE;
	
	list($columns, $indexes) = $db->metaTable($table);
	if(isset($columns['id']) 
		&& ('int(10) unsigned' != $columns['id']['type'] 
		|| 'auto_increment' != $columns['id']['extra'])
	) {
		$db->ExecuteMaster(sprintf("ALTER TABLE %s MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT", $table));
	}
}

return TRUE;