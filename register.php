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

// Initial Version: Brian Beck, 2008

/**
 * register.php - page for creating a new account
 */
require_once('includes/conf.php');
require_once('includes/utilities.php');
include('includes/gettextSupport.php'); // need to include this to access $locale_list

function showRegistrationForm($errorMessage = '') {

    include_once('includes/header.php');
  
    ?>
    <script type="text/javascript">
 // <![CDATA[
function checkUsername() {
//    new Ajax.Updater('unamecheck', 'includes/userFunctions.php', {parameters:{ action:"checkUsername",source:'UserFunctions', uname:$('username').value }});
	$.post('includes/userFunctions.php',{ action:"checkUsername",source:'UserFunctions', uname:$('input#username').val() },function(resp){$("#unamecheck").html(resp);}, 'html');
}

//]]>
</script>
	<!-- start form for registration -->
	<h2><?php echo gettext('Account Registration');?></h2>
    <div id="formstyle" class="koraform">
		<?php  if (!empty($errorMessage)) echo '<div class="error">'.$errorMessage.'</div>';?>
        
        <form action='' method='post'>
        <label for="username"><?php echo gettext('Username').':'?> <span class="small">add a username</span></label>
        <input type='text' name='username' id='username' onchange='checkUsername()'  <?php  if (isset($_POST['username'])) echo 'value="'.$_POST['username'].'"';?> />
        <div id="unamecheck"></div>
        <div style="clear: both;"></div>
        <label for="password1"><?php echo gettext('Password').':'?> <span class="small">8 characters or more</span></label>
        <input type='password' id='password1' name='password1' />
        <label for="password2"><?php echo gettext('Verify Password').':'?> <span class="small">retype password</span></label>
        <input type='password' id='password2' name='password2' />
        <div style="clear: both;"></div>
        <label for="email"><?php echo gettext('Email address').':'?> <span class="small">add a valid address</span></label>
        <input type='text' id='email' name='email' <?php  if (isset($_POST['email'])) echo 'value="'.$_POST['email'].'"';?> />
        <label for="realname"><?php echo gettext('Full Name').':'?> <span class="small">(optional)</span></label>
        <input type='text' id='realname' name='realname' <?php  if (isset($_POST['realname'])) echo 'value="'.$_POST['realname'].'"';?> />
        <label for="organization"><?php echo gettext('Organization').':'?> <span class="small">(optional)</span></label>
        <input type='text' id='organization' name='organization' <?php  if (isset($_POST['organization'])) echo 'value="'.$_POST['organization'].'"';?> />
        <label for="set_default_lang"><?php echo gettext('Language').':'?> <span class="small">(optional)</span></label>
        <select name = "set_default_lang">
            <?php foreach($locale_list as $key => $value)
                  {
                    echo "<option value=\"$key\"";
                    if(isset($_POST['set_default_lang']) && ($key == $_POST['set_default_lang'])) echo " selected";
                    echo ">$value</option>";
                  } ?> </select>
        <button name='submit' type="submit"><?php echo gettext('Create Account');?></button>
        
        </form>
    </div>        
    <?php 
    include_once('includes/footer.php');
}

if(isset($_POST['submit'])) {
	
	$errors = '';
	
	// check for Username Availability
	$results = $db->query("SELECT username FROM user WHERE username=".escape($_POST['username']));
	if (!$results) { 
		error_log($db->error);
	}
	if ($results->num_rows != 0)
	{
		$errors = gettext('That username is already taken.');
	}
	else if ($_POST['password1'] != $_POST['password2'])
	{
		$errors = gettext('Your Passwords do not Match.');
	}
	else if (empty($_POST['username']))
	{
		$errors = gettext('Your username is blank.');
	}
	else if (strlen($_POST['password1']) < 8)
	{
		$errors = gettext('Please make your password at least 8 characters.');
	}
	else if (empty($_POST['email']))
	{
		$errors = gettext('You did not supply an e-mail address.');
	}
	if (!empty($errors))
	{
		showRegistrationForm($errors);
	}
	else
	{
	    include_once('includes/header.php');
		// add user, send confirmation e-mail
		$salt = time();
		$sha2 = hash_init('sha256');
		hash_update($sha2,$_POST['password1']);
		hash_update($sha2,$salt);
		$pwhash = hash_final($sha2);
		
		$query = 'INSERT INTO user (username, password, salt, email, realName, admin, organization, language, confirmed, searchAccount) ';
		$query .= ' VALUES ('.escape($_POST['username']).", '".$pwhash."', $salt, ";
		$query .= escape($_POST['email']).', '.escape($_POST['realname']).", '0', ";
		$query .= escape($_POST['organization']).', '.escape($_POST['set_default_lang']).", '0', '0')";
		
		$db->query($query);
		
		echo '<h2>'.gettext('Account Creation').'</h2>';
		echo gettext('Your account has been created').'.  ';
		
		// Compose the E-Mail
		
		$messageSubject = gettext('KORA Account Activation');
		$message = '<html><body><p>'.gettext('In order to access your KORA account, you must verify your e-mail address').'.  ';
		$message .= gettext('To do this, click on the link below or go to the "Activate Account" page').', ';
		$message .= gettext('type in your username, and enter the following number').": $salt\n\n";
		$message .= "<a href=\"".baseURI.'activate.php?username='.$_POST['username']."&token=$salt\">".gettext('Activate Your Account')."</a></p></body></html>\n";
		
		$headers = '';
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html\r\n";
		$headers .= "From: \"KORA Activation\" <".baseEmail.">\r\n";
		$headers .= "To: ".$_POST['email']."\r\n";
		$headers .= "Reply-To: do-not-reply@example.com\r\n";

		$mailSuccess = mail($_POST['email'], $messageSubject, $message, $headers);
		if ($mailSuccess) {
			  echo gettext('Please check your e-mail for instructions on how to').' <a href="activate.php">'.gettext('Activate Your Account').'.</a>';
		} else {
			echo gettext('Attempt to send E-Mail failed; Please verify your mailserver configuration in php.ini');
		}
		include_once('includes/footer.php');
	}
	
}
elseif(isLoggedIn()){
	header('Location: index.php');
}
else { 
	showRegistrationForm();
}

?>