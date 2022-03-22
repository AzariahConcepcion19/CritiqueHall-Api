<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Admins_model extends CI_Model {
	function __construct() {
		$this->load->database();	
	}

//Authentication
	function confirm_otp($admin_id, $otp) {
		$data = $this->db->get_where('otp', ['admin_id' => $admin_id])->row_array();
	
		if (!is_null($data))
			if (date("Y-m-d H:i:s") < $data['expiration']) {
				if ($data['pin'] == $otp) {
					$admin = $this->db->get_where('admins', ['admin_id' => $admin_id])->row_array();

					if ($admin['token_exp'] < date("Y-m-d H:i:s")) {
						$token 		= sha1(microtime(true).mt_rand(10000,90000));
						$token_exp  = date("Y-m-d H:i:s", strtotime('+1 hour'));

						$this->db->update('admins',['token' => $token, 'token_exp' => $token_exp], ['admin_id' => $admin_id]);
					} else {
						return [
							'token' 			=> $admin['token'],
							'token_exp'			=> $admin['token_exp'],
							'is_super_admin'	=> $admin['is_super_admin'],
							'first_name'		=> $this->encryption->decrypt($admin['first_name']),
							'last_name'			=> $this->encryption->decrypt($admin['last_name'])
						];
					}

					if ($this->db->affected_rows() == 1) {
						$this->Audit_log_model->audit_log(null, $admin_id, 'Logged In');

						return [
							'token' 			=> $token,
							'token_exp'			=> $token_exp,
							'is_super_admin'	=> $admin['is_super_admin'],
							'first_name'		=> $this->encryption->decrypt($admin['first_name']),
							'last_name'			=> $this->encryption->decrypt($admin['last_name'])
						];
					}
				} else {
					return 'Wrong otp';
				}
			} else {
				return 'Otp expired';
			}

		return false;
	}

	function get_admins() {
		return $this->db->get('admins')->result_array();
	}

	function set_forgot_password($admin_id) {
		$token 		= sha1(microtime(true).mt_rand(10000,90000));
		$token_exp  = date("Y-m-d H:i:s", strtotime('+3  minutes'));

		$this->db->update('forgot_password', ['token' => $token, 'token_exp' => $token_exp], ['admin_id' => $admin_id]);

		if($this->db->affected_rows() == 0)
			$this->db->insert('forgot_password', ['token' => $token, 'token_exp' => $token_exp, 'admin_id' => $admin_id]);

		if ($this->db->affected_rows() == 1)
			return ['token' => $token, 'token_exp' => $token_exp];

		return false;
	}

	function get_forgot_password($admin_id) {
		return $this->db->select('token, token_exp')->get_where('forgot_password', ['admin_id' => $admin_id])->row_array();
	}

	function reset_password($new_password, $admin_id) {
		$this->db->update('admins', [
										'password'  => $new_password,
										'token_exp' => date("Y-m-d H:i:s", strtotime('-1 hour'))
									], ['admin_id'  => $admin_id]);
	
		if ($this->db->affected_rows() == 1)
			return $this->db->update('forgot_password', ['token_exp' => date("Y-m-d H:i:s", strtotime('-1 hour'))], ['admin_id' => $admin_id]);

		return false;
	}

	function register($admin_id) {
		$this->db->insert('admins', [
										'first_name' => $this->encryption->encrypt($this->input->post('first_name')),
										'last_name'  => $this->encryption->encrypt($this->input->post('last_name')),
										'email' 	 => $this->encryption->encrypt($this->input->post('email')),
										'password' 	 => password_hash($this->input->post('password'), PASSWORD_BCRYPT),
									]);

		if ($this->db->affected_rows() == 1) {
			$inserted_id = $this->db->insert_id();
			$action = 'Registered admin id# '.$inserted_id;
			$this->Audit_log_model->audit_log(null, $admin_id, $action);

			date_default_timezone_set('Asia/Manila');
			return ['status' => true, 'id' => $inserted_id, 'date' => date("M d, Y g:iA")];
		}

		return ['status' => false];
	}

	//Make or remove super admin
	function make_super_admin($admin_id) {
		$data = $this->db->select('is_super_admin')->get_where('admins', ['admin_id' => $admin_id])->row_array();

		$value = ($data['is_super_admin'] ? 0 : 1);
		$this->db->update('admins', ['is_super_admin' => $value], ['admin_id' => $admin_id]);

		if ($this->db->affected_rows() == 1) {
			if ($value == 1) {
				$action = 'Made admin id#'.$admin_id.' a super admin';
				$msg    = 'Admin id# '.$admin_id.' has been promoted';
			} else {
				$action = 'Removed super admin status of id#'.$admin_id;
				$msg    = 'Admin id# '.$admin_id.' has been demoted';
			}

			$this->Audit_log_model->audit_log(null, $admin_id, $action);

			return $msg;
		}

		return false;
	}

	//Enable or disable admin acc
	function disable_acc($admin_id) {
		$data = $this->db->select('is_disabled')->get_where('admins', ['admin_id' => $admin_id])->row_array();

		$value = ($data['is_disabled'] ? 0 : 1);
		$this->db->update('admins', ['is_disabled' => $value], ['admin_id' => $admin_id]);

		if ($this->db->affected_rows() == 1) {
			if ($value == 1) {
				$action = 'Disabled admin id#'.$admin_id;
				$msg    = 'Admin id# '.$admin_id.' disabled';
			} else {
				$action = 'Enabled admin id#'.$admin_id;
				$msg    = 'Admin id# '.$admin_id.' enabled';
			}

			$this->Audit_log_model->audit_log(null, $admin_id, $action);

			return $msg;
		}

		return false;
	}

	function update_info($admin_id) {
		$first_name = $this->input->post('first_name');
		$last_name  = $this->input->post('last_name');

		$data = [
			'first_name' => $this->encryption->encrypt($first_name),
			'last_name'  => $this->encryption->encrypt($last_name)
		];

		$this->db->update('admins', $data, ['admin_id' => $this->input->post('admin_id')]);

		if ($this->db->affected_rows() == 1) {
			$action = 'Updated information of id#'.$this->input->post('admin_id').' to [First Name => '.$first_name.', Last name => '.$last_name.']';

			$this->Audit_log_model->audit_log(null, $admin_id, $action);

			return true;
		}

		return false;
	}

	private function sanction_user_auto($id, $admin_id, $report_id) {
		$reportee_data = $this->db->select('sanction_exp, sanction_type')->get_where('users', ['id' => $id])->row_array();

		if($reportee_data['sanction_type'] != $this->input->post('sanction_type') &&
			$reportee_data['sanction_exp'] == $this->input->post('sanction_exp'))
		{
			$this->db->update('users', [
										'sanction_type' => $this->input->post('sanction_type')
									], ['id' => $id]);
		}
		elseif($reportee_data['sanction_type'] == $this->input->post('sanction_type') &&
			$reportee_data['sanction_exp'] != $this->input->post('sanction_exp'))
		{
			$this->db->update('users', [
										'sanction_exp'  => $this->input->post('sanction_exp')
									], ['id' => $id]);
		}
		else
		{
		$this->db->update('users', [
									'sanction_type' => $this->input->post('sanction_type'),
									'sanction_exp'  => $this->input->post('sanction_exp')
								], ['id' => $id]);
		}
		
		if($this->db->affected_rows() == 1) {
			$this->Audit_log_model->audit_log(null, $admin_id, 'Report id#'.$report_id.': gave '. $this->input->post('sanction_type').' to account id#'.$id.' until '.date("M d, Y g:iA", strtotime($this->input->post('sanction_exp'))));

			if ($this->input->post('sanction_type') == 'Mute') {
				$message = 'A message from a Critique Hall admin: Your account was reported due to violating the terms and conditions. You are now temporarily muted and thus will be unable to create, delete, edit, nor star posts/critiques/replies until '.date("M d, Y g:iA", strtotime($this->input->post('sanction_exp'))).'. If you wish to dispute this, you can contact us directly at critiquehall@gmail.com. Report ID#'.$report_id;

				return $this->db->insert('notifs', [
												'owner_id'  => $id,
												'action'    => $this->encryption->encrypt($message)
											]);
			}
			
			return true;
		}

		return false;
	}

	function reply_report($admin_id, $reply) {
		$decrypted_report_id = $this->encryption->decrypt($this->input->post('report_id'));
		$report_data = $this->db->get_where('reports', ['report_id' => $decrypted_report_id])->row_array();

		if($report_data['is_resolved'] == 0) {
			if(!is_null($report_data['user_id']))
			{
				$reportee_id = $this->encryption->decrypt($report_data['user_id']);
			}
			elseif(!is_null($report_data['post_id']))
			{
				$post_id = $this->encryption->decrypt($report_data['post_id']);
				$data = $this->post_display_name($post_id);
				$reportee_id = $data['user_id'];

				if($this->input->post('delete') != 0 && $this->input->post('delete') != 1) {
					return 'Delete post/critique/reply error';
				} elseif ($this->input->post('delete') == 1) {
					$this->db->update('posts', ['is_deleted' => 1], ['post_id' => $post_id]);

					if($this->db->affected_rows() == 1) {
						$this->Audit_log_model->audit_log(null, $admin_id, 'Post id#'.$post_id.': Deleted due to violation');
						$this->db->insert('notifs', [
														'owner_id'  => $reportee_id,
														'action'    => $this->encryption->encrypt('A message from a Critique Hall admin: Your post entitled "'.$data['title'].'" was taken down due to inappropriateness.')
													]);
					}
				}
			}
			elseif(!is_null($report_data['critique_id']))
			{
				$critique_id = $this->encryption->decrypt($report_data['critique_id']);
				$data = $this->critique_display_name($critique_id);
				$reportee_id = $data['user_id'];

				if($this->input->post('delete') != 0 && $this->input->post('delete') != 1) {
					return 'Delete post/critique/reply error';
				} elseif ($this->input->post('delete') == 1) {
					$this->db->update('critiques', ['is_deleted' => 1], ['critique_id' => $critique_id]);

					if(strlen($data['body']) > 100) {
						$data['body'] = substr($data['body'], 0, 100);
						$data['body'] .= '... ';
					}

					if($this->db->affected_rows() == 1) {
						$this->Audit_log_model->audit_log(null, $admin_id, 'Critique id#'.$critique_id.': deleted due to violation');
						$this->db->insert('notifs', [
														'owner_id'  => $reportee_id,
														'action'    => $this->encryption->encrypt('A message from a Critique Hall admin: Your critique "'.$data['body'].'" was taken down due to inappropriateness.')
													]);
					}
				}
			}
			elseif(!is_null($report_data['reply_id']))
			{
				$reply_id = $this->encryption->decrypt($report_data['reply_id']);
				$data = $this->reply_display_name($reply_id);
				$reportee_id = $data['user_id'];

				if($this->input->post('delete') != 0 && $this->input->post('delete') != 1) {
					return 'Delete post/critique/reply error';
				} elseif ($this->input->post('delete') == 1) {
					$this->db->update('replies', ['is_deleted' => 1], ['reply_id' => $reply_id]);

					if(strlen($data['body']) > 100) {
						$data['body'] = substr($data['body'], 0, 100);
						$data['body'] .= '... ';
					}

					if($this->db->affected_rows() == 1) {
						$this->Audit_log_model->audit_log(null, $admin_id, 'Reply id#'.$reply_id.': deleted due to violation');
						$this->db->insert('notifs', [
														'owner_id'  => $reportee_id,
														'action'    => $this->encryption->encrypt('A message from a Critique Hall admin: Your reply "'.$data['body'].'" was taken down due to inappropriateness.')
													]);
					}
				}
			}

			$reportee_data = $this->get_user($reportee_id);

			if ($reportee_data['sanction_type'] != $this->input->post('sanction_type') ||
				$reportee_data['sanction_exp'] != date('Y-m-d H:i:s', strtotime($this->input->post('sanction_exp'))))
			{
				if ($report_data['is_resolved'] == 0) {
					$this->db->insert('notifs', [
													'owner_id'  => $this->encryption->decrypt($report_data['reporter_id']),
													'report_id' => $report_data['report_id'],
													'action'	=> $this->encryption->encrypt($reply)
												]);

					if ($this->db->affected_rows() == 1) {
						$this->db->update('reports', ['is_resolved' => 1], ['report_id' => $report_data['report_id']]);

						if ($this->input->post('sanction_type') == 'None') {
							return $this->Audit_log_model->audit_log(null, $admin_id, 'Report id#'.$decrypted_report_id.': Resolved. No sanctions given');
						} elseif ($this->input->post('sanction_type') == 'Duplicate') {
							return $this->Audit_log_model->audit_log(null, $admin_id, 'Report id#'.$decrypted_report_id.': Duplicated Report');
						} elseif ($this->input->post('sanction_type') == 'Warning') {
							$this->Audit_log_model->audit_log(null, $admin_id, 'Report id#'.$decrypted_report_id.': Gave Warning');

							return $this->db->insert('notifs', [
														'owner_id'  => $reportee_id,
														'action'    => $this->encryption->encrypt('A message from a Critique Hall admin: '.$this->input->post('msg'))
													]);
						} else {
							return $this->sanction_user_auto($reportee_id, $admin_id, $decrypted_report_id);
						}
					}
				}
			} else {
				return 'No Changes made';
			}
		} else {
			return 'Report already resolved';
		}

		return false;
	}

	function sanction_user($id, $admin_id) {
		if($this->input->post('sanction_type') == 'Warning') {
			$this->db->insert('notifs', [
										'owner_id'  => $id,
										'action'    => $this->encryption->encrypt($this->input->post('msg'))
									]);

			if($this->db->affected_rows() == 1)
				return $this->Audit_log_model->audit_log(null, $admin_id, 'Account id#'.$id.': Gave Warning');
		}

		$this->db->update('users', [
									'sanction_type' => $this->input->post('sanction_type'),
									'sanction_exp'  => $this->input->post('sanction_exp')
								], ['id' => $id]);

		if($this->db->affected_rows() == 1)
			return $this->Audit_log_model->audit_log(null, $admin_id, 'Account id#'.$id.': '.$this->input->post('sanction_type').' until '.date("M d, Y g:iA", strtotime($this->input->post('sanction_exp'))));

		return false;
	}

	function edit_admin($admin_id) {
		$decrypted_id = $this->encryption->decrypt($this->input->post('id'));

		$data = [
			'first_name' => $this->encryption->encrypt($this->input->post('first_name')),
			'last_name'  => $this->encryption->encrypt($this->input->post('last_name')),
			'email'  	 => $this->encryption->encrypt($this->input->post('email')),
		];

		$this->db->update('admins', $data, ['admin_id' => $decrypted_id]);

		if ($this->db->affected_rows() == 1) {
			$this->Audit_log_model->audit_log(null, $decrypted_id, 'Updated the information of admin id#'.$decrypted_id);

			return 'Updated the information of admin id#'.$decrypted_id;
		}

		return false;
	}

	function edit_user($admin_id) {
		$decrypted_id = $this->encryption->decrypt($this->input->post('id'));

		$data = [
			'first_name'   		  => $this->encryption->encrypt($this->input->post('first_name')),
			'last_name'    		  => $this->encryption->encrypt($this->input->post('last_name')),
			'email'  	   		  => $this->encryption->encrypt($this->input->post('email')),
			'display_name' 		  => $this->encryption->encrypt($this->input->post('display_name')),
			'about_me'     		  => $this->encryption->encrypt($this->input->post('about_me')),
			'reputation_points'   => $this->input->post('reputation_points'),
			'specialization'	  => $this->encryption->encrypt($this->input->post('specialization')),
			'profile_photo'		  => $this->encryption->encrypt($this->input->post('profile_photo')),
			'cover_photo' 		  => $this->encryption->encrypt($this->input->post('cover_photo'))
		];

		if ($this->input->post('new_password') != '')
			$data['password'] = password_hash($this->input->post('new_password'), PASSWORD_BCRYPT);

		$this->db->update('users', $data, ['id' => $decrypted_id]);

		if ($this->db->affected_rows() == 1) {
			$this->Audit_log_model->audit_log(null, $admin_id, 'Updated the information of user id#'.$decrypted_id);

			return 'Updated the information of user id#'.$decrypted_id;
		}

		return false;
	}

//GET FUNCTIONS
	function is_super_admin($admin_id) {
		$data = $this->db->select('is_super_admin')->get_where('admins', ['admin_id' => $admin_id])->row_array();

		return $data['is_super_admin'];
	}

	function get_audit_logs($type_id, $start) {
		return $this->db->limit(10, $start)->order_by('audit_log_id', 'DESC')->get_where('audit_log', [$type_id.'!=' => null])->result_array();
	}

	function get_logs_totalRows($type_id) {
		return $this->db->get_where('audit_log', [$type_id.'!=' => null])->num_rows();
	}

	function get_admin_list() {
		return $this->db->select('admin_id, first_name, last_name, created_at, is_super_admin')->order_by('admin_id', 'DESC')->get_where('admins', ['admin_id >' => 4])->result_array();
	}

	function get_admin_one($admin_id) {
		return $this->db->select('admin_id, first_name, last_name, created_at, email, password, is_super_admin, is_disabled, token, token_exp')->get_where('admins', ['admin_id' => $admin_id])->row_array();
	}

	function get_user_list() {
		return $this->db->select('id, first_name, last_name, display_name, created_at, email_verified')->order_by('id', 'DESC')->get('users')->result_array();
	}

	function get_user($user_id) {
		return $this->db->select('id, first_name, last_name, display_name, email, profile_photo, cover_photo, specialization, about_me, reputation_points, sanction_type, sanction_exp, created_at')->get_where('users', ['id' => $user_id])->row_array();
	}

	function get_admin($admin_id) {
		return $this->db->select('admin_id, token, token_exp, is_disabled')->get_where('admins', ['admin_id' => $admin_id])->row_array();
	}	

	function get_reports($type) {
		if ($type == 'unread')
			return $this->db->order_by('report_id', 'DESC')->get_where('reports',['is_read' => 0])->result_array();
		elseif ($type == 'read')
			return $this->db->order_by('report_id', 'DESC')->get_where('reports',['is_read' => 1, 'is_resolved' => 0])->result_array();
		elseif ($type == 'notified')
			return $this->db->order_by('report_id', 'DESC')->get_where('reports',['is_resolved' => 1])->result_array();
	}

	function get_report($report_id) {
		$data = $this->db->get_where('reports', ['report_id' => $report_id])->row_array();

		$this->db->update('reports', ['is_read' => 1], $data);

		return $data;
	}

	function user_display_name($user_id) {
		return  $this->db->select('display_name, profile_photo, sanction_type, sanction_exp')->get_where('users', ['id' => $user_id])->row_array();
	}

	function get_post($post_id) {
		return $this->db->get_where('posts', ['post_id' => $post_id])->row_array();
	}

	function get_critique($critique_id) {
		return $this->db->get_where('critiques', ['critique_id' => $critique_id])->row_array();
	}

	function get_reply($reply_id) {
		return $this->db->get_where('replies', ['reply_id' => $reply_id])->row_array();
	}

	function post_display_name($post_id) {
		$post =  $this->db->select('user_id, title')->get_where('posts', ['post_id' => $post_id])->row_array();
		$data =  $this->db->select('display_name, id')->get_where('users', ['id' => $post['user_id']])->row_array();
		
		return [
					'display_name' => $this->encryption->decrypt($data['display_name']),
					'user_id'	   => $data['id'],
					'title'		   => $post['title']
				];
	}

	function get_critique_vers($critique_id) {
		$critique_vers = $this->db->select('created_at, body')->order_by('created_at')->get_where('critique_versions', ['critique_id' => $critique_id])->result_array();

		if(!is_null($critique_vers)) {
			$i = -1;
			foreach ($critique_vers as $critique_ver)
				$critique_vers[++$i]['created_at'] = date("M d, Y g:iA", strtotime($critique_ver['created_at']));
		}

		return $critique_vers;
	}

	function get_reply_vers($reply_id) {
		$reply_vers = $this->db->select('created_at, body')->order_by('created_at')->get_where('reply_versions', ['reply_id' => $reply_id])->result_array();

		if(!is_null($reply_vers)) {
			$i = -1;
			foreach ($reply_vers as $reply_ver)
				$reply_vers[++$i]['created_at'] = date("M d, Y g:iA", strtotime($reply_ver['created_at']));
		}

		return $reply_vers;
	}

	function critique_display_name($critique_id) {
		$datax = $this->db->select('user_id, body')->get_where('critiques', ['critique_id' => $critique_id])->row_array();
		$data  =  $this->db->select('id, display_name')->get_where('users', ['id' => $datax['user_id']])->row_array();

		return [
					'display_name' => $this->encryption->decrypt($data['display_name']),
					'user_id'	   => $data['id'],
					'body'		   => $datax['body']
				];
	}

	function reply_display_name($reply_id) {
		$datax = $this->db->select('user_id, body')->get_where('replies', ['reply_id' => $reply_id])->row_array();
		$data  = $this->db->select('id, display_name')->get_where('users', ['id' => $datax['user_id']])->row_array();

		return [
					'display_name' => $this->encryption->decrypt($data['display_name']),
					'user_id'	   => $data['id'],
					'body'		   => $datax['body']
				];
	}

	function logout($admin_id) {
		$this->db->update('admins', ['token_exp' => date("Y-m-d H:i:s")], ['admin_id' => $admin_id]);

		return $this->db->affected_rows();
	}
}