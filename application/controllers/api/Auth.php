<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Auth extends RestController {
	function register_post() {
		$this->form_validation->set_rules('first_name', 'First Name', 'required|alpha_numeric_spaces|max_length[50]');
		$this->form_validation->set_rules('last_name', 'Last Name', 'required|alpha_numeric_spaces|max_length[50]');
		$this->form_validation->set_rules('display_name', 'Display Name', 'required|alpha_numeric|callback_unique_display_name|max_length[16]');
		$this->form_validation->set_rules('email', 'Email', 'required|callback_unique_email|valid_email|max_length[100]');
		$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|max_length[100]');
		$this->form_validation->set_rules('confirm_password', 'Confirm Password', 'matches[password]');
		$this->form_validation->set_rules('specialization', 'Specialization', 'required|max_length[100]');	
 
		if ($this->form_validation->run() == FALSE) {
			$this->response([
				'status' 	=> 'Error',
				'message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);		
		} else {
			$status = $this->Auth_model->register();

			$this->response([
				'status' => $status
			], ($status ? RestController::HTTP_CREATED : RestController::HTTP_INTERNAL_ERROR));
		}
	}

	//Can be used to resend email verification
	function login_post() {
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[100]');
		$this->form_validation->set_rules('password', 'Password', 'required|max_length[100]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'status' 	=> 'Error',
				'message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$users = $this->Auth_model->get_users();

			foreach ($users as $user) {
				if ($this->input->post('email') == $this->encryption->decrypt($user['email'])) 
					if (password_verify($this->input->post('password'), $user['password'])) {
						if ($user['sanction_exp'] > date("Y-m-d H:i:s", strtotime('+8 hours')) && $user['sanction_type'] == 'Suspend')
							$this->response([
								'status' => 'You are temporarily suspended and thus will be unable to access Critique Hall until '.date("M d, Y g:iA", strtotime($user['sanction_exp'])).'. If you wish to dispute this, you can contact us directly at critiquehall@gmail.com.'
							], RestController::HTTP_OK);
						else
							if (!$user['email_verified']) {
								$this->email_verification($this->encryption->decrypt($user['email']), $user['id']);

								$this->response([
									'status' 		=> 'Email not verified',
									'display_name'	=> $this->encryption->decrypt($user['display_name']),
									'encrypted_id'	=> $this->encryption->encrypt($user['id']),
									'verified'		=> $user['email_verified'],
									'profile_photo' => $this->encryption->decrypt($user['profile_photo'])
								], RestController::HTTP_OK);
							} else {
								$data = $this->Auth_model->login($user['id']);
								$this->Audit_log_model->audit_log($user['id'], null, 'Logged In');

								$this->response([
									'status' 		=> 'Access Granted',
									'display_name'	=> $this->encryption->decrypt($user['display_name']),
									'encrypted_id'	=> $this->encryption->encrypt($user['id']),
									'token'			=> $this->encryption->encrypt($data['token']),
									'token_exp' 	=> $data['token_exp'],
									'verified'		=> $user['email_verified'],
									'profile_photo' => $this->encryption->decrypt($user['profile_photo'])
								], RestController::HTTP_OK);
							}

						break 1;
					} else {
						$this->Audit_log_model->audit_log($user['id'], null, 'Logged In: Wrong credentials');

						$this->response([
							'status'	=> 'Unauthorized',
							'message'	=> 'Wrong credentials'
						], RestController::HTTP_UNAUTHORIZED);

						break 1;
					}
			}

			$this->response([
				'status'	=> 'Unauthorized',
				'message'	=> 'Wrong credentials'
			], RestController::HTTP_UNAUTHORIZED);
		}
	}

	//Email verification code input from user
	function confirm_verification_post() {
		$this->form_validation->set_rules('verification_code', 'Verification Code', 'required|numeric|max_length[6]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'status'	=> 'Error',
				'message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$decrypted_user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);
			$data 			   = $this->Auth_model->is_verified($decrypted_user_id);

			if (!$data['email_verified']) {
				$data = $this->Auth_model->confirm_verification($decrypted_user_id, $this->input->post('verification_code'));

				if ($data['status'] === true)
					$this->response([
						'status'  => $data['status'], 
						'token'   => $this->encryption->encrypt($data['token']),
						'id'      => $this->encryption->encrypt($data['id']),
						'message' => 'Email have been verified'
					], RestController::HTTP_CREATED);
				else
					$this->response(['status' => $data], RestController::HTTP_BAD_REQUEST);
			}

			$this->response(['message' => 'Already Verified'], RestController::HTTP_BAD_REQUEST);
		}

		$this->response([
			'status' => 'Error'
		], RestController::HTTP_UNAUTHORIZED);
	}

	//Send email verification
	private function email_verification($email, $user_id) {
		$verification_code = random_string('numeric', 6);

		$status = $this->Auth_model->email_verification($user_id, $verification_code);

		if ($status) {
			$this->load->library('email');

			$this->email->initialize($this->config->item('email_config'));

			$this->email->from('critiquehall@gmail.com', 'Critique Hall - Account Verification');
			$this->email->to($email);
			$this->email->subject('Verify Your Email');
			$this->email->message($this->load->view('email_verify', ['verification_code' => $verification_code], true));

			return $this->email->send();
		}
		
		return false;
	}

	//Request to send a reset password link to the user
	function forgot_password_post() {
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[200]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'status'	=> 'Error',
				'Message'	=> validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$users = $this->Auth_model->unique_email();

			foreach ($users as $user)
				if ($this->input->post('email') == $this->encryption->decrypt($user['email'])) {
					$data = $this->Auth_model->set_forgot_password($user['id']);

					if (!$data) {
						$this->response([
							'status' 	=> 'Error'
						], RestController::HTTP_INTERNAL_ERROR);
					} else {
						$hashed_token = password_hash($data['token'], PASSWORD_BCRYPT);

						$this->send_reset_password($user['id'], $this->encryption->decrypt($user['email']), $hashed_token, $data['token_exp']);

						$this->Audit_log_model->audit_log($user['id'], null, 'Asked for Password Reset Link');

						$this->response([
							'status' 	=> 'Success',
							'message'	=> 'Reset Password Sent'
						], RestController::HTTP_CREATED);
					}
				}

			$this->response([
				'status' => 'Wrong Credential'
			], RestController::HTTP_BAD_REQUEST);
		}
	}

	//Send reset password link
	private function send_reset_password($user_id, $email, $hashed_token, $token_exp) {
		$reset_pass_link = 'https://critiquehall.vercel.app/reset-password?token='.urlencode($hashed_token).'&user_id='.$user_id;

		$this->load->library('email');
		$this->email->initialize($this->config->item('email_config'));

		$this->email->from('critiquehall@gmail.com', 'Critique Hall - Reset Password');
		$this->email->to($email);
		$this->email->subject('Reset Password');
		$this->email->message($this->load->view('reset_password', ['reset_pass_link' => $reset_pass_link, 'token_exp' => date('Y-m-d H:i:s', strtotime('+8 hours', strtotime($token_exp)))], true));

		if ($this->email->send())
			return true;
		else
			return false;
	}

	function reset_password_post() {
		$this->form_validation->set_rules('user_id', '-', 'required|is_natural_no_zero');
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
			$user  = $this->Auth_model->get_forgot_password($this->input->post('user_id'));

			if (password_verify($user['token'] ,$this->input->post('token')) && $user['token_exp'] > date("Y-m-d H:i:s")) {
				$hashed_new_passsword = password_hash($this->input->post('new_password'), PASSWORD_BCRYPT);

				$status = $this->Auth_model->reset_password($hashed_new_passsword, $this->input->post('user_id'));

				if ($status) {
					$this->Audit_log_model->audit_log($this->input->post('user_id'), null, 'Password Reset Success');

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
					'message'	=> 'Token expired'
				], RestController::HTTP_BAD_REQUEST);
			}
		}
	}
//--------------------------------------------CUSTOM RULES-----------------------------------------------------------------
	function unique_email($email) {
		$data = $this->Auth_model->unique_email();

		foreach ($data as $user_data)
			if (strtolower($email) == strtolower($this->encryption->decrypt($user_data['email']))) {
				$this->form_validation->set_message('unique_email', 'Email is already used');
				return false;
			}

		return true;
	}

	function unique_display_name($display_name) {
		$data = $this->Auth_model->unique_display_name();

		foreach ($data as $user_data)
			if (strtolower($display_name) == strtolower($this->encryption->decrypt($user_data['display_name']))) {
				$this->form_validation->set_message('unique_display_name', 'Display Name is already used');
				return false;
			}

		return true;
	}
}