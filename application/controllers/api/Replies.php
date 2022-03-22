<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Replies extends RestController {
	function __construct() {
		parent::__construct();

		$is_legit_user = $this->Not_A_Model->legit_user();

		if ($is_legit_user !== true)
			$this->response([
				'error' => $is_legit_user
			], RestController::HTTP_UNAUTHORIZED);

		$this->user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);
	}

	function display_replies_post() {
		$this->form_validation->set_rules('critique_id', 'Critique Id', 'required|is_natural_no_zero');

		if ($this->form_validation->run() == false) {
			$this->response(['Error' => validation_errors()], RestController::HTTP_BAD_REQUEST);
		} else {
			$data 		  = $this->Replies_model->display_replies($this->input->post('critique_id'));
			$author_photo = $this->Replies_model->get_author_photo($this->input->post('critique_id'));
			$i 	  = 0;

			if ($data)
				foreach ($data as $reply) {
					$owner 							= $this->get_display_name($reply['user_id']);
					$data[$i]['display_name'] 		= $this->encryption->decrypt($owner['display_name']);
					$data[$i]['profile_photo'] 		= $this->encryption->decrypt($owner['profile_photo']);
					$data[$i]['reputation_points']	= $this->Replies_model->check_reputation_points($reply['user_id']);
					$data[$i]['is_edited']			= $this->Replies_model->is_edited($reply['reply_id']);
					$data[$i]['stars']				= $this->Replies_model->get_stars($reply['reply_id']);
					// $data[$i]['is_starred']			= $this->Replies_model->is_starred($reply['reply_id'], $this->user_id);
					$data[$i]['time_ago']			= $this->Not_A_Model->ago_time(strtotime($reply['created_at']));

					if ($reply['starred_by_author'] == 1)
						$data[$i]['author_photo']  = $author_photo;

					$i++;
				}

			$this->response([
				'data' => $data
			], (!is_null($data) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
		}
	}

	function create_replies_post() {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$this->form_validation->set_rules('critique_id', 'Critique Id', 'required|is_natural');
			$this->form_validation->set_rules('body', 'Body', 'required|max_length[3000]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status'	=> 'Error',
					'message'	=> validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Replies_model->create_reply($this->user_id);

				$this->response([
					'status' => $status
				], ($status ? RestController::HTTP_CREATED : RestController::HTTP_INTERNAL_ERROR));
			}
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function update_replies_post() {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$this->form_validation->set_rules('reply_id', 'Reply Id', 'required|is_natural');
			$this->form_validation->set_rules('body', 'Body', 'required|max_length[3000]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status'	=> 'Error',
					'Message'	=> validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Replies_model->update_reply();

				$this->response([
					'status' => $status
				], ($status ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
			}
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function delete_replies_delete($reply_id) {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$status = $this->Replies_model->delete_replies($reply_id, $this->user_id);

			$this->response([
				'status' => $status
			], ($status ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function star_replies_post() {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$this->form_validation->set_rules('reply_id', 'Reply Id', 'required|is_natural_no_zero');
			$this->form_validation->set_rules('post_id', 'Post Id', 'required|is_natural_no_zero');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status' => 'Error',
					'message' => validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Replies_model->star_reply($this->user_id);

				$this->response([
					'status' => $status['status'],
					'stars'	 => $status['stars']
				], ($status ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
			}
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function version_replies_get($reply_id) {
		$data = $this->Replies_model->get_version_replies($reply_id);
		$i 	  = 0;

		foreach ($data as $reply_ver) {
			$data[$i]['time_ago'] = $this->Not_A_Model->ago_time(strtotime($reply_ver['created_at']));
			$data[$i]['created_at'] = date("M d, Y g:iA", strtotime('+8 hours', strtotime($reply_ver['created_at'])));

			$i++;
		}

		$this->response([
			'data' => $data
		], (!is_null($data) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}

//---------------------------------CAN BE IMPROVED BY USING JOIN-----------------------------
	function get_display_name($id) {
		return $this->Posts_model->get_display_name($id);
	}
}