<?php
	/*
		Class: BigTree\ResourceFolder
			Provides an interface for handling BigTree resource folders.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read array $Breadcrumb
	 * @property-read array $Contents
	 * @property-read int $ID
	 * @property-read array $Statistics
	 * @property-read string $UserAccessLevel
	 */
	
	class ResourceFolder extends BaseObject {
		
		public static $Table = "bigtree_resource_folders";
		
		protected $ID;
		
		public $Name;
		public $Parent;
		
		/*
			Constructor:
				Builds a ResourceFolder object referencing an existing database entry.

			Parameters:
				folder - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		function __construct($folder = null) {
			if ($folder !== null) {
				// Passing in just an ID
				if (!is_array($folder)) {
					$folder = SQL::fetch("SELECT * FROM bigtree_resource_folders WHERE id = ?", $folder);
				}
				
				// Bad data set
				if (!is_array($folder)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $folder["id"];
					
					$this->Name = $folder["name"];
					$this->Parent = $folder["parent"];
				}
			}
		}
		
		/*
			Function: create
				Creates a resource folder.

			Parameters:
				parent - The parent folder.
				name - The name of the new folder.

			Returns:
				A ResourceFolder object.
		*/
		
		static function create($parent, $name) {
			$id = SQL::insert("bigtree_resource_folders", array(
				"name" => Text::htmlEncode($name),
				"parent" => $parent
			));
			
			AuditTrail::track("bigtree_resource_folders", $id, "created");
			
			return new ResourceFolder($id);
		}
		
		/*
			Function: delete
				Deletes the resource folder and all of its sub folders and resources.
		*/
		
		function delete() {
			// Get everything inside the folder
			$items = $this->Contents;
			
			// Delete all subfolders
			foreach ($items["folders"] as $folder) {
				$folder = new ResourceFolder($folder);
				$folder->delete();
			}
			
			// Delete all files
			foreach ($items["resources"] as $resource) {
				$resource = new Resource($resource);
				$resource->delete();
			}
			
			// Delete the folder
			SQL::delete("bigtree_resource_folders", $this->ID);
			AuditTrail::track("bigtree_resource_folders", $this->ID, "deleted");
		}
		
		/*
			Function: getBreadcrumb
				Returns the breadcrumb for the folder.

			Returns:
				An array of arrays containing the name and id of folders above.
		*/
		
		function getBreadcrumb($folder = false, $crumb = array()) {
			// First call won't have folder
			if (!$folder) {
				$folder = $this;
			}
			
			// Add crumb part
			$crumb[] = array("id" => $folder->ID, "name" => $folder->Name);
			
			// If we have a parent, go higher up
			if ($folder->Parent) {
				return $this->getBreadcrumb(new ResourceFolder($this->Parent), $crumb);
				
			// Append home, reverse, return
			} else {
				$crumb[] = array("id" => 0, "name" => "Home");
				
				return array_reverse($crumb);
			}
		}
		
		/*
			Function: getContents
				Returns an array of resources and subfolders in a folder.

			Parameters:
				sort - The column to sort the folder's files on (default: date DESC).

			Returns:
				An array of two arrays - folders and resources.
		*/
		
		function getContents($sort = "date DESC") {
			$null_query = $this->ID ? "" : "OR folder IS NULL";
			
			$folders = SQL::fetchAll("SELECT * FROM bigtree_resource_folders WHERE parent = ? ORDER BY name", $this->ID);
			$resources = SQL::fetchAll("SELECT * FROM bigtree_resources WHERE folder = ? $null_query ORDER BY $sort", $this->ID);
			
			return array("folders" => $folders, "resources" => $resources);
		}
		
		/*
			Function: getStatistics
				Returns the number of items inside the folder and it's subfolders and the number of allocations of the contained resources.

			Returns:
				A keyed array of "resources", "folders", and "allocations" for the number of resources, sub folders, and allocations.
		*/
		
		function getStatistics() {
			$allocations = $folders = $resources = 0;
			$items = $this->Contents;
			
			// Loop through subfolders
			foreach ($items["folders"] as $folder) {
				$folders++;
				
				$sub_folder = new ResourceFolder($folder);
				$sub_folder_stats = $sub_folder->Statistics;
				
				$allocations += $sub_folder_stats["allocations"];
				$folders += $sub_folder_stats["folders"];
				$resources += $sub_folder_stats["resources"];
			}
			
			foreach ($items["resources"] as $resource) {
				$resources++;
				
				$resource = new Resource($resource);
				$allocations += $resource->AllocationCount;
			}
			
			return array("allocations" => $allocations, "folders" => $folders, "resources" => $resources);
		}
		
		/*
			Function: getUserAccessLevel
				Returns the permission level of the current user for the folder.

			Returns:
				"p" if a user can create folders and upload files, "e" if the user can see/use files, "n" if a user can't access this folder.
		*/
		
		function getUserAccessLevel() {
			return Auth::user()->getAccessLevel($this);
		}
		
		/*
			Function: root
				Returns a ResourceFolder object for the root folder.

			Returns:
				A ResourceFolder object.
		*/
		
		static function root() {
			return new ResourceFolder(array("id" => "0", "parent" => "-1", "name" => "Home"));
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		function save() {
			if (empty($this->ID)) {
				$new = static::create($this->Parent, $this->Name);
				$this->inherit($new);
			} else {
				SQL::update("bigtree_resource_folders", $this->ID, array(
					"name" => Text::htmlEncode($this->Name),
					"parent" => intval($this->Parent)
				));
				
				AuditTrail::track("bigtree_resource_folders", $this->ID, "updated");
			}
		}
		
	}
