<?php
	/*
		Class: BigTree\CloudStorage\Rackspace
			A cloud storage interface class for Rackspace Cloud Files.
			See BigTree\CloudStorage\Provider for method definitions.
	*/

	namespace BigTree\CloudStorage;

	use BigTree\cURL;

	class Rackspace extends Provider {

		protected $CDNContainerURLs;
		protected $CDNEndpoints;
		protected $Endpoints;
		protected $TempURLKey;
		protected $Token;
		protected $TokenExpiration;

		public $CDNEndpoint = "";
		public $Endpoint = "";
		public $Key;
		public $Regions = array(
			"ORD" => "Chicago, IL (USA)",
			"DFW" => "Dallas/Ft. Worth, TX (USA)",
			"HKG" => "Hong Kong",
			"LON" => "London (UK)",
			"IAD" => "Northern Virginia (USA)",
			"SYD" => "Sydney (Australia)"
		);
		public $Region;
		public $Username;

		function __construct() {
			parent::__construct();

			// Setup references to the main cloud storage setting
			$this->Active = &$this->Settings["rackspace"]["active"];
			$this->CDNContainerURLs = &$this->Settings["rackspace"]["container_cdn_urls"];
			$this->CDNEndpoints = &$this->Settings["rackspace"]["cdn_endpoints"];
			$this->Endpoints = &$this->Settings["rackspace"]["endpoints"];
			$this->Key = &$this->Settings["rackspace"]["api_key"];
			$this->Region = &$this->Settings["rackspace"]["region"];
			$this->TempURLKey = &$this->Settings["rackspace"]["temp_url_key"];
			$this->Token = &$this->Settings["rackspace"]["token"];
			$this->TokenExpiration = &$this->Settings["rackspace"]["token_expiration"];
			$this->Username = &$this->Settings["rackspace"]["username"];

			if ($this->Active) {
				if (!isset($this->TokenExpiration) || $this->TokenExpiration < time()) {
					$this->getToken();
				}

				// Current settings
				$this->Endpoint = $this->Endpoints[$this->Region];
				$this->CDNEndpoint = $this->CDNEndpoints[$this->Region];
			}
		}

		/*
			Function: call
				cURL wrapper for Rackspace API.

			Parameters:
				endpoint - Endpoint to hit.
				data - Request body data.
				curl_options - Additional cURL options.
		*/

		function call($endpoint = "",$data = false,$method = "GET",$curl_options = array()) {
			$curl_options = $curl_options + array(
					CURLOPT_HTTPHEADER => array(
						"Accept: application/json","X-Auth-Token: ".$this->Token
					)
				);

			return json_decode(cURL::request($this->Endpoint.($endpoint ? "/$endpoint" : ""),$data,$curl_options));
		}

		// Implements Provider::copyFile
		function copyFile($source_container,$source_pointer,$destination_container,$destination_pointer,$public = false) {
			global $bigtree;

			cURL::request($this->Endpoint."/$source_container/$source_pointer",false,array(
				CURLOPT_CUSTOMREQUEST => "COPY",
				CURLOPT_HTTPHEADER => array(
					"Destination: /$destination_container/$destination_pointer",
					"X-Auth-Token: ".$this->Token
				)
			));

			if ($bigtree["last_curl_response_code"] == "201") {
				return $this->getURL($destination_container, $destination_pointer);
			}

			return false;
		}

		// Implements Provider::createContainer
		function createContainer($name,$public = false) {
			global $bigtree;

			$this->call($name,"","PUT",array(CURLOPT_PUT => true));

			if ($bigtree["last_curl_response_code"] == 201) {
				// CDN Enable this container if it's public
				if ($public) {
					cURL::request($this->CDNEndpoint."/$name",false,array(
						CURLOPT_PUT => true,
						CURLOPT_HTTPHEADER => array(
							"X-Auth-Token: ".$this->Token,
							"X-Cdn-Enabled: true"
						)
					));
				}

				return true;
			}

			return false;
		}

		// Implements Provider::createFile
		function createFile($contents,$container,$pointer,$public = false,$type = "text/plain") {
			global $bigtree;

			cURL::request($this->Endpoint."/$container/$pointer",$contents,array(
				CURLOPT_CUSTOMREQUEST => "PUT",
				CURLOPT_HTTPHEADER => array(
					"Content-Length" => strlen($contents),
					"X-Auth-Token: ".$this->Token
				)
			));

			if ($bigtree["last_curl_response_code"] == "201") {
				return $this->getURL($container,$pointer);
			}

			return false;
		}

		// Implements Provider::deleteContainer
		function deleteContainer($container) {
			global $bigtree;
			
			$this->call($container,"","DELETE",array(CURLOPT_CUSTOMREQUEST => "DELETE"));
			
			if ($bigtree["last_curl_response_code"] == 204) {
				return true;
			} elseif ($bigtree["last_curl_response_code"] == 404) {
				$this->Errors[] = array("message" => "Container was not found.");
			} elseif ($bigtree["last_curl_response_code"] == 409) {
				$this->Errors[] = array("message" => "Container could not be deleted because it is not empty.");
			}
			
			return false;
		}

		// Implements Provider::deleteFile
		function deleteFile($container,$pointer) {
			global $bigtree;

			$this->call("$container/$pointer","","DELETE",array(CURLOPT_CUSTOMREQUEST => "DELETE"));

			if ($bigtree["last_curl_response_code"] == 204) {
				return true;
			}

			return false;
		}

		// Implements Provider::getAuthenticatedFileURL
		function getAuthenticatedFileURL($container,$pointer,$expires) {
			$expires += time();

			// If we don't have a Temp URL key already set, we need to make one
			if (!$this->TempURLKey) {
				// See if we already have one
				$response = cURL::request($this->Endpoint,false,array(
					CURLOPT_CUSTOMREQUEST => "HEAD",
					CURLOPT_HEADER => true,
					CURLOPT_HTTPHEADER => array("X-Auth-Token: ".$this->Token)
				));

				$headers = explode("\n",$response);
				foreach ($headers as $header) {
					if (substr($header,0,28) == "X-Account-Meta-Temp-Url-Key:") {
						$this->TempURLKey = trim(substr($header,29));
					}
				}

				// If we don't have an existing one, make up our own
				if (!$this->TempURLKey) {
					$this->TempURLKey = uniqid();

					cURL::request($this->Endpoint,false,array(
						CURLOPT_CUSTOMREQUEST => "POST",
						CURLOPT_HTTPHEADER => array(
							"X-Auth-Token: ".$this->Token,
							"X-Account-Meta-Temp-Url-Key: ".$this->TempURLKey
						)
					));
				}
			}

			list($domain,$client_id) = explode("/v1/",$this->Endpoint);
			$hash = urlencode(hash_hmac("sha1","GET\n$expires\n/v1/$client_id/$container/$pointer",$this->TempURLKey));

			return $this->Endpoint."/$container/$pointer?temp_url_sig=$hash&temp_url_expires=$expires";
		}

		// Implements Provider::getContainer
		function getContainer($container,$simple = false) {
			$flat = array();

			$response = $this->call($container);

			if (is_array($response)) {
				foreach ($response as $item) {
					if ($simple) {
						$flat[] = array(
							"name" => (string) $item->name,
							"path" => (string) $item->name,
							"size" => (int) $item->bytes
						);
					} else {
						$flat[(string) $item->name] = array(
							"name" => (string) $item->name,
							"path" => (string) $item->name,
							"updated_at" => date("Y-m-d H:i:s",strtotime($item->last_modified)),
							"etag" => (string) $item->hash,
							"size" => (int) $item->bytes
						);
					}
				}
			} else {
				trigger_error('BigTree\CloudStorage\Rackspace::getContainer call failed.', E_USER_WARNING);

				return array();
			}

			return $simple ? $flat : array("tree" => $this->getContainerTree($flat), "flat" => $flat);
		}

		// Implements Provider::getFile
		function getFile($container,$pointer) {
			return cURL::request($this->Endpoint."/$container/$pointer",false,array(
				CURLOPT_HTTPHEADER => array("X-Auth-Token: ".$this->Token)
			));
		}

		// Internal method for refreshing a Rackspace token
		function getToken() {
			$j = json_decode(cURL::request("https://identity.api.rackspacecloud.com/v2.0/tokens",json_encode(array(
				"auth" => array(
					"RAX-KSKEY:apiKeyCredentials" => array(
						"username" => $this->Username,
						"apiKey" => $this->Key
					)
				)
			)),array(CURLOPT_POST => true,CURLOPT_HTTPHEADER => array("Content-Type: application/json"))));

			if (isset($j->access->token)) {
				$this->Token = $j->access->token->id;
				$this->TokenExpiration = strtotime($j->access->token->expires);
				$this->Endpoints = array();
				$this->CDNEndpoints = array();

				// Get API endpoints
				foreach ($j->access->serviceCatalog as $service) {
					if ($service->name == "cloudFiles") {
						foreach ($service->endpoints as $endpoint) {
							$this->Endpoints[$endpoint->region] = (string)$endpoint->publicURL;
						}
					} elseif ($service->name == "cloudFilesCDN") {
						foreach ($service->endpoints as $endpoint) {
							$this->CDNEndpoints[$endpoint->region] = (string)$endpoint->publicURL;
						}
					}
				}

				return true;
			}

			return false;
		}

		// Internal method for getting the live URL of an asset
		function getURL($container,$pointer) {
			if ($this->CDNContainerURLs[$container]) {
				return $this->CDNContainerURLs[$container]."/$pointer";
			} else {
				// See if we can get the container's CDN URL
				$cdn = false;
				$response = cURL::request($this->CDNEndpoint."/$container",false,array(CURLOPT_CUSTOMREQUEST => "HEAD",CURLOPT_HEADER => true,CURLOPT_HTTPHEADER => array("X-Auth-Token: ".$this->Settings["rackspace"]["token"])));
				$lines = explode("\n",$response);
				foreach ($lines as $line) {
					if (substr($line,0,10) == "X-Cdn-Uri:") {
						$cdn = trim(substr($line,10));
					}
				}
				if ($cdn) {
					$this->CDNContainerURLs[$container] = $cdn;
					return "$cdn/$pointer";
				}
			}
			return $this->Endpoint."/$container/$pointer";
		}

		// Implements Provider::listContainers
		function listContainers() {
			$containers = array();
			$response = $this->call();

			if (is_array($response)) {
				foreach ($response as $item) {
					$containers[] = array("name" => (string)$item->name);
				}
			} else {
				return false;
			}

			return $containers;
		}

		// Implements Provider::uploadFile
		function uploadFile($file,$container,$pointer = false,$public = false) {
			global $bigtree;

			// No target destination, just use root folder w/ file name
			if (!$pointer) {
				$path_info = pathinfo($file);
				$pointer = $path_info["basename"];
			}

			// Open the file pointer for curl to upload from
			$file_pointer = fopen($file,"r");

			cURL::request($this->Endpoint."/$container/$pointer",false,array(
				CURLOPT_PUT => true,
				CURLOPT_INFILE => $file_pointer,
				CURLOPT_HTTPHEADER => array(
					"Content-Length" => filesize($file),
					"X-Auth-Token: ".$this->Token
				)
			));

			fclose($file_pointer);

			if ($bigtree["last_curl_response_code"] == "201") {
				return $this->getURL($container,$pointer);
			}

			return false;
		}

	}