<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Static_data extends RestController {
	// function email_suggestions_post() {
	// 	$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[200]');
	// 	$this->form_validation->set_rules('fullname', 'Fullname', 'required|max_length[100]');
	// 	$this->form_validation->set_rules('subject', 'Subject', 'required|max_length[50]');
	// 	$this->form_validation->set_rules('message', 'Message', 'required|max_length[500]');

	// 	if ($this->form_validation->run() == false) {
	// 		$this->response([
	// 			'status'	=> 'Error',
	// 			'message'	=> validation_errors()
	// 		]);
	// 	} else {
	// 		$status = $this->email_suggestion($this->input->post('email'), $this->input->post('fullname'), $this->input->post('subject'), $this->input->post('message'));

	// 		$this->response([
	// 			'status' => $status
	// 		], ($status ? RestController::HTTP_CREATED : RestController::HTTP_BAD_REQUEST));
	// 	}
	// }

	// private function email_suggestion($email, $fullname, $subject, $message) {
	// 	$this->load->library('email');    
	// 	$this->email->initialize($this->config->item('email_config'));

	// 	$data = [
	// 		'fullname' => $fullname,
	// 		'message'  => $message,
	// 		'email'	   => $email
	// 	];

	// 	$this->email->from('critiquehall@gmail.com', 'Critique Hall - Feedback Suggestion');
	// 	$this->email->to('critiquehall@gmail.com');
	// 	$this->email->subject($subject);
	// 	$this->email->message($this->load->view('email_suggestion', $data, true));

	// 	return $this->email->send();
	// }

	function posts_per_hall_get() {
		if ($this->user_auth() == true) {
			$halls = $this->Static_model->get_halls();
			$i 	   = 0;

			foreach ($halls as $hall) {
				$halls[$i]['posts'] = $this->Static_model->posts_hall($hall['hall_id']);
				$i++;
			}

			$this->response([
				'halls' => $halls
			], (!is_null($halls) ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
		} else {
			$this->response([
				'error' => 'Unauthorized'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function get_departments_get() {
		$depts = $this->Static_model->get_departments();

		$this->response([
			'Departments' => $depts
		]);
	}

	function get_dept_special_post() {
		if ($this->dept_exists($this->input->post('dept'))) {
			$data = $this->Static_model->get_specializations($this->input->post('dept'));
		
			$this->response([
				'specializations' => $data
			], RestController::HTTP_OK);
		}
	}

	//This function will give posts and users based on the search data
	function search_post() {
		if ($this->user_auth() == true) {
			if(!is_null($this->input->post('search_data'))) {
				$user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);

				$sort = strtoupper($this->input->post('sort'));
				if ($sort == 'DESC' || $sort == 'ASC')
					$sort = $this->input->post('sort');
				else
					$sort = 'DESC';

				$temp['users'] = $this->Static_model->search_users();
				$i = 0;

				$search_data = strtolower($this->input->post('search_data'));

				foreach ($temp['users'] as $user) {
					if ((strpos(strtolower($this->encryption->decrypt($user['first_name'])), $search_data) !== false ||
						strpos(strtolower($this->encryption->decrypt($user['last_name'])), $search_data) !== false ||
						strpos(strtolower($this->encryption->decrypt($user['display_name'])), $search_data) !== false)&&
						$user['email_verified']==1
						)
					{
						$data['users'][$i]['id']		    	= $user['id'];
						$data['users'][$i]['first_name']    	= $this->encryption->decrypt($user['first_name']);
						$data['users'][$i]['last_name']     	= $this->encryption->decrypt($user['last_name']);
						$data['users'][$i]['display_name']  	= $this->encryption->decrypt($user['display_name']);
						$data['users'][$i]['profile_photo'] 	= $this->encryption->decrypt($user['profile_photo']);
						$data['users'][$i]['cover_photo'] 		= $this->encryption->decrypt($user['cover_photo']);
						$data['users'][$i]['reputation_points'] = $user['reputation_points'];

						$i++;
					}
				}

				if(!isset($data['users']))
					$data['users'] = [];

				$id_box = [];
				if(!is_null($data['users']))
					foreach($data['users'] as $user)
						array_push($id_box, $user['id']);

				$data['posts'] = $this->Static_model->search_posts($sort, $id_box);
				$i = 0;

				foreach ($data['posts'] as $post) {
					$owner 							    = $this->get_display_name($post['user_id']);
					$data['posts'][$i]['profile_photo'] = $this->encryption->decrypt($owner['profile_photo']);
					$data['posts'][$i]['display_name']  = $this->encryption->decrypt($owner['display_name']);
					// $data['posts'][$i]['likes']		    = $this->Posts_model->get_likes($post['post_id']);
					// $data['posts'][$i]['is_liked']	    = $this->Posts_model->is_liked($post['post_id'], $user_id);
					$data['posts'][$i]['time_ago'] 	    = $this->Not_A_Model->ago_time(strtotime($post['created_at']));
					$hall_data				   	        = $this->Profile_model->get_hall($post['hall_id']);
					$data['posts'][$i]['hall_name']     = $hall_data['hall_name'];
					$data['posts'][$i]['hall_color']    = $hall_data['color'];
					
					$i++;
				}

				$this->response([
					'data' => $data
				], (!is_null($data) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
			}

			$this->response([
				'data' => $data
			], ($data ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
		} else {
			$this->response([
				'error' => 'Unauthorized'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function get_halls_get() {
		if ($this->user_auth() == true)
			$this->response([
				'halls' => $this->Static_model->get_halls()
			], RestController::HTTP_OK);
		else
			$this->response([
				'error' => 'Unauthorized'
			], RestController::HTTP_UNAUTHORIZED);
	}

//Custom Rules
	function dept_exists($dept) {
		return $this->Static_model->dept_exists($dept);
	}

	private function get_display_name($id) {
		return $this->Posts_model->get_display_name($id);
	}

	private function user_auth() {
		$is_legit_user = $this->Not_A_Model->legit_user();

		if ($is_legit_user !== true)
			return false;

		return true;
	}
}