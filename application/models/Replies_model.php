<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Replies_model extends CI_Model {	
	function __construct() {
		$this->load->database();
	}

	function display_replies($critique_id) {
		$is_critique = $this->db->select('is_deleted')->get_where('critiques', ['critique_id' => $critique_id])->row_array();

		if (!$is_critique['is_deleted'])
			return $this->db->order_by('reply_id', 'ASC')->get_where('replies', [
																					'critique_id' => $critique_id,
																					'is_deleted'  => 0
																				])->result_array();

		return 0;
	}

	function create_reply($user_id) {
		$this->db->insert('replies', [
										'user_id' 	  => $user_id,
										'critique_id' => $this->input->post('critique_id'),
										'body' 	  	  => $this->input->post('body')
									]);

		if ($this->db->affected_rows() == 1) {
			$action  = 'Created reply id#'.$this->db->insert_id();

			$owner = $this->db->select('user_id')->get_where('critiques', ['critique_id' => $this->input->post('critique_id')])->row_array();

			if ($user_id != $owner['user_id'])
				$this->db->insert('notifs', [
					'owner_id'  	=> $owner['user_id'],
					'user_id'		=> $user_id,
					'critique_id' 	=> $this->input->post('critique_id'),
					'action'    	=> $this->encryption->encrypt("Replied")
				]);
			
			$this->Audit_log_model->audit_log($user_id, null, $action);

			return true;
		}

		return false;
	}

	function update_reply() {
		$conditions = [
			'user_id' 	  => $this->encryption->decrypt($this->input->request_headers()['User-Id']),
			'reply_id' 	  => $this->input->post('reply_id')
		];

		$reply_data = $this->db->get_where('replies', $conditions)->row_array();

		$this->db->update('replies', ['body' => $this->input->post('body')], $conditions);

		if ($this->db->affected_rows() == 1) {
			$ver_num = $this->db->get_where('reply_versions', ['reply_id' => $reply_data['reply_id']])->num_rows();

			($ver_num == 0 ? $date = $reply_data['created_at'] : $date = $reply_data['updated_at']);
			
			$this->db->insert('reply_versions', [
													'reply_id'   => $reply_data['reply_id'],
													'body'	   	 => $reply_data['body'],
													'created_at' => $date
												]);

			$action = 'Updated reply id#'.$this->encryption->decrypt($this->input->post('reply_id'));
			$this->Audit_log_model->audit_log($this->encryption->decrypt($this->input->post('user_id')), null, $action);
		}

		return $this->db->affected_rows();
	}

	function get_author_photo($critique_id) {
		$data = $this->db->select('post_id')->get_where('critiques', ['critique_id' => $critique_id])->row_array();
		$data = $this->db->select('user_id')->get_where('posts', ['post_id' => $data['post_id']])->row_array();
		$data = $this->db->select('profile_photo')->get_where('users', ['id' => $data['user_id']])->row_array();

		return $this->encryption->decrypt($data['profile_photo']);
	}

	function delete_replies($reply_id, $user_id) {
		$conditions = [
			'user_id' 	  => $user_id,
			'reply_id' 	  => $reply_id
		];

		$this->db->update('replies', ['is_deleted' => 1], $conditions);
		$status = $this->db->affected_rows();

		if ($status) {
			$action = 'Deleted reply id#'.$this->encryption->decrypt($this->input->post('reply_id'));
			$this->Audit_log_model->audit_log($this->encryption->decrypt($this->input->post('user_id')), null, $action);
		}

		return $status; 
	}

	/*
	- Is critique star doer the author of the post
	*/
	function star_reply($user_id) {
		$post_id  = $this->input->post('post_id');
		$reply_id = $this->input->post('reply_id');

		$conditions = [
			'user_id'  => $user_id,
			'reply_id' => $reply_id
		];

		$reply_owner 	  = $this->db->select('user_id')->get_where('replies', ['reply_id' => $reply_id])->row_array();
		$post_owner  	  = $this->db->select('user_id')->get_where('posts', ['post_id' => $post_id])->row_array();
		$reply_owner_data = $this->db->get_where('users', ['id' => $reply_owner['user_id']])->row_array(); 
		$data 	 		  = $this->db->get_where('reply_stars', $conditions)->num_rows();

		if ($data) {
			if ($post_owner['user_id'] == $user_id) {
				if ($reply_owner['user_id'] != $user_id)
					$this->db->update('users', ['reputation_points' => $reply_owner_data['reputation_points']-3], ['id' => $reply_owner_data['id']]);

				if ($post_owner['user_id'] != $reply_owner['user_id'])
					$this->db->update('replies', ['starred_by_author' => 0], ['reply_id' => $reply_id]);
			} else {
				if ($reply_owner['user_id'] != $user_id)
					$this->db->update('users', ['reputation_points' => $reply_owner_data['reputation_points']-1], ['id' => $reply_owner_data['id']]);
			}

			if ($reply_owner['user_id'] != $user_id)
				$this->db->insert('notifs', [
					'owner_id'  => $reply_owner['user_id'],
					'user_id'	=> $user_id,
					'reply_id' 	=> $reply_id
				]);

			$this->db->delete('reply_stars', $conditions);
			$action = 'Unstarred reply id#'.$this->encryption->decrypt($this->input->post('reply_id'));
		} else {
			if ($post_owner['user_id'] == $user_id) {
				if ($reply_owner['user_id'] != $user_id)
					$this->db->update('users', ['reputation_points' => $reply_owner_data['reputation_points']+3], ['id' => $reply_owner_data['id']]);

				if ($post_owner['user_id'] != $reply_owner['user_id'])
					$this->db->update('replies', ['starred_by_author' => 1], ['reply_id' => $reply_id]);
			} else {
				if ($reply_owner['user_id'] != $user_id)
					$this->db->update('users', ['reputation_points' => $reply_owner_data['reputation_points']+1], ['id' => $reply_owner_data['id']]);
			}

			if ($reply_owner['user_id'] != $user_id)
				$this->db->insert('notifs', [
					'owner_id'  => $reply_owner['user_id'],
					'user_id'	=> $user_id,
					'reply_id' 	=> $reply_id,
					'action'    => $this->encryption->encrypt("Starred")
				]);

			$this->db->insert('reply_stars', $conditions);
			$action = 'Starred reply id#'.$this->encryption->decrypt($this->input->post('reply_id'));
		}

		$status = $this->db->affected_rows();

		$this->Audit_log_model->audit_log($this->encryption->decrypt($this->input->post('user_id')), null, $action);

		$stars = $this->db->get_where('reply_stars', ['reply_id' => $reply_id])->num_rows();

		return [
			'status' => $status,
			'stars'	 => $stars
		];
	}

	function is_starred($reply_id, $user_id) {
		return $this->db->get_where('reply_stars', [
														'user_id' 	=> $user_id,
														'reply_id'	=> $reply_id
													])->num_rows();
	}

	function get_stars($reply_id) {
		return $this->db->get_where('reply_stars', ['reply_id' => $reply_id])->num_rows();
	}

	function get_version_replies($reply_id) {
		$is_reply = $this->db->select('is_deleted')->get_where('replies', ['reply_id' => $reply_id])->row_array();

		if (!$is_reply['is_deleted'])
			return $this->db->get_where('reply_versions', [
															'reply_id' => $reply_id
														])->result_array();

		return 0;
	}

	function is_edited($reply_id) {
		$data = $this->db->get_where('reply_versions', ['reply_id' => $reply_id])->num_rows();

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