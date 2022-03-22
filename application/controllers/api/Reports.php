<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Reports extends RestController {
	function __construct() {
		parent::__construct();

		$is_legit_user = $this->Not_A_Model->legit_user();

		if ($is_legit_user !== true)
			$this->response([
				'error' => $is_legit_user
			], RestController::HTTP_UNAUTHORIZED);

		$this->user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);
	}

	function submit_report_post () {
		$this->form_validation->set_rules('user_id', 'User Id', 'callback_self_report');
		$this->form_validation->set_rules('post_id', 'Post Id', 'is_natural_no_zero');
		$this->form_validation->set_rules('critique_id', 'Critique Id', 'is_natural_no_zero');
		$this->form_validation->set_rules('reply_id', 'Reply Id', 'is_natural_no_zero');
		$this->form_validation->set_rules('message', 'Message', 'required|max_length[3000]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'status' 	=> 'Error',
				'message'	=> validation_errors()
			]);
		} else {
			if (!empty($this->input->post('user_id')) && empty($this->input->post('post_id')) && empty($this->input->post('critique_id')) && empty($this->input->post('reply_id'))) 
			{
				$user_id = $this->encryption->decrypt($this->input->post('user_id'));

				$status = $this->Report_model->does_user_exist($user_id);

				if ($status)
					$status = $this->Report_model->submit_report($this->user_id, $user_id, 'user_id');
			} 
			elseif (empty($this->input->post('user_id')) && !empty($this->input->post('post_id')) && empty($this->input->post('critique_id')) && empty($this->input->post('reply_id')))
			{
				$status = $this->Report_model->does_post_exist($this->input->post('post_id'));

				if ($status)
					$status = $this->Report_model->submit_report($this->user_id, $this->input->post('post_id'), 'post_id');
			}
			elseif (empty($this->input->post('user_id')) && empty($this->input->post('post_id')) && !empty($this->input->post('critique_id')) && empty($this->input->post('reply_id')))
			{
				$status = $this->Report_model->does_critique_exist($this->input->post('critique_id'));

				if ($status)
					$status = $this->Report_model->submit_report($this->user_id, $this->input->post('critique_id'), 'critique_id');
			}
			elseif (empty($this->input->post('user_id')) && empty($this->input->post('post_id')) && empty($this->input->post('critique_id')) && !empty($this->input->post('reply_id')))
			{
				$status = $this->Report_model->does_reply_exist($this->input->post('reply_id'));

				if ($status)
					$status = $this->Report_model->submit_report($this->user_id, $this->input->post('reply_id'), 'reply_id');
			} else {
				$status = 'Reporting null';
			}

			$this->response([
				'status' 	=> $status['status'],
				'report_id'	=> ($status['status'] ? $status['report_id'] : '')
			], ($status['status'] ? RestController::HTTP_CREATED : RestController::HTTP_BAD_REQUEST));
		}
	}

//-------------------------------------------------CUSTOM RULES----------------------------------------------
	function self_report($user_id) {
		if ($user_id === null)
			return true;
		if ($this->encryption->decrypt($user_id) != $this->user_id)
			return true;

		$this->form_validation->set_message('self_report', 'Self Report is not allowed');
		return false;
	}
}