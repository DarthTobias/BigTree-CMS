<?php
	namespace BigTree;
	
	$clean_referer = str_replace(array("http://","https://"),"//",$_SERVER["HTTP_REFERER"]);
	$clean_admin_root = str_replace(array("http://","https://"),"//",ADMIN_ROOT)."users/add/";

	if ($clean_referer != $clean_admin_root) {
?>
<div class="container">
	<section>
		<p><?=Text::translate('To create a user, please access the <a href=":link:">Add User</a> page.', false, array(":link:" => ADMIN_ROOT."users/add/"))?></p>
	</section>
</div>
<?php
	} else {
		Globalize::POST();

		// Check security policy
		if (!$admin->validatePassword($password)) {
			$_SESSION["bigtree_admin"]["create_user"] = $_POST;
			$_SESSION["bigtree_admin"]["create_user"]["error"] = "password";
			Utils::growl("Users","Invalid Password","error");
			Router::redirect(ADMIN_ROOT."users/add/");
		}

		// Don't let them exceed permission level
		if (Auth::user()->Level < intval($level)) {
			$level = Auth::user()->Level;
		}

		$user = User::create($email,$password,$name,$company,$level,$permissions,$alerts,$daily_digest);
			
		if ($user === false) {
			$_SESSION["bigtree_admin"]["create_user"] = $_POST;
			$_SESSION["bigtree_admin"]["create_user"]["error"] = "email";
			Utils::growl("Users","Creation Failed","error");
			Router::redirect(ADMIN_ROOT."users/add/");
		}
	
		Utils::growl("Users","Added User");
		Router::redirect(ADMIN_ROOT."users/edit/".$user->ID."/");
	}
?>