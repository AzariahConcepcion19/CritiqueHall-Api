<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Posts extends RestController {
	function __construct() {
		parent::__construct();

		$is_legit_user = $this->Not_A_Model->legit_user();

		if ($is_legit_user !== true)
			$this->response([
				'error' => $is_legit_user
			], RestController::HTTP_UNAUTHORIZED);

		$this->user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);
	}
	
	function posts_pagination_get($hall_id) {
		$posts = $this->Posts_model->posts_pagination($hall_id);
		$i 	   = 0;

		foreach ($posts as $post) {
			$user_data 				    = $this->get_display_name($post['user_id']);
			$posts[$i]['display_name']  = $this->encryption->decrypt($user_data['display_name']);
			$posts[$i]['profile_photo'] = $this->encryption->decrypt($user_data['profile_photo']);
			// $posts[$i]['likes']		    = $this->Posts_model->get_likes($post['post_id']);
			// $posts[$i]['is_liked']	    = $this->Posts_model->is_liked($post['post_id'], $this->user_id);
			// $posts[$i]['is_edited']     = $this->Posts_model->is_edited($post['post_id']);
			$posts[$i]['time_ago']	    = $this->Not_A_Model->ago_time(strtotime($post['created_at']));
			$hall_data				    = $this->Profile_model->get_hall($post['hall_id']);
			$posts[$i]['hall'] 	 	    = $hall_data['hall_name'];
			$posts[$i]['hall_color']    = $hall_data['color'];

			$i++;
		}

		$this->response([
			'posts' => $posts
		], (!is_null($posts) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}

	function display_post_get($post_id) {
		$post = $this->Posts_model->get_post($post_id);

		if (!is_null($post)) {
			$user_data 			   = $this->get_display_name($post['user_id']);
			$post['display_name']  = $this->encryption->decrypt($user_data['display_name']);
			$post['profile_photo'] = $this->encryption->decrypt($user_data['profile_photo']);
			$post['likes']		   = $this->Posts_model->get_likes($post['post_id']);
			// $post['is_liked']	   = $this->Posts_model->is_liked($post['post_id'], $this->user_id);
			$post['time_ago']	   = $this->Not_A_Model->ago_time(strtotime($post['created_at']));
			$post['is_edited']     = $this->Posts_model->is_edited($post['post_id']);
			// $hall_data			   = $this->Profile_model->get_hall($post['hall_id']);
			// $posts['hall'] 	 	   = $hall_data['hall_name'];
			// $posts['hall_color']   = $hall_data['color'];
		}

		$this->response([
			'post' => $post
		], (!is_null($post) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}

	function create_posts_post() {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$this->form_validation->set_rules('hall_id', 'Hall Id', 'required|is_natural_no_zero');
			$this->form_validation->set_rules('title', 'Title', 'required|max_length[50]');
			$this->form_validation->set_rules('body', 'Body', 'required|max_length[5000]');
			$this->form_validation->set_rules('attachment1', 'Attachment 1', 'callback_is_valid_url');
			$this->form_validation->set_rules('attachment2', 'Attachment 2', 'callback_is_valid_url');
			$this->form_validation->set_rules('attachment3', 'Attachment 3', 'callback_is_valid_url');
			$this->form_validation->set_rules('attachment4', 'Attachment 4', 'callback_is_valid_url');
			$this->form_validation->set_rules('attachment5', 'Attachment 5', 'callback_is_valid_url');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status' 	=> 'Error',
					'message'	=> validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Posts_model->create_post();

				$this->response([
					'status'	=> $status
				], ($status ? RestController::HTTP_CREATED : RestController::HTTP_INTERNAL_ERROR));
			}
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function update_posts_post() {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$this->form_validation->set_rules('post_id', 'Post Id', 'required|is_natural_no_zero');
			$this->form_validation->set_rules('title', 'Title', 'required|max_length[50]');
			$this->form_validation->set_rules('body', 'Body', 'required|max_length[5000]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status'	=> 'Error',
					'message'	=> validation_errors()
				]);
			} else {
				$post_id = $this->input->post('post_id');

				$status = $this->Posts_model->update_post($post_id, $this->user_id);

				if ($status)
					$this->Audit_log_model->audit_log($this->user_id, null, 'Updated post id#'.$post_id);

				$this->response([
					'status' => $status
				], ($status ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
			}
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function delete_posts_delete($post_id) {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$status = $this->Posts_model->delete_post($post_id, $this->user_id);

			if ($status)
				$this->Audit_log_model->audit_log($this->user_id, null, 'Deleted post id#'.$post_id);

			$this->response([
				'status' => $status
			], ($status ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	//Like and unlike a post
	function like_posts_post($post_id) {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$status = $this->Posts_model->like_post($post_id, $this->user_id);

			if($status)
				$stars = $this->Posts_model->get_likes($post_id);

			$this->response([
				'status'	=> $status,
				'stars'		=> (isset($stars) ? $stars : null)
			], ($status ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function version_posts_get($post_id) {
		$data = $this->Posts_model->get_version_posts($post_id);
		$i 	  = 0;

		foreach ($data as $post_ver) {
			$data[$i]['time_ago'] = $this->Not_A_Model->ago_time(strtotime($post_ver['created_at']));
			$data[$i]['created_at'] = date("M d, Y g:iA", strtotime('+8 hours', strtotime($post_ver['created_at'])));

			$i++;
		}

		$this->response([
			'data' => $data
		], (!is_null($data) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}

	private function get_display_name($id) {
		$data = $this->Posts_model->get_display_name($id);
		return $data;
	}

	function is_valid_url($url) {
		if ($url == 'undefined')
			return true;
		
		$pos = strpos($url, 'https://firebasestorage.googleapis.com/v0/b/critique-hall.appspot.com/');
		if ($pos !== false)
			return true;
		
		$this->form_validation->set_message('is_valid_url', 'Invalid upload');
		return false;
	}
}