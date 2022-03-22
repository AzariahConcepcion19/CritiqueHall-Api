<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Admin_auth extends RestController {
	function login_post() {
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[100]');
		$this->form_validation->set_rules('password', 'Password', 'required|max_length[100]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'status' 	=> 'Error',
				'message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$admins = $this->Admins_model->get_admins();

			if (!is_null($admins))
				foreach ($admins as $admin)
					if ($this->encryption->decrypt($admin['email']) == $this->input->post('email'))
						if (password_verify($this->input->post('password'), $admin['password'])) {
							if ($admin['is_disabled'] == 1)
								$status = 'Account Disabled';
							else
								$status = $this->Not_A_Model->send_otp(null, null, $this->input->post('email'), $admin['admin_id']);

							break 1;
						} else {
							$status = "Wrong credentials";
						}
					else
						$status = "Wrong credentials";

			if($status === null || !isset($status) || $status === 'Account Disabled' || $status === false || $status === "Wrong credentials") {
				$encrypted_id = '';
				$request 	  = RestController::HTTP_BAD_REQUEST;
				$email 		  = '';
			} else {
				$encrypted_id = $this->encryption->encrypt($admin['admin_id']);
				$request 	  = RestController::HTTP_OK;
				$email 		  = $this->input->post('email');
			}

			$this->response([
				'status' 		=> $status,
				'encrypted_id'	=> $encrypted_id,
				'email'			=> $email
			], $request);
		}
	}

	function confirm_otp_post() {
		$this->form_validation->set_rules('encrypted_id', 'Encrypted Id', 'required|max_length[500]');
		$this->form_validation->set_rules('otp', 'Otp', 'required|numeric|max_length[6]');

		if($this->form_validation->run() == false) {
			$this->response([
				'status' 	=> 'Errors',
				'message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$decrypted_admin_id = $this->encryption->decrypt($this->input->post('encrypted_id'));

			$data = $this->Admins_model->confirm_otp($decrypted_admin_id, $this->input->post('otp'));
		
			if (isset($data['token']))
				$this->response([
					'status' 		 => 'Access Granted',
					'id'			 => $decrypted_admin_id,
					'token'			 => $this->encryption->encrypt($data['token']),
					'is_super_admin' => $data['is_super_admin'],
					'first_name'	 => $data['first_name'],
					'last_name'		 => $data['last_name']
				], RestController::HTTP_OK);
			elseif ($data == 'Wrong otp' || $data == 'Otp expired')
				$this->response([
					'status' 		=> 'Error',
					'message'		=> $data
				], RestController::HTTP_BAD_REQUEST);
			else
				$this->response([
					'status' 		=> $data
				], RestController::HTTP_INTERNAL_ERROR);
		}
	}

	//Request to send a reset password link to the admin
	function forgot_password_post() {
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[200]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'status'	=> 'Error',
				'Message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$admins = $this->Admins_model->get_admins();

			foreach ($admins as $admin)
				if ($this->input->post('email') == $this->encryption->decrypt($admin['email'])) {
					$data = $this->Admins_model->set_forgot_password($admin['admin_id']);

					if (!$data) {
						$this->response([
							'status' 	=> 'Error'
						], RestController::HTTP_INTERNAL_ERROR);
					} else {
						$hashed_token = password_hash($data['token'], PASSWORD_BCRYPT);

						$this->send_reset_password($admin['admin_id'], $this->encryption->decrypt($admin['email']), $hashed_token, $data['token_exp']);

						$this->Audit_log_model->audit_log(null, $admin['admin_id'], 'Asked for Password Reset Link');

						$this->response([
							'status' 	=> 'Success',
							'message'	=> 'Reset Password Sent'
						], RestController::HTTP_CREATED);
					}
				}

			$this->response([
				'Message' => 'Wrong Credential'
			], RestController::HTTP_BAD_REQUEST);
		}
	}

	//Send reset password link
	private function send_reset_password($admin_id, $email, $hashed_token, $token_exp) {
		$reset_pass_link = 'https://critiquehalladmin.herokuapp.com?token='.urlencode($hashed_token).'&admin_id='.$admin_id;

		$this->load->library('email');
		$this->email->initialize($this->config->item('email_config'));

		$this->email->from('critiquehall@gmail.com', 'Critique Hall Admin - Reset Password');
		$this->email->to($email);
		$this->email->subject('Reset Password');
		$this->email->message($this->load->view('reset_password', ['reset_pass_link' => $reset_pass_link, 'token_exp' => date('Y-m-d H:i:s', strtotime('+8 hours', strtotime($token_exp)))], true));

		if ($this->email->send())
			return true;
		
		return false;
	}

	function reset_password_post() {
		$this->form_validation->set_rules('admin_id', '-', 'required|is_natural_no_zero');
		$this->form_validation->set_rules('token', '-', 'required|max_length[500]');
		$this->form_validation->set_rules('new_password', 'New Password', 'required|min_length[8]|max_length[100]');
		$this->form_validation->set_rules('confirm_new_password', 'Confirm New Password', 'matches[new_password]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'status'	=> 'Error',
				'message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$token = urldecode($this->input->post('token'));		
			$admin = $this->Admins_model->get_forgot_password($this->input->post('admin_id'));

			if (password_verify($admin['token'], $token) && $admin['token_exp'] > date('Y-m-d H:i:s')) {
				$hashed_new_passsword = password_hash($this->input->post('new_password'), PASSWORD_BCRYPT);

				$status = $this->Admins_model->reset_password($hashed_new_passsword, $this->input->post('admin_id'));

				if ($status) {
					$this->Audit_log_model->audit_log(null, $this->input->post('admin_id'), 'Password Reset Success');

					$this->response([
						'status'	=> 'Success',
						'message'	=> 'Password reset success'
					], RestController::HTTP_OK);
				}

				$this->response([
					'status'	=> 'Error',
					'message'	=> 'Error'
				], RestController::HTTP_INTERNAL_ERROR);
			} else {
				$this->response([
					'status'	=> 'Error',
					'message'	=> 'Reset password expired'
				], RestController::HTTP_BAD_REQUEST);
			}
		}
	}
}