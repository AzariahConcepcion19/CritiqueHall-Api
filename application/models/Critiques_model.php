<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Critiques_model extends CI_Model {	
	function __construct() {
		$this->load->database();
	}

	function display_critiques($post_id, $sort) {
		$is_post = $this->db->select('is_deleted')->get_where('posts', ['post_id' => $post_id])->row_array();

		if (!$is_post['is_deleted'])
			return $this->db->order_by('critique_id', $sort)->get_where('critiques', [
																						'post_id'	 => $post_id,
																						'is_deleted' => 0
																					])->result_array();

		return 0;
	}

	function create_critique($user_id) {
		$this->db->insert('critiques', [
										'user_id' => $user_id,
										'post_id' => $this->input->post('post_id'),
										'body' 	  => $this->input->post('body')
									]);

		if ($this->db->affected_rows() == 1) {
			$action  = 'Created critique id#'.$this->db->insert_id();

			$owner = $this->db->select('user_id')->get_where('posts', ['post_id' => $this->input->post('post_id')])->row_array();

			if ($user_id != $owner['user_id'])
				$this->db->insert('notifs', [
					'owner_id'  => $owner['user_id'],
					'user_id'	=> $user_id,
					'post_id' 	=> $this->input->post('post_id'),
					'action'    => $this->encryption->encrypt("Critiqued")
				]);

			$this->Audit_log_model->audit_log($user_id, null, $action);

			return true;
		}

		return false;
	}

	function update_critique($user_id) {
		$conditions = [
			'user_id' 	  => $user_id,
			'critique_id' => $this->input->post('critique_id')
		];

		$critique_data = $this->db->get_where('critiques', $conditions)->row_array();

		$this->db->update('critiques', ['body' => $this->input->post('body')], $conditions);

		if ($this->db->affected_rows() == 1) {
			$ver_num = $this->db->get_where('critique_versions', ['critique_id' => $critique_data['critique_id']])->num_rows();

			($ver_num == 0 ? $date = $critique_data['created_at'] : $date = $critique_data['updated_at']);
			
			$this->db->insert('critique_versions', [
													'critique_id'     => $critique_data['critique_id'],
													'body'	   	 	  => $critique_data['body'],
													'created_at' 	  => $date
												]);

			$action = 'Updated critique id#'.$this->input->post('critique_id');
			$this->Audit_log_model->audit_log($user_id, null, $action);
		}

		return $this->db->affected_rows();
	}

	function delete_critique($critique_id, $user_id) {
		$conditions = [
			'user_id' 	  => $user_id,
			'critique_id' => $critique_id
		];

		$this->db->update('critiques', ['is_deleted' => 1], $conditions);

		if ($this->db->affected_rows() == 1) {
			$action = 'Deleted critique id#'.$critique_id;
			$this->Audit_log_model->audit_log($user_id, null, $action);
		}

		return $this->db->affected_rows(); 
	}

	function star_critique($user_id) {
		$post_id 	 = $this->input->post('post_id');
		$critique_id = $this->input->post('critique_id');

		$conditions = [
			'user_id' 	  => $user_id,
			'critique_id' => $critique_id
		];

		$critique_owner 	 = $this->db->select('user_id')->get_where('critiques', ['critique_id' => $critique_id])->row_array();
		$post_owner 		 = $this->db->select('user_id')->get_where('posts', ['post_id' => $post_id])->row_array();
		$critique_owner_data = $this->db->get_where('users', ['id' => $critique_owner['user_id']])->row_array(); 
		$data 	 			 = $this->db->get_where('critique_stars', $conditions)->num_rows();

		if ($data) {
			if ($post_owner['user_id'] == $user_id) {
				if ($critique_owner['user_id'] != $user_id)
					$this->db->update('users', ['reputation_points' => $critique_owner_data['reputation_points']-3], ['id' => $critique_owner_data['id']]);

				if ($post_owner['user_id'] != $critique_owner['user_id'])
					$this->db->update('critiques', ['starred_by_author' => 0], ['critique_id' => $critique_id]);
			} else {
				if ($critique_owner['user_id'] != $user_id)
					$this->db->update('users', ['reputation_points' => $critique_owner_data['reputation_points']-1], ['id' => $critique_owner_data['id']]);
			}

			if ($critique_owner['user_id'] != $user_id)
				$this->db->delete('notifs', [
					'owner_id'  	=> $critique_owner['user_id'],
					'user_id'		=> $user_id,
					'critique_id' 	=> $critique_id
				]);

			$this->db->delete('critique_stars', $conditions);
			$action = 'Unstarred critique id#'.$critique_id;
		} else {
			if ($post_owner['user_id'] == $user_id) {
				if ($critique_owner['user_id'] != $user_id)
					$this->db->update('users', ['reputation_points' => $critique_owner_data['reputation_points']+3], ['id' => $critique_owner_data['id']]);

				if ($post_owner['user_id'] != $critique_owner['user_id'])
					$this->db->update('critiques', ['starred_by_author' => 1], ['critique_id' => $critique_id]);
			} else {
				if ($critique_owner['user_id'] != $user_id)
					$this->db->update('users', ['reputation_points' => $critique_owner_data['reputation_points']+1], ['id' => $critique_owner_data['id']]);
			}

			if ($critique_owner['user_id'] != $user_id)
				$this->db->insert('notifs', [
					'owner_id'  	=> $critique_owner['user_id'],
					'user_id'		=> $user_id,
					'critique_id' 	=> $critique_id,
					'action'    	=> $this->encryption->encrypt("Starred")
				]);

			$this->db->insert('critique_stars', $conditions);
			$action = 'Starred critique id#'.$critique_id;
		}

		$status = $this->db->affected_rows();
		$this->Audit_log_model->audit_log($user_id, null, $action);

		$stars = $this->db->get_where('critique_stars', ['critique_id' => $critique_id])->num_rows();

		return [
			'status' => $status,
			'stars'	 => $stars
		];
	}

	function get_author_photo($post_id) {
		$data = $this->db->select('user_id')->get_where('posts', ['post_id' => $post_id])->row_array();
		$data = $this->db->select('profile_photo')->get_where('users', ['id' => $data['user_id']])->row_array();

		return $this->encryption->decrypt($data['profile_photo']);
	}

	function is_starred($critique_id, $user_id) {
		return $this->db->get_where('critique_stars', [
														'user_id' 	  => $user_id,
														'critique_id' => $critique_id
													])->num_rows();
	}

	function get_stars($critique_id) {
		return $this->db->get_where('critique_stars', ['critique_id' => $critique_id])->num_rows();
	}

	function get_version_critiques($critique_id) {
		$is_reply = $this->db->select('is_deleted')->get_where('critiques', ['critique_id' => $critique_id])->row_array();

		if (!$is_reply['is_deleted'])
			return $this->db->get_where('critique_versions', [
																'critique_id' => $critique_id
															])->result_array();

		return 0;
	}

	function is_edited($critique_id) {
		$data = $this->db->get_where('critique_versions', ['critique_id' => $critique_id])->num_rows();

		if ($data > 0)
			return 1;

		return 0;
	}
//---------------------------------CAN BE IMPROVED BY USING JOIN-------------------------------
	function check_reputation_points($user_id) {
		$data = $this->db->select('reputation_points')->get_where('users', ['id' => $user_id])->row_array();

		return $data['reputation_points'];
	}
}