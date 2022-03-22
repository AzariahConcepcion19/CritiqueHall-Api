<?php

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, DELETE");

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'libraries/RestController.php';

use chriskacerguis\RestServer\RestController;

class Admin_functions extends RestController {
	function __construct() {
		parent::__construct();

		$this->admin_id = $this->encryption->decrypt($this->input->request_headers()['Admin-Id']);
		$token 	  		= $this->encryption->decrypt($this->input->request_headers()['Token']);

		$is_legit_admin = $this->Not_A_Model->legit_admin($this->admin_id, $token);

		if ($is_legit_admin !== true)
			$this->response([
				'error' => $is_legit_admin
			], RestController::HTTP_UNAUTHORIZED);
	}

	function register_post() {
		if ($this->is_super_admin()) {
			$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[100]');
			$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|max_length[36]');
			$this->form_validation->set_rules('first_name', 'First Name', 'required|alpha_numeric_spaces|max_length[50]');
			$this->form_validation->set_rules('last_name', 'Last Name', 'required|alpha_numeric_spaces|max_length[50]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'status' 	=> 'Error',
					'message'	=> validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$admins = $this->Admins_model->get_admins();

				foreach ($admins as $admin)
					if ($this->encryption->decrypt($admin['email']) == $this->input->post('email'))
						$this->response([
							'status' 	=> 'Error',
							'message'	=> 'Email is already used'
						], RestController::HTTP_BAD_REQUEST);

				$data = $this->Admins_model->register($this->admin_id);

				$this->response([
					'status' => $data['status'],
					'id' 	 => $data['id'],
					'date' 	 => $data['date'],
				], ($data['status'] === false ? RestController::HTTP_INTERNAL_ERROR : RestController::HTTP_CREATED)); 
			}
		}
	}

	//Make or remove super admin
	function super_admin_post($admin_id) {
		if ($this->is_super_admin() && $admin_id != 4) {
			$msg = $this->Admins_model->make_super_admin($admin_id);

			$this->response([
				'msg' => $msg
			], ($msg === false ? RestController::HTTP_BAD_REQUEST : RestController::HTTP_OK));
		}

		$this->response([
			'Error' => 'Unauthorized'
		], RestController::HTTP_UNAUTHORIZED);
	}

	//Enable or disable admin account
	function disable_acc_post($admin_id) {
		if ($this->is_super_admin() && $admin_id != 4) {
			$msg = $this->Admins_model->disable_acc($admin_id);

			$this->response([
				'msg' => $msg
			], ($msg === false ? RestController::HTTP_BAD_REQUEST : RestController::HTTP_OK));
		}

		$this->response([
			'Error' => 'Unauthorized'
		], RestController::HTTP_UNAUTHORIZED);
	}

