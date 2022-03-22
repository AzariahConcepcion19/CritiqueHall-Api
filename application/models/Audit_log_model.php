<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Audit_log_model extends CI_Model {
	function __construct() {
		$this->load->database();
	}

	function audit_log($user_id, $admin_id, $action) {
		if (is_numeric($user_id) && is_null($admin_id)) 
			return $this->db->insert('audit_log', [
													'user_id' => $this->encryption->encrypt($user_id),
													'action'  => $this->encryption->encrypt($action)
												]);
		elseif (is_null($user_id) && is_numeric($admin_id))
			return $this->db->insert('audit_log', [
													'admin_id' => $this->encryption->encrypt($admin_id),
													'action'   => $this->encryption->encrypt($action)
												]);
	}
}