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

require_once('includes/conf.php');
require_once('includes/utilities.php');

function showLoginForm($errorMessage = '')
{
    include_once('includes/header.php');
    
    echo '<h2>'.gettext('Log In to KORA').'</h2>';
    
    ?>
<script type="text/javascript">$(document).ready(function(){$('input[type="text"]').focus();});</script>
<div id="formstyle" class="koraform">
<?php  if (!empty($errorMessage)) echo '<div class="error">'.gettext($errorMessage).'</div><br/>'; ?>
	<form action='<?php echo $_SERVER['PHP_SELF']?>' method='post'>

        <label for="Username"><?php echo gettext("Username").":";?></label>
        <input type="text" name="username" id="textfield" <?php  if (isset($_POST['username'])) echo 'value="'.$_POST['username'].'"';?>/>
        <label for="Password"><?php echo gettext("Password").":";?></label>
        <input type="password" name="password" id="textfield" />
        <button  type="submit"><?php echo gettext('Log In');?></button>
    </form>
	<form action='register.php' method='post'>
    	<button  type="submit"><?php echo gettext('New Account');?></button>
	</form>
	<form action='activate.php' method='post'>
    	<button  type="submit"><?php echo gettext('Activate Account');?></button>
	</form>
	<div class="spacer"></div>
    <div class="forgot"><a href="recoverPassword.php"><?php echo gettext('I forgot my username/password');?></a></div>
</div>
	<?php
    include_once('includes/footer.php');
}

/// Check to ensure proper form was submitted
if(!isset($_SESSION['uid']))
{


    if(!isset($_POST['username'])&& !isset($_POST['password'])&& !isset($_POST['submit']))
	{
	    showLoginForm();
    }
    else
    {
        /// Insert the record into the database
        
        // Begin Hash Calculation
        $sha256 = hash_init('sha256');
     	$results = $db->query("SELECT salt FROM user WHERE username=".escape($_POST['username'])." LIMIT 1");
     	if(!$results){
     		print $db->error;
     	}
     	else {
     		$array = $results->fetch_assoc();
     	}
     	hash_update($sha256,$_POST['password']);
     	hash_update($sha256,$array['salt']);
     	$passwd = hash_final($sha256);
     	// End Hash Calculation
     	
     	// Compare the Computed Hash to that in the Database
		$results = $db->query("SELECT uid,admin,confirmed FROM user WHERE username=".escape($_POST['username'])." AND password='".$passwd."' LIMIT 1");
     	//$results = $db->query("SELECT uid,admin,confirmed,language FROM user WHERE username=".escape($_POST['username'])." AND password='".$passwd."' LIMIT 1");
        if(!$results)
            print $db->error;
        else {
        	$array = $results->fetch_assoc();
        	if (!empty($array))
        	{
        	    if (!$array['confirmed']) {
        	        showLoginForm(gettext('Your account needs to be activated.'));
        	    } else {
        	        @session_start();
        	        if(!isset($_SESSION['base_Path'])){
    					$_SESSION['base_Path'] = basePath;
    				}
	        	        $_SESSION['uid'] = $array['uid'];
	            	    $_SESSION['admin'] = $array['admin'];
	            	    //$_SESSION['language'] = $array['language'];
	            	    $_SESSION['language'] = 'English';
	            	    
	            	    // Check for the database version number.  If the query fails or returns no row,
	            	    // set the variable to 0
	            	    @$versionQuery = $db->query('SELECT version FROM systemInfo WHERE baseURL='.escape(baseURI).' LIMIT 1');
	            	    if (!$versionQuery || $versionQuery->num_rows == 0)
	            	    {
	            	    	$_SESSION['dbVersion'] = '0';
	            	    }
	            	    else
	            	    {
	            	    	$versionQuery = $versionQuery->fetch_assoc();
	            	    	$_SESSION['dbVersion'] = $versionQuery['version'];
	
	            	    }
	        	
	                	// Header redirect to index
	                	header('Location: index.php');
        	        }
        	    
        	}
        	else
        	{
        		// **changed.  added a check for if the user is trying to change the language
        		if(isset($_POST['action']) /*&& $_POST['action']==gettext('change language')*/)
        			showLoginForm();
        		else
                	showLoginForm(gettext('Username/Password Incorrect'));
        	}
        }
     }
} //end if !$_SESSION['uid']
else {
    // already logged in, so redirect to the index
    header('Location: index.php');
}

?>
