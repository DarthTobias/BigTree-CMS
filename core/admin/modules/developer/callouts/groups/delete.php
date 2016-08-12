<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	$group = new CalloutGroup(end($bigtree["path"]));
	$group->delete();

	Utils::growl("Developer","Deleted Callout Group");
	
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	