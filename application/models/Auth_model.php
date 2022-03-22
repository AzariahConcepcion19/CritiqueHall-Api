<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Auth_model extends CI_Model {
	function __construct() {
		$this->load->database();
	}

	function register() {
		$token 		= sha1(microtime(true).mt_rand(10000,90000));
		$token_exp 	= date("Y-m-d H:i:s", strtotime('+1 week'));

		$profile_photo = 'https://firebasestorage.googleapis.com/v0/b/critique-hall.appspot.com/o/default_profile_pics%2Fdefault-male-profile-picture.png?alt=media&token=e223fe03-63b9-4783-864a-6cba1a2409e9';

		$cover_photo = 'https://firebasestorage.googleapis.com/v0/b/critique-hall.appspot.com/o/default_profile_pics%2Fdefault-cover-picture-2.jpg?alt=media&token=525c2a78-6587-4fc7-aea7-0a844d2c1a2d';

		$data = [
			'first_name' 		=> $this->encryption->encrypt($this->input->post('first_name')),
			'last_name' 		=> $this->encryption->encrypt($this->input->post('last_name')),
			'display_name' 		=> $this->encryption->encrypt($this->input->post('display_name')),
			'email' 			=> $this->encryption->encrypt($this->input->post('email')),
			'password' 			=> password_hash($this->input->post('password'), PASSWORD_BCRYPT),
			'token'				=> $token,
			'token_exp'			=> $token_exp,
			'about_me'			=> $this->encryption->encrypt('Critique Hall Lover'),
			'specialization' 	=> $this->encryption->encrypt($this->input->post('specialization')),
			'profile_photo'		=> $this->encryption->encrypt($profile_photo),
			'cover_photo'		=> $this->encryption->encrypt($cover_photo)
		];

		$status 	 = $this->db->insert('users', $data);
		$inserted_id = $this->db->insert_id();

		$this->Audit_log_model->audit_log($inserted_id, null, 'Registered Account');
	
		return $status;
	}

	function login($id) {
		$token 		= sha1(microtime(true).mt_rand(10000,90000));
		$token_exp  = date("Y-m-d H:i:s", strtotime('+1 week'));

		$this->db->update('users', ['token' => $token, 'token_exp' => $token_exp], ['id' => $id]);

		if ($this->db->affected_rows() == 1)
			return [
				'token' 			=> $token,
				'token_exp'			=> $token_exp
			];
		
		return false;
	}

	//Email verification code input from user
	function confirm_verification($user_id, $verification_code) {
		$data = $this->db->order_by('id', 'desc')->get_where('email_verification', ['user_id' => $user_id])->row_array();

		if (date("Y-m-d H:i:s") < $data['expiration'] && $data['is_used'] == 0)
			if ($data['verification_code'] == $verification_code) {
				$this->Audit_log_model->audit_log($user_id, null, 'Verified Email');

				$token 		= sha1(microtime(true).mt_rand(10000,90000));
				$token_exp  = date("Y-m-d H:i:s", strtotime('+1 week'));

				$this->db->update('email_verification', ['is_used' => 1], ['user_id' => $user_id]);

				$this->db->update('users', [
											'token' => $token, 
											'token_exp' => $token_exp, 
											'email_verified' => 1
										], ['id' => $user_id]);
			
				return [
					'status' => true,
					'token'  => $token,
				];
			} else {
				$this->db->update('email_verification', ['is_used' => 1], ['user_id' => $user_id]);
				return 'Wrong code';
			}
		
		return 'Code expired';
	}

	//Send email verification
	function email_verification($user_id, $verification_code) {
		$data = $this->db->get_where('email_verification', ['user_id' => $user_id])->row_array();

		$expiration  = date("Y-m-d H:i:s", strtotime('+70 seconds'));
		$go_email 	 = 0;

		if (!is_null($data)) {
			if(date("Y-m-d H:i:s") > $data['expiration'] || $data['is_used'] == 1) {
				$this->Audit_log_model->audit_log($user_id, null, 'Asked for New Email Verification Code');

				$this->db->update('email_verification', [
														'verification_code' => $verification_code,
														'expiration' 		=> $expiration,
														'is_used'			=> 0
													], ['user_id' => $user_id]);
				$go_email++;
			}
		} else {
			$this->Audit_log_model->audit_log($user_id, null, 'Asked for Email Verification Code');

			$this->db->insert('email_verification', [
													'user_id' => $user_id,
													'verification_code' => $verification_code,
													'expiration' => $expiration
												]);
			$go_email++;
		}

		return $go_email;
	}

	function reset_password($new_password, $user_id) {
		$this->db->update('users', [
									'password' => $new_password, 
									'token_exp' => date("Y-m-d H:i:s", strtotime('-1 week'))
								], ['id' => $user_id]);
	
		if ($this->db->affected_rows() == 1) {
			return $this->db->update('forgot_password', ['token_exp' => date("Y-m-d H:i:s", strtotime('-1 hour'))], ['user_id' => $user_id]);
		}

		return false;
	}

	function set_forgot_password($user_id) {
		$token 		= sha1(microtime(true).mt_rand(10000,90000));
		$token_exp  = date("Y-m-d H:i:s", strtotime('+3  minutes'));

		$this->db->update('forgot_password', ['token' => $token, 'token_exp' => $token_exp], ['user_id' => $user_id]);

		if($this->db->affected_rows() == 0)
			$this->db->insert('forgot_password', ['token' => $token, 'token_exp' => $token_exp, 'user_id' => $user_id]);

		if ($this->db->affected_rows() == 1)
			return ['token' => $token, 'token_exp' => $token_exp];

		return false;
	}

	function get_forgot_password($user_id) {
		return $this->db->select('token, token_exp')->get_where('forgot_password', ['user_id' => $user_id])->row_array();
	}


	function logout() {
		$conditions = [
			'id' 	  => $this->encryption->decrypt($this->input->request_headers()['User-Id']),
			'token'	  => $this->encryption->decrypt($this->input->request_headers()['Token'])
		];

		$this->db->update('users', ['token_exp' => date("Y-m-d H:i:s", strtotime('-1 day'))], $conditions);

		if ($this->db->affected_rows() == 1) 
			$this->Audit_log_model->audit_log($this->encryption->decrypt($this->input->post('user_id')), null, 'Logged Out');

		return $this->db->affected_rows();
	}

	function get_single_user($user_id) {
		return $this->db->get_where('users', ['id' => $user_id])->row_array();
	}

	function is_verified($user_id) {
		return $this->db->select('email_verified')->get_where('users', ['id' => $user_id])->row_array();
	}

	function get_users() {
		return $this->db->order_by('id', 'DESC')->get('users')->result_array();
	}

	function unique_email() {
		return $this->db->select('email, id')->get('users')->result_array();
	}

	function unique_email_admin() {
		return $this->db->select('email, admin_id')->get('admins')->result_array();
	}

	function unique_display_name() {
		return $this->db->select('id, display_name')->get('users')->result_array();
	}

	function department_exists($department) {
		return $this->db->get_where('departments', ['name' => $department])->row_array();
	}

	function matches_department($specialization) {
		return $this->db->get_where('specialization', ['name' => $specialization])->row_array();
	}
}