<?php

/**
Copyright (2008) Matrix: Michigan State University

This file is part of KORA.

KORA is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

KORA is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>. */

// Initial Version: Matt Geimer, 2008

require_once('includes/conf.php');
require_once('includes/utilities.php');
requireLogin();

$errors = "";
$passUpdated = false;

if(isset($_POST['submit'])) {
	if(strlen($_POST['password1']) > 0 ) {
		if($_POST['password1'] != $_POST['password2'] && strlen($_POST['password1']) != 0) {
			$errors = gettext('Passwords do not match.');
		}
		elseif(strlen($_POST['password1']) < 8) {
			$errors = gettext('Password not long enough, must be at least 8 characters.');
		}
		else {   //passwords match and are of required length
			$salt = time();
			$sha256 = hash_init('sha256');
			hash_update($sha256,$_POST['password1']);
			hash_update($sha256,$salt);
			$pwhash = hash_final($sha256);
			$result = $db->query("UPDATE user SET organization=".escape($_POST['organization']).",realName=".escape($_POST['realName']).",salt=".$salt.",password='".$pwhash."',language=".escape($_POST['update_lang'])." WHERE uid=".$_SESSION['uid']." LIMIT 1 ");
			print $db->error;
			$passUpdated = true;
//			print gettext('Password updated')."!<br />";
		}
	}
	else {     //password not being updated
		$result = $db->query("UPDATE user SET organization=".escape($_POST['organization']).",realName=".escape($_POST['realName']).",email=".escape($_POST['email']).",language=".escape($_POST['update_lang'])." WHERE uid=".$_SESSION['uid']." LIMIT 1");
		print $db->error;
	}
}
$results = $db->query("SELECT * FROM user WHERE uid=".$_SESSION['uid']." LIMIT 1");
if(!$results) {
	print $db->error;
}
else {
	$array = $results->fetch_assoc();
	$_SESSION['language'] = $array['language'];
	include('includes/gettextSupport.php');		// need to include this to access $locale_list and
												// need it down here so the display language changes right away
	require_once('includes/header.php');	// header must be here for the language to change before a page refresh
	print $errors; 
	if($passUpdated) print gettext('Password updated')."!<br />";
	?>
<h2><?php echo gettext('Update User Information');?></h2>
	<form action='' method='post'>
	<table class="table_noborder">
	   <tr><td align="right"><?php echo gettext('Username').':';?></td><td><?php echo htmlEscape($array['username']);?></td></tr>
	   <tr><td align="right"><?php echo gettext('E-Mail').':';?></td><td><input type="text" name="email" value="<?php echo htmlEscape($array['email']);?>" /></td></tr>
	   <tr><td align="right"><?php echo gettext('Real Name').':';?></td><td><input type='text' name='realName' value='<?php echo htmlEscape($array['realName']);?>' /></td></tr>
	   <tr><td align="right"><?php echo gettext('Organization').':';?></td><td><input type='text' name='organization' value='<?php echo htmlEscape($array['organization']);?>' /></td></tr>
	   <tr><td align="right"><?php echo gettext('New Password').':';?><br />(<?php echo gettext('Leave blank if not changing');?>)</td><td><input type='password' name='password1' /></td></tr>
	   <tr><td align="right"><?php echo gettext('Confirm New Password').':';?></td><td><input type='password' name='password2' /></td></tr>
	   <tr><td align="right"><?php echo gettext('Language').':'?></td>
	   <td><select name = "update_lang">
	   <?php foreach($locale_list as $key => $value)
    		  {
    			echo "<option value=\"$key\"";
    			if($key == $array['language']) echo " selected";
    			echo ">$value</option>";
    	  	  } ?> </select></td></tr>
	   <tr><td colspan="2" align="right"><input type='submit' value='<?php echo gettext('Update Information');?>' name='submit' /></td></tr>
	</table>
	</form>	
	<?php 	
}

require_once('includes/footer.php');
?>
