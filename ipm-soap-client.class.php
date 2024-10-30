<?php
	/*
		This class is used for SOAP communication between a WordPress Client and a Compatible IPManager server.
	*/
	class ipm_soap_client extends ipm_soap {
		
		private $config 		= NULL;
		private $last_result	= NULL;
		private $is_connected	= FALSE;
	
		function __construct() {
		
			if (ipm_get_config('active') == 1) {

				$this->config['remote_server_url']			= ipm_get_config('remote_site_soap_url');
				
				parent::__construct($this->config['remote_server_url']);

				$this->is_connected = TRUE;

			}
			else {
				$this->is_connected = FALSE;
			}
		}
		
		public function get_last_result() {
			if (isset($this->last_result) && is_array($this->last_result)) {
				return $this->last_result;
			}
			else {
				return false;
			}
		}
		
		public function is_connected() {
			return $this->is_connected;
		}
				
		public function add_anonymous_ticket($array) {
		
			if ($this->is_connected) {

				$ticket_array['name']				= $array['name'];
				$ticket_array['email']				= $array['email'];
				$ticket_array['subject']			= $array['subject'];
				$ticket_array['description']		= $array['description'];

				$send_array['addTicketArray']		= $ticket_array;
				
				$result = $this->call('ipm_soap_add_anonymous_ticket', array($send_array));
				$this->last_result = $result;
				
				if (function_exists('btev_trigger_error')) {
					btev_trigger_error('IPManager: '. $result['message'], E_USER_NOTICE, __FILE__, __LINE__);
				}

				return $result;
			}
			else {
				return false;
			}
		}
		
	}

?>