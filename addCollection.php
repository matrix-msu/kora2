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

requireScheme();
requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');

function showNewCollectionForm($errors = '')
{
    include_once('includes/header.php');

    echo '<h2>'.gettext('Add New Collection to ').$_SESSION['currentSchemeName'].'</h2>';
    if(!empty($errors)) echo '<div class="error">'.gettext($errors).'</div>'; ?>
    <form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
    <input type="hidden" name="addGroup" value="true" />
    <table class="table_noborder">
        <tr><td><?php echo gettext('Name');?>:</td><td><input type="text" name="collName" <?php  if(isset($_POST['collName'])) echo ' value="'.htmlEscape($_POST['collName']).'" ';?> /></td></tr>
        <tr><td><?php echo gettext('Description');?>:</td><td><textarea name="description"><?php  if(isset($_POST['description'])) echo htmlEscape($_POST['description']);?></textarea></td></tr>
        <tr><td colspan="2"><input type="submit" value="<?php echo gettext('Create New Collection');?>" /></td></tr>
    </table>        
    </form>    
<?php 
    include_once('includes/footer.php');      
}

if(isset($_POST['addGroup']))
{
    if (empty($_POST['collName'])) {
        showNewCollectionForm(gettext('You must provide a name.'));
        die();
    }
    else {
        $query = "INSERT INTO collection (schemeid, name, sequence, description) ";
        $query .= "SELECT ".escape($_SESSION['currentScheme']).", ";
        $query .= escape($_POST['collName']).", COUNT(sequence) + 1, ";
        $query .= escape($_POST['description'])." FROM collection ";
        $query .= "WHERE schemeid=".escape($_SESSION['currentScheme']);
        $result = $db->query($query);
        
        if(!$result) showNewCollectionForm($db->error);
        else {
            header('Location: schemeLayout.php');
        }
    }
	
	header('Location: schemeLayout.php');
}
else showNewCollectionForm();

?>
