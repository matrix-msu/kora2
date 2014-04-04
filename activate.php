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

/**
 * Activate.php: Activation of Newly Registered Accounts
 * 
 * Author: Brian Beck
 */

require_once('includes/conf.php');
require_once('includes/utilities.php');

function showActivationForm($errorMessage = '')
{
    // Show the form
    include_once('includes/header.php');

 echo '<h2>'.gettext('Activate your Account').'</h2>';
?>

<div id="formstyle" class="koraform">
<?php  if (!empty($errorMessage)) echo '<div class="error">'.$errorMessage.'</div>'; ?>
<form action="<?php echo $_SERVER['PHP_SELF']?>" method="get">

	<label for="Username"><?php echo gettext('Username').':';?><span class="small">previously created</span></label>
    <input type='text' name='username' <?php  if (isset($_GET['username'])) echo 'value="'.$_GET['username'].'"';?> />
    
	<label for="Username"><?php echo gettext('Token').':';?><span class="small">token sent to email</span></label>
    <input type='text' name='token' <?php  if (isset($_GET['token'])) echo 'value="'.$_GET['token'].'"';?> />
    
	<button name="submit" type="submit"><?php echo gettext('Activate Account');?></button> 
</form>
</div>

<?php     
    include_once('includes/footer.php');   
    
}

// Logged-in users have no need of activation
if (isLoggedIn()) header('Location: index.php');

// see if the proper form was submitted
if (!isset($_GET['username']) || !isset($_GET['token']))
{
    showActivationForm();
}
else
{
    // attempt to activate the account
    
    // see if it's a valid username/token combination
    $query_result = $db->query("SELECT username FROM user WHERE username=".escape($_GET['username'])." AND salt=".escape($_GET['token'])." LIMIT 1");
    $results =& $query_result;
     	if(!$results){
     		showActivationForm($db->error);
     	}
     	else {
     		$array = $results->fetch_assoc();
     		
     		// If valid, activate the account.  If not, show an error message.
     		if (!empty($array)) {
     			$updatedQueryResults = $db->query("UPDATE user SET confirmed='1' WHERE username=".escape($_GET['username'])." AND salt=".escape($_GET['token']));
     		    $updatedResults =& $updatedQueryResults;

     		    include_once('includes/header.php');
     		    echo gettext('Your account has been activated').'.  '.gettext('Please').' <a href="login.php">'.gettext('Log In').'</a>.';
     		    include_once('includes/footer.php');
     		} else {
     		    showActivationForm(gettext('Username/Token Incorrect'));
     		}
     	}
    
}
?>