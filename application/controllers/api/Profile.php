<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Profile extends RestController {
	function __construct() {
		parent::__construct();

		$is_legit_user = $this->Not_A_Model->legit_user();

		if ($is_legit_user !== true)
			$this->response([
				'error' => $is_legit_user
			], RestController::HTTP_UNAUTHORIZED);

		$this->user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);
	}

	function display_profile_get($display_name) {
		$user = $this->Profile_model->display_user($display_name);

		if (!is_null($user)) {
			$profile['user']['encrypted_id'] = $this->encryption->encrypt($user['id']);

			//Decrypt user data
			foreach ($user as $data => $value) {
				if ($data != 'id' && $data != 'reputation_points' && $value != null)
					$profile['user'][$data] = $this->encryption->decrypt($value);
				elseif ($data != 'id')
					$profile['user'][$data] = $value;
			}
		}

		$this->response([
			'data' => (isset($profile) ? $profile : null)
		], (isset($profile) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}

	function display_user_posts_post() {
		$this->form_validation->set_rules('user_id', 'User Id', 'required|trim');

		if ($this->form_validation->run() == false) {
			$this->response(['Error' => validation_errors()], RestController::HTTP_BAD_REQUEST);
		} else {
			$sort = strtolower($this->input->post('sort'));
			if ($sort == 'desc' || $sort == 'asc')
				$sort = $this->input->post('sort');
			else
				$sort = 'DESC';

			$posts_data = $this->Profile_model->display_user_posts($sort);
			

			if($posts_data !== []) {

				$i = 0;
				foreach($posts_data as $data) {
					$posts[$i]['post_id'] 	  = $data['post_id'];
					$hall_data				  = $this->Profile_model->get_hall($data['hall_id']);
					$posts[$i]['hall'] 	 	  = $hall_data['hall_name'];
					$posts[$i]['hall_color']  = $hall_data['color'];
					$posts[$i]['body'] 	 	  = $data['body'];
					$posts[$i]['attachment1'] = $data['attachment1'];
					$posts[$i]['attachment2'] = $data['attachment2'];
					$posts[$i]['attachment3'] = $data['attachment3'];
					$posts[$i]['created_at']  = $data['created_at'];
					$posts[$i]['updated_at']  = $data['updated_at'];
					$posts[$i]['likes'] 	  = $this->Profile_model->num_likes($data['post_id']);
					$posts[$i]['critiques']   = $this->Profile_model->num_critiques($data['post_id']);
					$posts[$i]['time_ago'] 	  = $this->Not_A_Model->ago_time(strtotime($data['created_at']));

					$i++;
				}

				if (strtolower($this->input->post('sort') == 'most_stars'))
					array_multisort(array_column($posts, 'likes'), SORT_DESC, $posts);
				elseif (strtolower($this->input->post('sort') == 'most_interacted'))
					array_multisort(array_column($posts, 'critiques'), SORT_DESC, $posts);

				$this->response([
					'posts' => $posts
				], RestController::HTTP_OK);
			} else {
				$this->response([
					'posts' => null
				], RestController::HTTP_OK);
			}
		}
	}

	function display_user_critiques_post() {
		$this->form_validation->set_rules('user_id', 'User Id', 'required|trim');

		if ($this->form_validation->run() == false) {
			$this->response(['Error' => validation_errors()], RestController::HTTP_BAD_REQUEST);
		} else {
			$sort = strtolower($this->input->post('sort'));
			if ($sort == 'desc' || $sort == 'asc')
				$sort = $this->input->post('sort');
			else
				$sort = 'DESC';

			$critiques_data = $this->Profile_model->display_user_critiques($sort);
			

			if($critiques_data !== []) {
				$i = 0;
				foreach($critiques_data as $data) {
					$post_data = $this->Profile_model->get_single_post($data['post_id']);

					if ($post_data['is_deleted'] == 0) {
						$critiques[$i]['critique_id']	= $data['critique_id'];
						$critiques[$i]['critique'] 	 	= $data['body'];
						$critiques[$i]['stars'] 		= $this->Profile_model->num_stars($data['critique_id']);
						$critiques[$i]['replies'] 		= $this->Profile_model->num_replies($data['critique_id']);
						$critiques[$i]['time_ago'] 		= $this->Not_A_Model->ago_time(strtotime($data['created_at']));

						$critiques[$i]['post_id'] 	 	= $post_data['post_id'];
						$hall_data				  		= $this->Profile_model->get_hall($post_data['hall_id']);
						$critiques[$i]['hall'] 	 	  	= $hall_data['hall_name'];
						$critiques[$i]['hall_color']  	= $hall_data['color'];
						$critiques[$i]['attachment1'] 	= $post_data['attachment1'];
						$critiques[$i]['created_at']  	= $post_data['created_at'];
						$critiques[$i]['updated_at']  	= $post_data['updated_at'];
						$critiques[$i]['time_ago_post'] = $this->Not_A_Model->ago_time(strtotime($post_data['created_at']));

						$i++;
					}
				}

				if (strtolower($this->input->post('sort') == 'most_stars'))
					array_multisort(array_column($critiques, 'stars'), SORT_DESC, $critiques);
				elseif (strtolower($this->input->post('sort') == 'most_interacted'))
					array_multisort(array_column($critiques, 'replies'), SORT_DESC, $critiques);

				$this->response([
					'critiques' => $critiques
				], RestController::HTTP_OK);
			} else {
				$this->response([
					'critiques' => null
				], RestController::HTTP_OK);
			}
		}
	}

	function change_profile_post() {
		$this->form_validation->set_rules('profile_photo', 'Profile Photo', 'required|callback_valid_file|max_length[500]');
		$this->form_validation->set_rules('cover_photo', 'Cover Photo', 'required|callback_valid_file|max_length[500]');
		$this->form_validation->set_rules('first_name', 'First Name', 'required|callback_no_changes|alpha_numeric_spaces|max_length[255]');
		$this->form_validation->set_rules('last_name', 'Last Name', 'required|alpha_numeric_spaces|max_length[255]');
		$this->form_validation->set_rules('display_name', 'Display Name', 'required|alpha_numeric|callback_unique_display_name|max_length[16]');
		$this->form_validation->set_rules('about_me', 'About Me', 'max_length[255]');
		$this->form_validation->set_rules('specialization', 'Specialization', 'required|max_length[100]');
		$this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|max_length[255]');

		if($this->form_validation->run() == false) {
			$this->response([
				'status' 	=> 'Error',
				'message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$user_pass = $this->Profile_model->get_user_data($this->user_id);

			if (password_verify($this->input->post('confirm_password'), $user_pass['password'])) {
				$status = $this->Profile_model->change_profile($this->user_id);
			
				$this->response([
					'status' => $status
				], ($status ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
			}

			$this->response([
				'Error' => 'Wrong password'
			], RestController::HTTP_BAD_REQUEST);
		}
	}

	function change_password_post() {
		$this->form_validation->set_rules('current_password', 'Current Password', 'required|max_length[255]');
		$this->form_validation->set_rules('new_password', 'Confirm Password', 'required|callback_new_pass|max_length[255]');
		$this->form_validation->set_rules('confirm_new_password', 'Confirm New Password', 'matches[new_password]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'status' 	=> 'Error',
				'message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$decrypted_user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);

			$user_pass = $this->Profile_model->get_user_data($decrypted_user_id);

			if (password_verify($this->input->post('current_password'), $user_pass['password'])) {
				$status = $this->Profile_model->change_pass($decrypted_user_id);
			
				$this->response([
					'status' => $status
				], ($status ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
			}

			$this->response([
				'Error' => 'Wrong password'
			], RestController::HTTP_BAD_REQUEST);
		}
	}

	function get_notifs_get() {
		$data = $this->Profile_model->get_notifs($this->user_id);
		$i 	  = 0;

		$your = '';
		foreach ($data as $notif) {
			if (is_numeric($notif['post_id'])) {
				$post_info 			   = $this->Profile_model->get_single_post($notif['post_id']);
				$data[$i]['title']	   = $post_info['title'];
				$your 				   = ' your post';
			}

			if (is_numeric($notif['critique_id'])) {
				$critique_info 		     = $this->Profile_model->get_single_critique($notif['critique_id']);
				$data[$i]['description'] = $critique_info['body'];
				$your 				     = ' your critique';
			}

			if (is_numeric($notif['reply_id'])) {
				$reply_info 		     = $this->Profile_model->get_single_reply($notif['reply_id']);
				$data[$i]['description'] = $reply_info['body'];
				$your 				     = ' your reply';
			}

			if (!is_null($notif['user_id'])) {
				$user_info 				   = $this->Profile_model->get_user_info($notif['user_id']);
				$data[$i]['profile_photo'] = $this->encryption->decrypt($user_info['profile_photo']);
				$data[$i]['display_name']  = $this->encryption->decrypt($user_info['display_name']);
			}

			$data[$i]['ago_time']		   = $this->Not_A_Model->ago_time(strtotime($notif['created_at']))	;
			$data[$i]['action']			   = $this->encryption->decrypt($notif['action']).$your;

			$i++;
		}

		$this->response([
			'status' => $data
		], RestController::HTTP_OK);
	}

	function read_notifs_get($first_id) {
		$this->response([
			'status' => $this->Profile_model->read_notifs($first_id, $this->user_id)
		]);
	}

	function logout_post() {
		$status = $this->Auth_model->logout();

		$this->response([
			'status' => $status
		], ($status ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}
//-------------------------------------------------CUSTOM RULES----------------------------------------------
	function new_pass($new_password) {
		if ($new_password != $this->input->post('current_password'))
			return true;

		$this->form_validation->set_message('new_pass', 'New password was already used');
		return false;
	}

	function no_changes($first_name) {
		if($this->Profile_model->check_password($this->user_id)) {
			$user_data = $this->Profile_model->get_user_info($this->user_id);
		
			if ($this->input->post('profile_photo')  == $this->encryption->decrypt($user_data['profile_photo']) &&
				$this->input->post('cover_photo') 	 == $this->encryption->decrypt($user_data['cover_photo']) &&
				$first_name 						 == strtolower($this->encryption->decrypt($user_data['first_name'])) &&
				$this->input->post('last_name') 	 == strtolower($this->encryption->decrypt($user_data['last_name'])) &&
				$this->input->post('display_name') 	 == strtolower($this->encryption->decrypt($user_data['display_name'])) &&
				$this->input->post('specialization') == strtolower($this->encryption->decrypt($user_data['specialization'])) &&
				$this->input->post('about_me') 		 == $this->encryption->decrypt($user_data['about_me']))
			{
				$this->form_validation->set_message('no_changes', 'No changes made');
				return false;
			}
		} else {
			$this->form_validation->set_message('no_changes', 'Wrong password');
			return false;
		}

		return true;
	}

	function unique_display_name($display_name) {
		$data = $this->Auth_model->unique_display_name();

		foreach ($data as $user_data)
			if (strtolower($display_name) == strtolower($this->encryption->decrypt($user_data['display_name']))) {
				if($this->user_id != $user_data['id']) {
					$this->form_validation->set_message('unique_display_name', 'Display Name is already used');
					return false;
				}
			}

		return true;
	}

	function valid_file($file) {
		$match = strpos($file, 'https://firebasestorage.googleapis.com/v0/b/critique-hall.appspot.com/');
	
		if ($match !== false)
			return true;

		$this->form_validation->set_message('valid_file', 'Invalid file');
		return false;
	}
}