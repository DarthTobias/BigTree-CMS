<?php
	/*
		Class: BigTree\Email
			Provides an interface for creating an email and sending it.
			You should create an object, set its properties, then call the send method.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read string $Service
	 * @property-read array $Settings
	 */
	
	class Email extends BaseObject {
		
		protected $Service;
		protected $Settings;
		
		public $BCC = false;
		public $CC = false;
		public $Error;
		public $From = "";
		public $Headers = [];
		public $HTML = "";
		public $ReplyTo = "";
		public $Subject = "";
		public $Text = "";
		public $To = "";
		
		function __construct() {
			$setup = Setting::value("bigtree-internal-email-service");
			
			// Setting doesn't exist? Create it.
			if ($setup === false) {
				$setting = Setting::create("bigtree-internal-email-service", "Email Service", "", "", [], "", true, true, true);
				$setting->Value = ["service" => "Local", "settings" => []];
				$setting->save();
			} else {
				$this->Service = $setup["service"];
				$this->Settings = $setup["settings"];
			}
			
			if (!$this->Service) {
				$this->Service = "Local";
			}
		}
		
		/*
			Function: send
				Sends the email with the preferred selected email service provider.
		*/
		
		function send(): bool {
			$provider_string = "BigTree\\EmailService\\".$this->Service;
			
			$provider = new $provider_string($this->Settings);
			$success = $provider->send($this);
			
			if (!$success) {
				$this->Error = $provider->Error;
			}
			
			return $success;
		}
		
	}

