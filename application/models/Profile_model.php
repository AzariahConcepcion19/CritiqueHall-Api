<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Profile_model extends CI_Model {
	function __construct() {
		$this->load->database();
	}

	function display_user($display_name) {
		$users = $this->db->select('id, first_name, last_name, display_name, profile_photo, cover_photo, specialization, about_me, reputation_points')->get('users')->result_array();

		foreach ($users as $user)
			if (strtolower($this->encryption->decrypt($user['display_name'])) == strtolower($display_name))
				return $user;

		return null;
	}

	function display_user_posts($sort) {
		$conditions = [
			'user_id'    => $this->encryption->decrypt($this->input->post('user_id')),
			'is_deleted' => 0
		];

		return $this->db->select('post_id, hall_id, body, attachment1, attachment2, attachment3, created_at, updated_at')
						->order_by('post_id', $sort)
						->get_where('posts', $conditions)
						->result_array();
	}

	function display_user_critiques($sort) {
		$conditions = [
			'user_id' 	 => $this->encryption->decrypt($this->input->post('user_id')),
			'is_deleted' => 0
		];

		return $this->db->select('critique_id, body, post_id, created_at, updated_at')
						->order_by('critique_id', $sort)
						->get_where('critiques', $conditions)
						->result_array();
	}

	function change_profile($user_id) {
		$data = [
			'profile_photo'  => $this->encryption->encrypt($this->input->post('profile_photo')),
			'cover_photo'	 => $this->encryption->encrypt($this->input->post('cover_photo')),
			'first_name'	 => $this->encryption->encrypt($this->input->post('first_name')),
			'last_name'		 => $this->encryption->encrypt($this->input->post('last_name')),
			'display_name'	 => $this->encryption->encrypt($this->input->post('display_name')),
			'specialization' => $this->encryption->encrypt($this->input->post('specialization')),
			'about_me'		 => $this->encryption->encrypt($this->input->post('about_me'))
		];
		
		$status = $this->db->update('users', $data, ['id' => $user_id]);
	
		if ($this->db->affected_rows() == 1) {
			$this->Audit_log_model->audit_log($user_id, null, 'Changed Profile Information');

			return true;
		}

		return false;
	}

	function change_pass($user_id) {
		$status = $this->db->update('users', [
									'password' => password_hash($this->input->post('new_password'), PASSWORD_BCRYPT)
								], ['id' => $user_id]);

		if($status) {
			$this->Audit_log_model->audit_log($user_id, null, 'Changed Password');

			return $status;
		}

		return $status;
	}

	function num_likes($post_id) {
		return $this->db->get_where('posts_likes', ['post_id' => $post_id])->num_rows();
	}

	function num_critiques($post_id) {
		return $this->db->get_where('critiques', ['post_id' => $post_id, 'is_deleted' => 0])->num_rows();
	}

	function get_hall($hall_id) {
		$data = $this->db->get_where('halls', ['hall_id' => $hall_id])->row_array();

		return $data;
	}

	function num_replies($critique_id) {
		return $this->db->get_where('replies', ['critique_id' => $critique_id])->num_rows();
	}

	function num_stars($critique_id) {
		return $this->db->get_where('critique_stars', ['critique_id' => $critique_id])->num_rows();
	}

	function get_single_post($post_id) {
		return $this->db->select('post_id, hall_id, attachment1, created_at, updated_at, title, is_deleted')->get_where('posts', ['post_id' => $post_id])->row_array();
	}

	function get_single_critique($critique_id) {
		return $this->db->select('critique_id, created_at, updated_at, body')->get_where('critiques', ['critique_id' => $critique_id])->row_array();
	}

	function get_single_reply($reply_id) {
		return $this->db->select('reply_id, created_at, updated_at, body')->get_where('replies', ['reply_id' => $reply_id])->row_array();
	}

	function get_user_data($user_id) {
		return $this->db->select('password')->get_where('users', ['id' => $user_id])->row_array();
	}

	function get_user_info($user_id) {
		return $this->db->select('profile_photo, cover_photo, first_name, last_name, display_name, about_me')->get_where('users', ['id' => $user_id])->row_array();
	}

	function check_password($user_id) {
		$data = $this->db->select('password')->get_where('users', ['id' => $user_id])->row_array();

		return password_verify($this->input->post('confirm_password'), $data['password']);
	}

	function get_notifs($user_id) {
		return $this->db->order_by('notifs_id', 'DESC')->get_where('notifs', ['owner_id' => $user_id], 20)->result_array();
	}

	function read_notifs($first_id, $user_id) {
		return $this->db->update('notifs', ['is_read' => 1], ['owner_id' => $user_id, 'notifs_id <=' => $first_id,]);
	}
}