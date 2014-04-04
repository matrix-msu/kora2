<?php
require_once('includes/utilities.php');
require_once('includes/header.php');

function showResetForm($errorMessage = '')
{
 
echo '<h2>'.gettext('Reset Password').'</h2>';

if (!empty($errorMessage)) echo '<div class="error">'.gettext($errorMessage).'</div>'; ?>

<script type="text/javascript">
function resetPassword()
{
    var form = $('#resetForm').serialize();
    var pw1 = $('#password1').val();
    var pw2 = $('#password2').val();

    if (pw1 == pw2)
    {
        if (pw1.length >= 8)
        {
            document.resetForm.submit();
        }
        else
        {
            alert(<?php echo "'".gettext('Password must be at least 8 characters.')."'";?>);
        }
    }
    else
    {
        alert(<?php echo "'".gettext('Passwords do not match.')."'";?>);
    }
}
</script>

<form name="resetForm" id="resetForm" action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
<input type="hidden" name="resetpw" id="resetpw" value="resetpw" />
<table class="table">
    <tr>
        <td><strong><?php echo gettext('Username');?></strong></td>
        <td><input type="text" name="username" id="username" <?php 
            if (isset($_REQUEST['username'])) echo ' value="'.$_REQUEST['username'].'"'; ?> /></td>
    </tr>
    <tr>
        <td><strong><?php echo gettext('Token');?></strong></td>
        <td><input type="text" name="token" id="token" <?php 
            if (isset($_REQUEST['token'])) echo ' value="'.$_REQUEST['token'].'"'; ?> /></td>
    </tr>    
    <tr>
        <td><strong><?php echo gettext('Password');?></strong></td>
        <td><input type="password" name="password1" id="password1" /></td>
    </tr>
    <tr>
        <td><strong><?php echo gettext('Confirm Password');?></strong></td>
        <td><input type="password" name="password2" id="password2" /></td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td><input type="button" onclick="resetPassword();" value="<?php echo gettext('Reset Password');?>" /></td>
    </tr>
</table>
</form>
<?php 	
}

if (isset($_POST['resetpw']))
{
	if (isset($_POST['username']) && isset($_POST['token']) && isset($_POST['password1']) && isset($_POST['password2']))
	{
		if ($_POST['password1'] == $_POST['password2'])
		{
			if (strlen($_POST['password1']) >= 8)
			{
    			// Update the database
			
	       		$salt = time();
                $sha256 = hash_init('sha256');
                hash_update($sha256, $_POST['password1']);
                hash_update($sha256, $salt);
                $pwhash = hash_final($sha256);  
                $db->query('UPDATE user SET password='.escape($pwhash).', salt='.escape($salt).',allowPasswordReset=0 WHERE username='.escape($_POST['username']).' LIMIT 1');
            
                echo gettext('Password reset').'.  '.gettext('Please').' <a href="login.php">'.gettext('Log In').'</a>';
			}
			else
			{
				showResetForm(gettext('Password must be at least 8 characters.'));
			}
		}
		else
		{
			showResetForm(gettext('Passwords do not match.'));
		}
	}
}
else
{
    showResetForm();
}

require_once('includes/footer.php');
?>