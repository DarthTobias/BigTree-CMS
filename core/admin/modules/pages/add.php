<?php
	namespace BigTree;
	
	$tags = array();
	$bigtree["form_action"] = "create";
	$bigtree["current_page"] = array("id" => $bigtree["current_page"]["id"]);
	// Reset the $page variable to take out the information from the parent page.
	$page = array("id" => $page["id"]);
	Router::includeFile("admin/modules/pages/_form.php");