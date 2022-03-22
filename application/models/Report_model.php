<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Report_model extends CI_Model {
	function __construct() {
		$this->load->database();
	}

	function submit_report($reporter_id, $reportee_id, $type) {
		$this->db->insert('reports', [
										'reporter_id'  => $this->encryption->encrypt($reporter_id),
										$type 		   => $this->encryption->encrypt($reportee_id),
										'message' 	   => $this->encryption->encrypt($this->input->post('message'))
									]);

		if ($this->db->affected_rows() == 1)
			return [
				'status' 	=> $this->db->affected_rows(),
				'report_id'	=> $this->db->insert_id()
			];
	}

	function does_user_exist($user_id) {
		return $this->db->get_where('users', ['id' => $user_id])->num_rows();
	}

	function does_post_exist($post_id) {
		return $this->db->get_where('posts', ['post_id' => $post_id])->num_rows();
	}

	function does_critique_exist($critique_id) {
		return $this->db->get_where('critiques', ['critique_id' => $critique_id])->num_rows();
	}

	function does_reply_exist($reply_id) {
		return $this->db->get_where('replies', ['reply_id' => $reply_id])->num_rows();
	}
}