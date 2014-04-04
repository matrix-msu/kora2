<?php

function showRecoveryForm($errorMessage = '')
{
?>
	<h2><?php echo gettext('Account Registration');?></h2>
    <div id="formstyle" class="koraform">
		<?php  if (!empty($errorMessage)) echo '<div class="error">'.$errorMessage.'</div>';
?>
<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">

<label for="Username"><?php echo gettext('Username').':';?> <span class="small"><?php echo gettext('Reset Password');?></span></label>
<label for="Email"><input type="text" name="username" id="username" /></label>
<button name='submit' type="submit"><?php echo gettext('Send E-Mail');?></button>

</form>
<br />

<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
<label for="Email"><?php echo gettext('E-Mail Address').':';?> <span class="small"><?php echo gettext('Recover Username');?></span></label>
<input type="text" name="email" id="email" />
<button name='submit' type="submit"><?php echo gettext('Send E-Mail');?></button>
</form>

</div>

<?php     
}

require_once('includes/utilities.php');
require_once('includes/header.php');

// Check for POST data and process if necessary

if (isset($_POST['email']))
{
	// The user is trying to recover a username.  If such a user exists,
	// e-mail them their username
	
	// There is no LIMIT on this query in case multiple accounts are registered to the
	// same e-mail address
    $query = $db->query('SELECT username, email FROM user WHERE email='.escape($_POST['email']));
    
    if ($query->num_rows == 0)
    {
    	showRecoveryForm(gettext('There is no account with that e-mail address.'));
    }
    else
    {
    	$errorMsg = '';
    	
        while ($user = $query->fetch_assoc())
        {
            $messageSubject = gettext('KORA Account Information');
	        $message  = '<html><body><p>'.gettext('Your KORA Username is').': ';
	        $message .= '<b>'.$user['username'].'</b>';
	        $message .= "</p></body></html>\n";
	        
	        $headers = '';
	        $headers .= "MIME-Version: 1.0\r\n";
	        $headers .= "Content-type: text/html\r\n";
	        $headers .= "From: \"KORA Account\" <".baseEmail.">\r\n";
	        $headers .= "To: ".$user['email']."\r\n";
	        $headers .= "Reply-To: do-not-reply@example.com\r\n";
	
	        $mailSuccess = mail($user['email'], $messageSubject, $message, $headers);
	        if ($mailSuccess) {
	            $errorMsg = gettext('Your username has been sent to your e-mail address.');
	        } else {
	            $errorMsg = gettext('Attempt to send E-Mail failed; Please verify your mailserver configuration in php.ini');
	        }        	
        }
        
        showRecoveryForm($errorMsg);
    }
}
else if (isset($_POST['username']))
{
    // The user is trying to recover a forgotten password.  If such a user exists,
    // set a random activation token and e-mail them a link to reset their password.
    
    $query = $db->query('SELECT uid, username, email FROM user WHERE username='.escape($_POST['username']).' AND confirmed=1 LIMIT 1');
    
    if ($query->num_rows == 0)
    {
    	// **changed.  added a check for if the user is trying to change the language
        if(isset($_POST['action']) && $_POST['action']=='change language')
        	showRecoveryForm();
        else
        	showRecoveryForm(gettext('There is no activated account with that username.'));
    }
    else
    {
        $user = $query->fetch_assoc();    	
        
        // generate a random 16-character alphanumeric string
        $token = '';
        $validChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for($i=0; $i<16; $i++)
        {
        	$token .= $validChars[rand() % 62];
        }
        
        // Update the database; set the user's allowPasswordReset flag and resetToken
        $db->query('UPDATE user SET allowPasswordReset=1, resetToken='.escape($token).' WHERE uid='.$user['uid'].' LIMIT 1');
        
        $messageSubject = gettext('KORA Account Information');
        $message  = '<html><body><p>'.gettext('To change your password, visit the following link').': ';
        $message .= '<a href="'.baseURI.'resetPassword.php?username='.$user['username'].'&token='.$token.'">'.gettext('Reset Password').'</a><br />';
        $message .= gettext('Or copy/paste the following URL into your browser').': '.baseURI.'resetPassword.php?username='.$user['username'].'&token='.$token;
        $message .= "</p></body></html>\n";
            
        $headers = '';
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html\r\n";
        $headers .= "From: \"KORA Account\" <".baseEmail.">\r\n";
        $headers .= "To: ".$user['email']."\r\n";
        $headers .= "Reply-To: do-not-reply@example.com\r\n";
    
        $mailSuccess = mail($user['email'], $messageSubject, $message, $headers);
        if ($mailSuccess) {
            showRecoveryForm(gettext('Instructions on how to reset your password have been sent to your e-mail address.'));
        } else {
            showRecoveryForm(gettext('Attempt to send E-Mail failed; Please verify your mailserver configuration in php.ini'));
        }           
    }	
}
else
{
	showRecoveryForm();
}

require_once('includes/footer.php');

?>