<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Critiques extends RestController {
	function __construct() {
		parent::__construct();

		$is_legit_user = $this->Not_A_Model->legit_user();

		if ($is_legit_user !== true)
			$this->response([
				'error' => $is_legit_user
			], RestController::HTTP_UNAUTHORIZED);

		$this->user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);
	}

	function display_critiques_post() {
		$this->form_validation->set_rules('post_id', 'Post Id', 'required|is_natural_no_zero');

		if ($this->form_validation->run() == false) {
			$this->response(['Error' => validation_errors()], RestController::HTTP_BAD_REQUEST);
		} else {
			$sort = strtoupper($this->input->post('sort'));
			if ($sort == 'DESC' || $sort == 'ASC')
				$sort = $this->input->post('sort');
			else
				$sort = 'DESC';

			$data 		  = $this->Critiques_model->display_critiques($this->input->post('post_id'), $sort);
			$author_photo = $this->Critiques_model->get_author_photo($this->input->post('post_id'));
			$i 	  = 0;

			if ($data) {
				foreach ($data as $critique) {
					$author 					   = $this->get_display_name($critique['user_id']);
					$data[$i]['display_name'] 	   = $this->encryption->decrypt($author['display_name']);
					$data[$i]['profile_photo'] 	   = $this->encryption->decrypt($author['profile_photo']);

					$data[$i]['reputation_points'] = $this->Critiques_model->check_reputation_points($critique['user_id']);
					$data[$i]['stars']			   = $this->Critiques_model->get_stars($critique['critique_id']);
					// $data[$i]['replies'] 		   = $this->Profile_model->num_replies($critique['critique_id']);
					// $data[$i]['is_starred']		   = $this->Critiques_model->is_starred($critique['critique_id'], $this->user_id);
					$data[$i]['is_edited']		   = $this->Critiques_model->is_edited($critique['critique_id']);
					$data[$i]['time_ago']		   = $this->Not_A_Model->ago_time(strtotime($critique['created_at']));

					if ($critique['starred_by_author'] == 1)
						$data[$i]['author_photo']  = $author_photo;

					$i++;
				}

				if (strtolower($this->input->post('sort') == 'most_stars'))
					array_multisort(array_column($data, 'stars'), SORT_DESC, $data);
				elseif (strtolower($this->input->post('sort') == 'most_interacted'))
					array_multisort(array_column($data, 'replies'), SORT_DESC, $data);
			}

			$this->response([
				'data' => $data
			], (!is_null($data) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
		}
	}

	function create_critiques_post() {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$this->form_validation->set_rules('post_id', 'Post Id', 'required|is_natural_no_zero');
			$this->form_validation->set_rules('body', 'Body', 'required|max_length[3000]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status'	=> 'Error',
					'message'	=> validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Critiques_model->create_critique($this->user_id);

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

	function update_critiques_post() {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$this->form_validation->set_rules('critique_id', 'Critique Id', 'required|is_natural_no_zero');
			$this->form_validation->set_rules('body', 'Body', 'required|max_length[3000]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status'	=> 'Error',
					'Message'	=> validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Critiques_model->update_critique($this->user_id);

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

	function delete_critiques_delete($critique_id) {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$status = $this->Critiques_model->delete_critique($critique_id, $this->user_id);

			$this->response([
				'status' => $status
			], ($status ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function star_critique_post() {
		if ($this->Not_A_Model->is_muted($this->user_id) == false) {
			$this->form_validation->set_rules('critique_id', 'Critique Id', 'required|max_length[255]');
			$this->form_validation->set_rules('post_id', 'Post Id', 'required|max_length[255]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status' => 'Error',
					'message' => validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Critiques_model->star_critique($this->user_id);

				$this->response([
					'status' => $status['status'],
					'stars'	 => $status['stars']
				], ($status['status'] ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
			}
		} else {
			$this->response([
				'status' => 'Account Muted'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	function version_critiques_get($critique_id) {
		$data = $this->Critiques_model->get_version_critiques($critique_id);
		$i 	  = 0;

		foreach ($data as $critique_ver) {
			$data[$i]['time_ago'] = $this->Not_A_Model->ago_time(strtotime($critique_ver['created_at']));
			$data[$i]['created_at'] = date("M d, Y g:iA", strtotime('+8 hours', strtotime($critique_ver['created_at'])));

			$i++;
		}

		$this->response([
			'data' => $data
		], (!is_null($data) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}

//---------------------------------CAN BE IMPROVED BY USING JOIN-----------------------------
	function get_display_name($id) {
		$data = $this->Posts_model->get_display_name($id);
		return $data;
	}
}