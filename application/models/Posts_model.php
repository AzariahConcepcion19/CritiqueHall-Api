<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Posts_model extends CI_Model {
	function __construct() {
		$this->load->database();
	}

	function get_post($post_id) {
		return $this->db->get_where('posts', ['post_id' => $post_id, 'is_deleted' => 0])->row_array();
	}

	function posts_pagination($hall_id) {
		$condition = [
			($hall_id == 0 ? 'hall_id >' : 'hall_id') => $hall_id,
			'is_deleted'							  => 0
		];

		return $this->db->order_by('post_id', 'DESC')->get_where('posts', $condition)->result_array();
	}

	function update_post($post_id, $user_id) {
		$post_data = $this->db->get_where('posts', ['post_id' => $post_id])->row_array();

		$this->db->update('posts', [
									'title'		  => $this->input->post('title'),
									'body'		  => $this->input->post('body'),
								], ['post_id' => $post_id, 'user_id' => $user_id]);

		if ($this->db->affected_rows() == 1) {
			$ver_num = $this->db->get_where('post_versions', ['post_id' => $post_data['post_id']])->num_rows();

			($ver_num == 0 ? $date = $post_data['created_at'] : $date = $post_data['updated_at']);

			$this->db->insert('post_versions', [
												'post_id' 		=> $post_data['post_id'],
												'title'	  		=> $post_data['title'],
												'body'			=> $post_data['body'],
												'attachment1'	=> $post_data['attachment1'],
												'attachment2'	=> $post_data['attachment2'],
												'attachment3'	=> $post_data['attachment3'],
												'attachment4'	=> $post_data['attachment4'],
												'attachment5'	=> $post_data['attachment5'],
												'created_at'	=> $date
											]);
		}

		return $this->db->affected_rows();
	}

	function delete_post($post_id, $user_id) {
		$this->db->update('posts', ['is_deleted' => 1], ['post_id' => $post_id, 'user_id' => $user_id]);
	
		return $this->db->affected_rows();
	}

	function like_post($post_id, $user_id) {
		$conditions = [
			'post_id' => $post_id,
			'user_id' => $user_id
		];

		$owner = $this->db->get_where('posts', ['post_id' => $post_id])->row_array();

		$data = $this->db->get_where('posts_likes', $conditions)->row_array();

	
		if ($data) {
			$this->db->delete('posts_likes', $conditions);
			$action = 'Unstarred post id#'.$post_id;

			if ($owner['user_id'] != $user_id)
				$this->db->delete('notifs', [
					'owner_id'  => $owner['user_id'],
					'user_id'	=> $user_id,
					'post_id' 	=> $post_id,
					'action'    => $this->encryption->encrypt("Starred")
				]);
		} else {
			$this->db->insert('posts_likes', $conditions);
			$action = 'Starred post id#'.$post_id;

			if ($owner['user_id'] != $user_id)
				$this->db->insert('notifs', [
					'owner_id'  => $owner['user_id'],
					'user_id'	=> $user_id,
					'post_id' 	=> $post_id,
					'action'    => $this->encryption->encrypt("Starred")
				]);
		}

		$this->Audit_log_model->audit_log($user_id, null, $action);

		return $this->db->affected_rows();
	}

	function create_post() {
		$user_id 	 = $this->encryption->decrypt($this->input->request_headers()['User-Id']);
		$hall_exists = $this->db->get_where('halls', ['hall_id' => $this->input->post('hall_id')])->num_rows();

		if ($hall_exists) {
			$this->db->insert('posts', [
									'user_id' 		=> $user_id,
									'title' 		=> $this->input->post('title'),
									'body' 			=> $this->input->post('body'),
									'hall_id' 		=> $this->input->post('hall_id'),
									'attachment1'	=> $this->input->post('attachment1'),
									'attachment2'	=> $this->input->post('attachment2'),
									'attachment3'	=> $this->input->post('attachment3'),
									'attachment4'	=> $this->input->post('attachment4'),
									'attachment5'	=> $this->input->post('attachment5'),
								]);

			if ($this->db->affected_rows() == 1) {
				$action  = 'Created Post id#'.$this->db->insert_id();
				$this->Audit_log_model->audit_log($user_id, null, $action);

				return true;
			}
		}

		return false;
	}

	function get_version_posts($post_id) {
		$is_post = $this->db->select('is_deleted')->get_where('posts', ['post_id' => $post_id])->row_array();

		if (!$is_post['is_deleted'])
			return $this->db->order_by('version_id', 'DESC')->get_where('post_versions', ['post_id' => $post_id])->result_array();

		return 0;
	}

	function is_edited($post_id) {
		$data = $this->db->get_where('post_versions', ['post_id' => $post_id])->num_rows();

		if ($data > 0)
			return 1;

		return 0;
	}

	function is_liked($post_id, $user_id) {
		return $this->db->get_where('posts_likes', [
													'post_id' => $post_id,
													'user_id' => $user_id
												])->num_rows();
	}

	function get_likes($post_id) {
		return $this->db->get_where('posts_likes', ['post_id' => $post_id])->num_rows();
	}

	function get_display_name($id) {
		return $this->db->select('display_name, profile_photo')->get_where('users', ['id' => $id])->row_array();
	}

	function check_hall_exist() {
		return $this->db->get_where('halls', ['hall_id' => $this->input->post('hall_id')])->num_rows();
	}

	function get_single_post($post_id) {
		return $this->db->get_where('posts', ['post_id' => $post_id])->row_array();
	}
}