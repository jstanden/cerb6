<?php
$db = DevblocksPlatform::services()->database();
$logger = DevblocksPlatform::services()->log();
$tables = $db->metaTables();

// ===========================================================================
// Add POP3 fail count

if(!isset($tables['pop3_account'])) {
	$logger->error("The 'pop3_account' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('pop3_account');

if(!isset($columns['num_fails'])) {
	$db->ExecuteMaster("ALTER TABLE pop3_account ADD COLUMN num_fails TINYINT NOT NULL DEFAULT 0");
}

// ===========================================================================
// Clean ACL

$db->ExecuteMaster(sprintf("DELETE FROM worker_role_acl WHERE priv_id = %s",
	$db->qstr('core.mail.actions.auto_refresh')
));

return TRUE;