<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Critiquehall - Reset Password</title>
</head>
<body>
	<center><img src='https://firebasestorage.googleapis.com/v0/b/critique-hall.appspot.com/o/critiquehall.png?alt=media&token=5782afbb-5316-49c9-bac0-61d5fd4b03a4' style="width: 180px; height: 120px"><br><br>

	<br><label><b style="font-size: 18px">Click the button below to reset your password</b></label><br><br>
	
	<a href="<?= $reset_pass_link; ?>"><button class="btn btn-outline-primary btn-lg" style="width:auto; font-size: 24px; display:inline-block; padding:0.7em 1.4em; margin:0 0.3em 0.3em 0; border-radius:0.15em; box-sizing: border-box; text-decoration:none; font-weight:400; color:#FFFFFF; background-color:#3369ff; box-shadow:inset 0 -0.6em 0 -0.35em rgba(0,0,0,0.17); text-align:center; position:relative; cursor: pointer">Reset Password</button></a><br><br>

	<span>This link will expire on <b><?= date("M d, Y g:iA", strtotime($token_exp)); ?></b></span>
	</center>
</body>
</html>