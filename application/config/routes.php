<?php
defined('BASEPATH') OR exit('No direct script access allowed');

//Auth
$route['api/register'] 					= 'api/Auth/register';
$route['api/login'] 					= 'api/Auth/login';
$route['api/confirm_verification'] 		= 'api/Auth/confirm_verification';
$route['api/forgot_password'] 			= 'api/Auth/forgot_password'; 			//Send forgot password to email
$route['api/reset_password'] 			= 'api/Auth/reset_password';

//Posts
$route['api/posts_pagination/(:any)']	= 'api/Posts/posts_pagination/$1';
$route['api/display_post/(:any)']		= 'api/Posts/display_post/$1';
$route['api/create_post']				= 'api/Posts/create_posts';
$route['api/update_post']				= 'api/Posts/update_posts';
$route['api/delete_post/(:any)']		= 'api/Posts/delete_posts/$1';
$route['api/like_post/(:any)']			= 'api/Posts/like_posts/$1';
$route['api/version_post/(:any)']		= 'api/Posts/version_posts/$1';

//Critiques
$route['api/display_all_critiques']		= 'api/Critiques/display_critiques';
$route['api/create_critique']			= 'api/Critiques/create_critiques';
$route['api/update_critique']			= 'api/Critiques/update_critiques';
$route['api/delete_critique/(:any)']	= 'api/Critiques/delete_critiques/$1';
$route['api/star_critique']				= 'api/Critiques/star_critique';
$route['api/version_critique/(:any)']	= 'api/Critiques/version_critiques/$1';

//Replies
$route['api/display_replies']			= 'api/Replies/display_replies';
$route['api/create_reply']				= 'api/Replies/create_replies';
$route['api/update_reply']				= 'api/Replies/update_replies';
$route['api/delete_reply/(:any)']		= 'api/Replies/delete_replies/$1';
$route['api/star_reply']				= 'api/Replies/star_replies';
$route['api/version_reply/(:any)']		= 'api/Replies/version_replies/$1';

//Profile
$route['api/display_profile/(:any)']	= 'api/Profile/display_profile/$1';
$route['api/display_posts']				= 'api/Profile/display_user_posts';
$route['api/display_critiques']			= 'api/Profile/display_user_critiques';
$route['api/change_profile']			= 'api/Profile/change_profile';
$route['api/change_password']			= 'api/Profile/change_password';
$route['api/get_notifs']				= 'api/Profile/get_notifs';
$route['api/read_notifs/(:any)']		= 'api/Profile/read_notifs/$1';
$route['api/logout']					= 'api/Profile/logout';

//Static
$route['api/email_suggestion']			= 'api/Static_data/email_suggestions';
$route['api/posts_per_hall']			= 'api/Static_data/posts_per_hall';
// $route['api/get_departments']			= 'api/Static_data/get_departments';
// $route['api/get_dept_special']			= 'api/Static_data/get_dept_special';
$route['api/search']					= 'api/Static_data/search';
$route['api/get_halls']					= 'api/Static_data/get_halls';
// $route['api/feedback']					= 'api/Static_data/feedback';

//Reports
$route['api/submit_report']				= 'api/Reports/submit_report';

//Admin Authentication
$route['api/admin/login']				= 'api/Admin_auth/login';
$route['api/admin/confirm_otp']			= 'api/Admin_auth/confirm_otp';
$route['api/admin/forgot_password'] 	= 'api/Admin_auth/forgot_password'; 			//Send forgot password to email
$route['api/admin/reset_password'] 		= 'api/Admin_auth/reset_password';

//Admin Functions
$route['api/admin/register']			= 'api/Admin_functions/register';
$route['api/admin/audit_logs/(:any)']	= 'api/Admin_functions/audit_logs/$1'; 		//Params = admin/user
$route['api/admin/get_reports/(:any)']	= 'api/Admin_functions/get_reports/$1'; 	//Params = new/ongoing/resolved
$route['api/admin/get_report/(:any)']	= 'api/Admin_functions/get_report/$1'; 		//Get specific report
$route['api/admin/get_accs/(:any)']		= 'api/Admin_functions/get_accs/$1'; 		//Params = admin/user
$route['api/admin/get_acc']				= 'api/Admin_functions/get_acc/'; 			//Get specific acc
$route['api/admin/super_admin/(:any)']  = 'api/Admin_functions/super_admin/$1'; 	//Promote/demote to super admin
$route['api/admin/disable_acc/(:any)']  = 'api/Admin_functions/disable_acc/$1'; 	//Enable/disable an admin acc
$route['api/admin/reply_report']		= 'api/Admin_functions/reply_report';
$route['api/admin/edit_user']			= 'api/Admin_functions/edit_user';
$route['api/admin/edit_admin']			= 'api/Admin_functions/edit_admin';
$route['api/admin/sanction_user']		= 'api/Admin_functions/sanction_user';
$route['api/admin/logout']				= 'api/Admin_functions/logout';

$route['default_controller'] 			= '';
$route['404_override'] 					= '';
$route['translate_uri_dashes'] 			= FALSE;