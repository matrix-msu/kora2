<?php
/*
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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Initial Version: Brian Beck, 2008

require_once('includes/utilities.php');

// check for authorization to access this page
requireScheme();
requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');

$query  = 'SELECT name, description, schemeid FROM collection WHERE collid='.escape($_REQUEST['cid']).' LIMIT 1';

// Make sure the Collection ID is valid
$query = $db->query($query);
if ($query->num_rows != 1) header('location:schemeLayout.php');
else {
$collectionInfo = $query->fetch_assoc();
if ($collectionInfo['schemeid'] != $_SESSION['currentScheme']) header('Location: schemeLayout.php');
else
{
	if ( isset($_POST['editCollection']) ) {
		// update the database
		$query  = 'UPDATE collection SET name='.escape($_POST['name']).', description='.escape($_POST['description']);
		$query .= ' WHERE collid='.escape($_POST['cid']);
		$db->query($query);
		
		header('Location: schemeLayout.php');
	}

	// otherwise display the form
	
    include_once('includes/header.php');
	
	echo '<h2>'.gettext('Edit Collection Properties for').' '.htmlEscape($collectionInfo['name']).'</h2>';
	?>

    <form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
	<table class="table_noborder">
	   <tr>
	       <td align="right"><?php echo gettext('Name');?>:</td>
	       <td><input type="text" name="name" value="<?php echo htmlEscape($collectionInfo['name'])?>" /></td>
	   </tr>
	   <tr>
	       <td align="right"><?php echo gettext('Description');?>:</td>
	       <td><textarea name="description" cols="40" rows="5"><?php echo htmlEscape($collectionInfo['description'])?></textarea></td>
	   </tr>
	<tr><td colspan="2" align="right">
	<input type="hidden" name="cid" value="<?php echo htmlEscape($_REQUEST['cid'])?>" />
    <input type="hidden" name="editCollection" value="true" />
	<input type="submit" value="<?php echo gettext('Submit Changes');?>" />
	</td></tr></table>
    </form>
	
	<?php 
	
    include_once('includes/footer.php');	
}
}


?>