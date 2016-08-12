<?php
	namespace BigTree;
	
	// Backwards compatibility
	function sqlquery($query) {
		return SQL::query($query);
	}

	function sqlfetch(SQL $query) {
		return $query->fetch();
	}

	function sqlrows(SQL $result) {
		return $result->rows();
	}

	function sqlid() {
		return SQL::insertID();
	}

	function sqlescape($string) {
		return SQL::escape($string);
	}