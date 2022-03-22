<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Static_model extends CI_Model {	
	function get_halls() {
		return $this->db->get('halls')->result_array();
	}

	function posts_hall($hall_id) {
		return $this->db->get_where('posts', ['hall_id' => $hall_id])->num_rows();
	}

	function get_specializations($dept) {
		return $this->db->select('name')->get_where('specialization', ['department' => $dept])->result_array();
	}

	function get_departments() {
		return $this->db->get('departments')->result_array();
	}

	function dept_exists($dept) {
		return $this->db->get_where('departments', ['name' => $dept])->num_rows();
	}

	function search_posts($sort, $id_array) {
		if (empty($id_array))
			$id_array = [0];

		$this->db->select('title, post_id, hall_id, body, attachment1, attachment2, attachment3, created_at, updated_at, user_id');
		$this->db->from('posts');

		$this->db->group_start();
			$this->db->like('title', $this->input->post('search_data'));
			$this->db->or_like('body', $this->input->post('search_data'));
		$this->db->group_end();

		$this->db->where('is_deleted', 0);

		$this->db->or_group_start();
			$this->db->where_in('user_id', $id_array);
			$this->db->where('is_deleted', 0);
		$this->db->group_end();

		$this->db->order_by('post_id', $sort);
		
		return $this->db->get()->result_array();
	}

	function search_users() {		
		return $this->db->select('id, first_name, last_name, display_name, profile_photo, cover_photo, reputation_points, email_verified')->get('users')->result_array();
	}

	function save_otp($user_id, $admin_id, $verification_code) {
		$expiration  = date("Y-m-d H:i:s", strtotime('+65 seconds'));

		if (is_null($user_id)) {
			$data = $this->db->get_where('otp', ['admin_id' => $admin_id])->row_array();

			if(!is_null($data)) {
				if($data['expiration'] < date("Y-m-d H:i:s")) {
					return $this->db->update('otp', [
														'pin' 				=> $verification_code,
														'expiration'		=> $expiration
													], ['admin_id' => $admin_id]);
				}
			} else {
				return $this->db->insert('otp', [
													'admin_id' 			=> $admin_id,
													'pin' 				=> $verification_code,
													'expiration'		=> $expiration
												]);
			}
		} elseif (is_null($admin_id)) {
			$data = $this->db->get_where('otp', ['user_id' => $user_id])->row_array();

			if(!is_null($data)) {
				if($data['expiration'] < date("Y-m-d H:i:s")) {
					return $this->db->update('otp', [
														'pin' 				=> $verification_code,
														'expiration'		=> $expiration
													], ['user_id' => $user_id]);
				}
			} else {
				return $this->db->insert('otp', [
													'user_id' 			=> $user_id,
													'pin' 				=> $verification_code,
													'expiration'		=> $expiration
												]);
			}
		}

		return false;
	}
}