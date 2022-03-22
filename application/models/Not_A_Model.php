<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Not_A_Model extends CI_Model {
	function ago_time($created_at) {
		$time_diff = time() - $created_at;

		$secs 	= $time_diff;
		$mins 	= round($secs/60);
		$hours 	= round($secs/3600);
		$days 	= round($secs/86400);
		$weeks	= round($secs/604800);
		$months	= round($secs/2629440);
		$years	= round($secs/31553280);

		if ($secs < 60) {
			if ($secs == 1) 
				return $secs." second ago";
			else
				return $secs." seconds ago";
		} elseif ($mins < 60) {
			if ($mins == 1)
				return "A minute ago";
			else	
				return $mins." minutes ago";
		} elseif ($hours < 24) {
			if ($hours == 1)
				return "An hour ago";
			else
				return $hours." hours ago";
		} elseif ($days < 6) {
			if ($days == 1)
				return "A day ago";
			else
				return $days." days ago";
		} elseif ($weeks < 4) {
			if ($weeks == 1)
				return "A week ago";
			else
				return $weeks." weeks ago";
		} elseif ($months < 12) {
			if ($months == 1)
				return "A month ago";
			else
				return $months." months ago";
		} elseif ($years > 1) {
			if ($years == 1)
				return $years."A year ago";
			else
				return $years." years ago";
		} else {
			return 'Error';
		}
	}

	function send_otp($user_email, $user_id, $admin_email, $admin_id) {
		$verification_code = random_string('numeric', 6);

		$status = $this->Static_model->save_otp($user_id, $admin_id, $verification_code);

		$email = (is_null($user_email) ? $admin_email : $user_email);

		if ($status) {
			$this->load->library('email');    

			$this->email->initialize($this->config->item('email_config'));

			$this->email->from('critiquehall@gmail.com', 'Critique Hall - OTP');
			$this->email->to($email);
			$this->email->subject('OTP');
			$this->email->message($this->load->view('otp', ['otp' => $verification_code], true));

			return $this->email->send();
		}
		
		return false;
	}

	function legit_user() {
		$user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);
		$token 	 = $this->encryption->decrypt($this->input->request_headers()['Token']);

		$user_data = $this->Auth_model->get_single_user($user_id);

		if ($user_id == $user_data['id'])
			if ($user_data['token_exp'] > date("Y-m-d H:i:s") && $token == $user_data['token'])
				if($user_data['email_verified'])  
					if ($user_data['sanction_exp']> date("Y-m-d H:i:s", strtotime('+8 hours')))
						if($user_data['sanction_type'] == 'Suspend')
							return 'Account Suspended';
						else
							return true;
					else
						return true;
				else
					return 'Email Not Verified';	
			else
				return 'Token Expired';
		else
			return 'User does not exist';
	}

	function is_muted($user_id) {
		$data = $this->db->select('sanction_type, sanction_exp')->get_where('users', ['id' => $user_id])->row_array();
	
		if ($data['sanction_type'] == 'Mute' && $data['sanction_exp'] > date("Y-m-d H:i:s", strtotime('+8 hours')))
			return true;

		return false;
	}

	function legit_admin($admin_id, $token) {
		$admin_data = $this->Admins_model->get_admin($admin_id);

		if ($admin_id == $admin_data['admin_id'] && $token == $admin_data['token'])
			if (!$admin_data['is_disabled'])
				if ($admin_data['token_exp'] > date("Y-m-d H:i:s"))
					if($admin_data['is_disabled'] == 0)
						return true;
					else
						return 'Account Disabled';
				else
					return 'Token Expired';
			else
				return 'Account disabled';

		return 'Unauthorized';
	}

	function feedback() {
		$user_id = $this->encryption->decrypt($this->input->request_headers()['User-Id']);

		$user_info = $this->Profile_model->get_user_info($user_id);

		$data = [
			'q1'  		 => $this->input->post('q1'),
			'q2'  		 => $this->input->post('q2'),
			'q3'  		 => $this->input->post('q3'),
			'q4'  		 => $this->input->post('q4'),
			'q5'  		 => $this->input->post('q5'),
			'q6'  		 => $this->input->post('q6'),
			'q7'  		 => $this->input->post('q7'),
			'q8'  		 => $this->input->post('q8'),
			'q9'  		 => $this->input->post('q9'),
			'q10' 		 => $this->input->post('q10'),
			'q11' 		 => $this->input->post('q11'),
			'q12' 		 => $this->input->post('q12')
		];

		$this->load->library('email');    

		$this->email->initialize($this->config->item('email_config'));

		$this->email->from('critiquehall@gmail.com', 'Critique Hall - Feedback');
		$this->email->to('critiquehall@gmail.com');
		$this->email->subject('Feedback: Id# '.$user_id.' - '.$this->encryption->decrypt($user_info['first_name']).' '.$this->encryption->decrypt($user_info['last_name']));
		$this->email->message($this->load->view('feedback', ['answers' => $data, 'i' => 0], true));

		return $this->email->send();
	}
}