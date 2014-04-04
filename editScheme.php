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

requireProject();
requirePermissions(CREATE_SCHEME, 'selectScheme.php');

$error = '';

if (isset($_POST['schemeName']) && isset($_POST['description']) && isset($_POST['sid']))
{
	// Validate the scheme id
	$sid = (int) $_POST['sid'];
    $existenceQuery = $db->query('SELECT schemeid, schemeName, description FROM scheme WHERE pid='.$_SESSION['currentProject'].' AND schemeid='.$sid.' LIMIT 1');
    $nameConflictQuery = $db->query('SELECT schemeid FROM scheme WHERE pid='.$_SESSION['currentProject'].' AND schemeName='.escape($_POST['schemeName']).' AND schemeid != '.$sid.' LIMIT 1');
    if ($existenceQuery->num_rows == 0)
    {
        $error = gettext('Invalid Scheme ID');
    }
    else if ($nameConflictQuery->num_rows > 0)
    {
    	$error = gettext('A scheme with that name already exists');
    }
    else
    {
    	//check if checkbox was checked
    	$public = 0;
    	if(isset($_POST['publicIngestion']))
    	{
    		$public = 1;
    	}
    	
    	$query  = 'UPDATE scheme SET schemeName='.escape($_POST['schemeName']);
    	$query .= ', description='.escape($_POST['description']);
    	$query .= ', publicIngestion='.$public;
    	$query .= ', legal='.escape($_POST['legal']);
    	$query .= ' WHERE pid='.$_SESSION['currentProject'].' AND schemeid='.$sid.' LIMIT 1';
    	$db->query($query);
    	header('Location: selectScheme.php');
    	die();
    }
}

require_once('includes/header.php');

if (!isset($_REQUEST['sid']))
{
	echo gettext('No Scheme ID Provided.');
	require_once('includes/footer.php');
	die();
}

// Cast to Integer to sanitize for database use
$sid = (int) $_REQUEST['sid'];
$existenceQuery = $db->query('SELECT schemeid, schemeName, description, publicIngestion, legal FROM scheme WHERE pid='.$_SESSION['currentProject'].' AND schemeid='.$sid.' LIMIT 1');
if (!$existenceQuery || $existenceQuery->num_rows < 1)
{
	echo gettext('Invalid Scheme ID');
    require_once('includes/footer.php');
    die();	
}

$schemeInfo = $existenceQuery->fetch_assoc();

echo '<h2>'.gettext('Edit Scheme Info').'</h2>';

if (!empty($error))
{
	echo '<div class="error">'.gettext($error).'</div><br />';
}
?>
<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
<input type="hidden" name="sid" value="<?php echo $schemeInfo['schemeid'];?>" />
<table class="table_noborder">
    <tr><td align="right"><?php echo gettext('Name');?>:</td><td><input type="text" name="schemeName" value="<?php echo htmlEscape($schemeInfo['schemeName']);?>" /></td></tr>
    <tr><td align="right"><?php echo gettext('Description');?>:</td><td><textarea name="description"><?php echo $schemeInfo['description'];?></textarea></td></tr>
    <tr><td align="right"><?php echo gettext('Public Ingestible?');?>:</td><td><input type="checkbox" name="publicIngestion" <?php if($schemeInfo['publicIngestion']==1)echo 'checked="checked"';?>/></td></tr>
    <tr><td align="right"><?php echo gettext('Legal Notice');?>:</td><td><textarea name="legal" ><?php echo $schemeInfo['legal'];?></textarea></td></tr>
    <tr><td colspan="2" align="right"><input type="submit" value="<?php echo gettext('Edit Scheme');?>" /></td></tr>
</table>        
</form>
<?
require_once('includes/footer.php');
?>