	function reply_report_post() {
		$this->form_validation->set_rules('report_id', 'Report Id', 'required|max_length[5000]');
		$this->form_validation->set_rules('sanction_type', 'Sanction Type', 'required|callback_sanction_valid');
		$this->form_validation->set_rules('sanction_exp', 'Sanction Expiration', 'required|max_length[50]|callback_date_changed');
		$this->form_validation->set_rules('msg', 'Message', 'max_length[3000]|callback_msg_req');
		$this->form_validation->set_rules('delete', 'Delete', 'is_numeric|max_length[1]');

		if ($this->form_validation->run() == false) {
			$this->response([
				'Error' => validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$msg_front = 'A message from a Critique Hall admin: ';
			$msg_end   = " If you wish to dispute this, you can contact us directly at critiquehall@gmail.com. Report id#".$this->encryption->decrypt($this->input->post('report_id'));

			if($this->input->post('sanction_type') == 'Suspend' || $this->input->post('sanction_type') == 'Mute')
				$reply = $msg_front."Thank you for taking the time to submit a report. We have reviewed your report and thus have decided to sanction the reportee accordingly.";
			elseif($this->input->post('sanction_type') == 'Warning')
				$reply = $msg_front."Thank you for taking the time to submit a report. We have reviewed your report and thus have decided to give the reportee a warning.";
			elseif($this->input->post('sanction_type') == 'Duplicate')
				$reply = $msg_front."Thank you for taking the time to submit a report and thus we are happy to inform you that this matter has already been dealt with accordingly.";
			else
				$reply = $msg_front."Thank you for taking the time to submit a report. We have reviewed your report and we understand your concern but the reportee's actions do not warrant a sanction.".$msg_end;

			$status = $this->Admins_model->reply_report($this->admin_id, $reply);

			$this->response([
				'status' => $status
			], ($status == 1 ? RestController::HTTP_CREATED : RestController::HTTP_BAD_REQUEST));
		}
	}

//GET FUNCTIONS
	function audit_logs_get($type) {
		$data = explode('_', $type);

		if ($data[0] == 'admin')
			if (!$this->is_super_admin())
				$this->response([
					'Error' => 'Unauthorized'
				], RestController::HTTP_UNAUTHORIZED);

		$type_id = $data[0].'_id';

		if ($data[1] == 1)
			$data[1] = 0;
		elseif ($data[1] > 1)
			$data[1] = ($data[1]*10)-10;

		$logs = $this->Admins_model->get_audit_logs($type_id, $data[1]);
		$i    = 0;

		if(!is_null($logs))
			foreach ($logs as $log) {
				$logs[$i][$type_id] 	= $this->encryption->decrypt($log[$type_id]);
				$logs[$i]['action'] 	= $this->encryption->decrypt($log['action']);
				$logs[$i]['created_at'] = date("M d, Y g:iA", strtotime('+8 hours', strtotime($log['created_at'])));

				$i++;
			}

		$this->response([
			'logs' 		=> $logs,
			'totalRows' => ($data[1] == 0 ? $this->Admins_model->get_logs_totalRows($type_id) : null)
		],RestController::HTTP_OK);
	}

	function get_reports_get($type) {
		$reports = $this->Admins_model->get_reports($type);
		$i 		 = 0;

		foreach ($reports as $report) {
			if(!is_null($reports[$i]['user_id'])) {
				$user_id 					 = $this->encryption->decrypt($report['user_id']);
				$user_data					 = $this->Admins_model->user_display_name($user_id);

				$owner['user_id']  	 		 = $user_id;
				$owner['display_name'] 		 = $this->encryption->decrypt($user_data['display_name']);
			} elseif(!is_null($reports[$i]['post_id'])) {
				$post_id 					 = $this->encryption->decrypt($report['post_id']);

				$reports[$i]['post_id']  	 = $post_id;
				$owner 						 = $this->Admins_model->post_display_name($post_id);
			} elseif(!is_null($reports[$i]['critique_id'])) {
				$critique_id 				 = $this->encryption->decrypt($report['critique_id']);

				$reports[$i]['critique_id']  = $critique_id;
				$owner 						 = $this->Admins_model->critique_display_name($critique_id);
			} elseif(!is_null($reports[$i]['reply_id'])) {
				$reply_id 				     = $this->encryption->decrypt($report['reply_id']);

				$reports[$i]['reply_id']     = $reply_id;
				$owner 						 = $this->Admins_model->reply_display_name($reply_id);	
			}

			$reports[$i]['display_name'] 	= $owner['display_name'];
			$reports[$i]['user_id'] 	 	= $owner['user_id'];
			$reports[$i]['reporter_id']  	= $this->encryption->decrypt($report['reporter_id']);
			$reports[$i]['message']		 	= $this->encryption->decrypt($report['message']);
			$reports[$i]['created_at'] 	 	= date("M d, Y g:iA", strtotime('+8 hours', strtotime($report['created_at'])));

			$i++;
		}

		$this->response([
			'Reports' => $reports
		], RestController::HTTP_OK);
	}

	function get_report_get($report_id) {
		$report   = $this->Admins_model->get_report($report_id);
			
		$report['reporter_id'] 		   = $this->encryption->decrypt($report['reporter_id']);
		$reporter_data				   = $this->Admins_model->user_display_name($report['reporter_id']);
		$report['reporter_dn']		   = $this->encryption->decrypt($reporter_data['display_name']);
		$report['reporter_pp']		   = $this->encryption->decrypt($reporter_data['profile_photo']);
		$report['encrypted_report_id'] = $this->encryption->encrypt($report['report_id']);
		$report['message']		 	   = $this->encryption->decrypt($report['message']);
		$report['created_at'] 	 	   = date("M d, Y g:iA", strtotime('+8 hours', strtotime($report['created_at'])));
		
		if (!is_null($report['user_id'])) {
			$report['user_id'] 	   		= $report['user_id'];

			$user_id 					= $this->encryption->decrypt($report['user_id']);
			$report['reportee_id'] 	   	= $user_id;

			$reportee_data				= $this->Admins_model->user_display_name($user_id);
			$report['reportee_dn']		= $this->encryption->decrypt($reportee_data['display_name']);
			$report['reportee_pp']		= $this->encryption->decrypt($reportee_data['profile_photo']);

			$report['sanction_type']	= $reportee_data['sanction_type'];
			$report['sanction_exp']		= date("Y-m-d\TH:i", strtotime('+8 hours', strtotime($reportee_data['sanction_exp'])));		
		} elseif (!is_null($report['post_id'])) {
			$post_id 					= $this->encryption->decrypt($report['post_id']);
			$report['post_id'] 	   		= $post_id;
			$post_data 				 	= $this->Admins_model->get_post($post_id);
			$reportee_data			 	= $this->Admins_model->get_user($post_data['user_id']);

			$report['reportee_dn']		= $this->encryption->decrypt($reportee_data['display_name']);
			$report['reportee_pp']		= $this->encryption->decrypt($reportee_data['profile_photo']);
			$report['reportee_id']		= $reportee_data['id'];

			$report['sanction_type']	= $reportee_data['sanction_type'];
			$report['sanction_exp']		= date("Y-m-d\TH:i", strtotime('+8 hours', strtotime($reportee_data['sanction_exp'])));		
		} elseif (!is_null($report['critique_id'])) {
			$critique_id 				= $this->encryption->decrypt($report['critique_id']);
			$report['critique_id'] 	   	= $critique_id;
			$critique_data			 	= $this->Admins_model->get_critique($critique_id);
			$reportee_data			 	= $this->Admins_model->get_user($critique_data['user_id']);

			$report['reportee_dn']		= $this->encryption->decrypt($reportee_data['display_name']);
			$report['reportee_pp']		= $this->encryption->decrypt($reportee_data['profile_photo']);
			$report['reportee_id']		= $reportee_data['id'];

			$report['critique_body']	= $critique_data['body'];
			$report['critique_vers']	= $this->Admins_model->get_critique_vers($critique_id);

			$report['sanction_type']	= $reportee_data['sanction_type'];
			$report['sanction_exp']		= date("Y-m-d\TH:i", strtotime('+8 hours', strtotime($reportee_data['sanction_exp'])));		
		} elseif (!is_null($report['reply_id'])) {
			$reply_id 					= $this->encryption->decrypt($report['reply_id']);
			$report['reply_id'] 	   	= $reply_id;
			$reply_data 				= $this->Admins_model->get_reply($reply_id);
			$reportee_data			 	= $this->Admins_model->get_user($reply_data['user_id']);

			$report['reportee_dn']		= $this->encryption->decrypt($reportee_data['display_name']);
			$report['reportee_pp']		= $this->encryption->decrypt($reportee_data['profile_photo']);
			$report['reportee_id']		= $reportee_data['id'];

			$report['reply_body']		= $reply_data['body'];
			$report['reply_vers']		= $this->Admins_model->get_reply_vers($reply_id);

			$report['sanction_type']	= $reportee_data['sanction_type'];
			$report['sanction_exp']		= date("Y-m-d\TH:i", strtotime('+8 hours', strtotime($reportee_data['sanction_exp'])));		
		}

		$this->response([
			'Report' => $report
		], (!is_null($report) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}

	function get_accs_get($type) {
		$verified_email = 0;
		$i = 0;
		if ($type == 'admin') {
			if ($this->is_super_admin()) {
				$accs = $this->Admins_model->get_admin_list();

				foreach ($accs as $acc) {
					$accs[$i]['first_name'] = $this->encryption->decrypt($acc['first_name']);
					$accs[$i]['last_name']  = $this->encryption->decrypt($acc['last_name']);
					$accs[$i]['created_at'] = date("M d, Y g:iA", strtotime('+8 hours', strtotime($acc['created_at'])));

					$i++;
				}
			}
		} elseif ($type == 'user') {
			$accs = $this->Admins_model->get_user_list();
		
			foreach ($accs as $acc) {
				$accs[$i]['first_name']    = $this->encryption->decrypt($acc['first_name']);
				$accs[$i]['last_name']     = $this->encryption->decrypt($acc['last_name']);
				$accs[$i]['display_name']  = $this->encryption->decrypt($acc['display_name']);
				$accs[$i]['created_at']    = date("M d, Y g:iA", strtotime('+8 hours', strtotime($acc['created_at'])));

				if ($accs[$i]['email_verified'] == 1)
					$verified_email++;

				$i++;
			}
		}

		$this->response([
			'Accs' 		   	  => $accs,
			'verified_accs'   => $verified_email,
			'registered_accs' => $i
		], (!is_null($accs) ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
	}

	function get_acc_get() {
		if ($this->input->get('type') == 'admin') {
			if ($this->is_super_admin() == false) {
				$this->response([
					'Error' => 'Unauthorized'
				], RestController::HTTP_UNAUTHORIZED);
			} else {
				$admin = $this->Admins_model->get_admin_one($this->input->get('id'));

				$admin['encrypted_id']	= $this->encryption->encrypt($admin['admin_id']);
				$admin['email'] 	 	= $this->encryption->decrypt($admin['email']);
				$admin['first_name'] 	= $this->encryption->decrypt($admin['first_name']);
				$admin['last_name']  	= $this->encryption->decrypt($admin['last_name']);
				$admin['created_at'] 	= date("M d, Y g:iA", strtotime('+8 hours', strtotime($admin['created_at'])));

				$this->response([
					'admin' => $admin
				], RestController::HTTP_OK);
			}
		} elseif ($this->input->get('type') == 'user') {
			$user = $this->Admins_model->get_user($this->input->get('id'));

			$user['encrypted_id']	= $this->encryption->encrypt($user['id']);
			$user['first_name'] 	= $this->encryption->decrypt($user['first_name']);
			$user['last_name'] 		= $this->encryption->decrypt($user['last_name']);
			$user['display_name'] 	= $this->encryption->decrypt($user['display_name']);
			$user['email'] 			= $this->encryption->decrypt($user['email']);
			$user['profile_photo']  = $this->encryption->decrypt($user['profile_photo']);
			$user['cover_photo'] 	= $this->encryption->decrypt($user['cover_photo']);
			$user['specialization'] = $this->encryption->decrypt($user['specialization']);
			$user['about_me'] 		= $this->encryption->decrypt($user['about_me']);
			$user['sanction_exp']	= date("Y-m-d\TH:i", strtotime($user['sanction_exp']));
			$user['created_at'] 	= date("M d, Y g:iA", strtotime('+8 hours', strtotime($user['created_at'])));

			$this->response([
				'user' => $user
			], RestController::HTTP_OK);
		}
	}

	function sanction_user_post() {
		$this->form_validation->set_rules('id', 'Id', 'required');
		$this->form_validation->set_rules('sanction_type', 'Sanction', 'required|callback_sanction_valid');
		$this->form_validation->set_rules('sanction_exp', 'Sanction Expiration', 'required|max_length[50]');
		$this->form_validation->set_rules('msg', 'Message', 'max_length[3000]|callback_msg_req');

		if ($this->form_validation->run() == false) {
			$this->response([
				'Error' => validation_errors()
			], RestController::HTTP_BAD_REQUEST);
		} else {
			$status = $this->Admins_model->sanction_user($this->encryption->decrypt($this->input->post('id')), $this->admin_id);

			$this->response([
				'status' => $status
			], ($status == true ? RestController::HTTP_OK : RestController::HTTP_INTERNAL_ERROR));
		}
	}

	function edit_user_post() {
		if ($this->is_super_admin() == true) {
			$this->form_validation->set_rules('id', 'Id', 'required');
			$this->form_validation->set_rules('first_name', 'First Name', 'required|callback_no_changes|alpha_numeric_spaces|max_length[50]');
			$this->form_validation->set_rules('last_name', 'Last Name', 'required|alpha_numeric_spaces|max_length[50]');
			$this->form_validation->set_rules('display_name', 'Display Name', 'required|alpha_numeric|callback_unique_display_name|max_length[50]');
			$this->form_validation->set_rules('email', 'Email', 'required|callback_unique_email|max_length[100]');
			$this->form_validation->set_rules('about_me', 'About Me', 'required|max_length[100]');
			$this->form_validation->set_rules('reputation_points', 'Reputation Points', 'required|is_natural');
			$this->form_validation->set_rules('specialization', 'Specialization', 'required|alpha_numeric_spaces|max_length[100]');
			$this->form_validation->set_rules('profile_photo', 'Profile Photo', 'required|callback_valid_file|max_length[500]');
			$this->form_validation->set_rules('cover_photo', 'Cover Photo', 'required|callback_valid_file|max_length[500]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'Error' => validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Admins_model->edit_user($this->admin_id);

				$this->response([
					'status' => $status
				], ($status != false ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
			}
		}
	}

	function edit_admin_post() {
		if ($this->is_super_admin() == true) {
			$this->form_validation->set_rules('id', 'Id', 'required');
			$this->form_validation->set_rules('first_name', 'First Name', 'required|callback_no_changes_admin|alpha_numeric_spaces|max_length[50]');
			$this->form_validation->set_rules('last_name', 'Last Name', 'required|alpha_numeric_spaces|max_length[50]');
			$this->form_validation->set_rules('email', 'Email', 'required|callback_unique_email_admin|max_length[100]');

			if ($this->form_validation->run() == false) {
				$this->response([
					'Error' => validation_errors()
				], RestController::HTTP_BAD_REQUEST);
			} else {
				$status = $this->Admins_model->edit_admin($this->admin_id);

				$this->response([
					'status' => $status
				], ($status != false ? RestController::HTTP_OK : RestController::HTTP_BAD_REQUEST));
			}
		}
	}

	function logout_post() {
		$this->response([
			'status' => $this->Admins_model->logout($this->admin_id)
		], RestController::HTTP_OK);
	}

//Custom Rules
	function is_super_admin() {
		return $this->Admins_model->is_super_admin($this->admin_id);
	}

	function unique_email($email) {
		$data = $this->Auth_model->unique_email();

		foreach ($data as $user_data)
			if (strtolower($email) == strtolower($this->encryption->decrypt($user_data['email']))) {
				if($this->encryption->decrypt($this->input->post('id')) != $user_data['id']) {
					$this->form_validation->set_message('unique_email', 'Email is already used');
					return false;
				}
			}

		return true;
	}

	function unique_email_admin($email) {
		$data = $this->Auth_model->unique_email_admin();

		foreach ($data as $user_data)
			if (strtolower($email) == strtolower($this->encryption->decrypt($user_data['email']))) {
				if($this->encryption->decrypt($this->input->post('id')) != $user_data['admin_id']) {
					$this->form_validation->set_message('unique_email_admin', 'Email is already used');
					return false;
				}
			}

		return true;
	}

	function unique_display_name($display_name) {
		$data = $this->Auth_model->unique_display_name();

		foreach ($data as $user_data)
			if (strtolower($display_name) == strtolower($this->encryption->decrypt($user_data['display_name']))) {
				if($this->encryption->decrypt($this->input->post('id')) != $user_data['id']) {
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

	function no_changes($first_name) {
		$user_data = $this->Admins_model->get_user($this->encryption->decrypt($this->input->post('id')));
	
		if (strtolower($first_name) 					== strtolower($this->encryption->decrypt($user_data['first_name'])) &&
			strtolower($this->input->post('last_name')) == strtolower($this->encryption->decrypt($user_data['last_name'])) &&
			strtolower($this->input->post('email')) 	== strtolower($this->encryption->decrypt($user_data['email'])) &&
			strtolower($this->input->post('display_name')) == strtolower($this->encryption->decrypt($user_data['display_name'])) &&
			strtolower($this->input->post('about_me')) 	== strtolower($this->encryption->decrypt($user_data['about_me'])) &&
			strtolower($this->input->post('specialization')) 	== strtolower($this->encryption->decrypt($user_data['specialization'])) &&
			strtolower($this->input->post('profile_photo')) 	== strtolower($this->encryption->decrypt($user_data['profile_photo'])) &&
			strtolower($this->input->post('cover_photo')) 	== strtolower($this->encryption->decrypt($user_data['cover_photo'])) &&
			$this->input->post('reputation_points') 	== $user_data['reputation_points'])
		{
			$this->form_validation->set_message('no_changes', 'No changes made');
			return false;
		}

		return true;
	}

	function no_changes_admin($first_name) {
		$admin_data = $this->Admins_model->get_admin_one($this->encryption->decrypt($this->input->post('id')));
	
		if (strtolower($first_name) 					== strtolower($this->encryption->decrypt($admin_data['first_name'])) &&
			strtolower($this->input->post('last_name')) == strtolower($this->encryption->decrypt($admin_data['last_name'])) &&
			strtolower($this->input->post('email')) 	== strtolower($this->encryption->decrypt($admin_data['email'])))
		{
			$this->form_validation->set_message('no_changes_admin', 'No changes made');
			return false;
		}

		return true;
	}

	function date_changed($sanction_exp) {
		if($this->input->post('sanction_type') == 'None' || $this->input->post('sanction_type') == 'Warning' || $this->input->post('sanction_type') == 'Duplicate')
			return true;
		
		if (date('Y-m-d H:i:s', strtotime($sanction_exp)) > date('Y-m-d H:i:s'))
			return true;

		$this->form_validation->set_message('date_changed', 'Sanction expiration should be higher than the current date');
		return false;
	}

	function msg_req($msg) {
		if($this->input->post('sanction_type') == 'Suspend' || $this->input->post('sanction_type') == 'None' || $this->input->post('sanction_type') == 'Mute' || $this->input->post('sanction_type') == 'Duplicate')
			return true;

		if($this->input->post('sanction_type') == 'Warning' && $msg != null)
			return true;

		$this->form_validation->set_message('msg_req', 'Message field is required');
		return false;
	}

	function sanction_valid($type) {
		if ($type == 'Suspend' || $type == 'Mute' || $type == 'None' || $type == 'Warning' || $type == 'Duplicate')
			return true;

		$this->form_validation->set_message('sanction_valid', 'Sanction Error');
		return false;
	}
}