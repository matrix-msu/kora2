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
requirePermissions(EDIT_LAYOUT,"schemeLayout.php");

$errorMessage = '';

if(isset($_POST['submit'])) {
	$required = 0;
    $searchable = 0;
    $showinresults = 0;
    $showinpublic = 0;
    $publicentry = 0;
    $advSearchable = 0;
    if(isset($_POST['required']) && $_POST['required'] == "on")
    {
        $required = 1;
    }
    if(isset($_POST['searchable']) && $_POST['searchable'] == "on")
    {
        $searchable = 1;
    }
	if(isset($_POST['advSearchable']) && $_POST['advSearchable'] == "on")
    {
        $advSearchable = 1;
    }
    if(isset($_POST['showinresults']) && $_POST['showinresults'] == "on")
    {
        $showinresults = 1;
    }
    if(isset($_POST['showinpublicresults']) && $_POST['showinpublicresults'] == "on")
    {
        $showinpublic = 1;
    }
    if(isset($_POST['publicEntry']) && $_POST['publicEntry'] == "on")
    {
        $publicentry = 1;
    }
    
    // Verify that the new name is valid
    if (!isset($_POST['name']) || empty($_POST['name']))
    {
    	// Make sure it's not blank
    	$errorMessage = gettext('You must provide a name for the control.');
    }
    else if (in_array(strtoupper($_POST['name']), $invalidControlNames))
    {
    	// Make sure it's not on the list of censored keywords
    	$errorMessage = '"'.$_POST['name'].'" '.gettext('is not a valid control name').'.';
    }
    else
    {
        $nameQuery  = 'SELECT cid FROM p'.$_SESSION['currentProject'].'Control ';
        $nameQuery .= 'WHERE schemeid='.escape($_SESSION['currentScheme']);
        $nameQuery .= ' AND name='.escape(trim($_POST['name'])).' AND cid != '.escape($_POST['cid']);
        $nameQuery .= ' LIMIT 1';
        $nameQuery = $db->query($nameQuery);
        if ($nameQuery->num_rows != 0) {
            $errorMessage = gettext('That name is already used by another control in this scheme.');
        }
    }
        
    if (empty($errorMessage))
    {
	    $db->query('UPDATE p'.$_SESSION['currentProject'].'Control set name='.escape(trim($_POST['name'])).',description='.escape($_POST['description']).',required='.$required .
	    ',searchable='.$searchable.',advSearchable='.$advSearchable.',showInResults='.$showinresults.',showInPublicResults='.$showinpublic.',publicEntry='.$publicentry.' where cid='.escape($_POST['cid']));
		header("Location: schemeLayout.php?");
    }
}
require_once('includes/header.php');
echo "<h2>".gettext('Edit control in')." $_SESSION[currentProjectName], ".gettext('scheme')." $_SESSION[currentSchemeName]</h2><br />";
$cTable = 'p'.$_SESSION['currentProject'].'Control';

$query = "SELECT $cTable.name AS name, $cTable.description AS description, $cTable.required AS required, $cTable.searchable AS searchable, $cTable.advSearchable AS advSearchable, $cTable.showInResults AS showInResults, $cTable.showInPublicResults AS showInPublicResults, $cTable.publicEntry AS publicEntry, control.name AS type FROM $cTable LEFT JOIN control ON ($cTable.type = control.class) WHERE cid=".escape($_REQUEST['cid']).' LIMIT 1';
$result = $db->query($query);
$array = $result->fetch_assoc();
 
if (!empty($errorMessage))
{
	echo '<div class="error">'.gettext($errorMessage).'</div>';
}

$unsupportedAdvSearch = array('File','Image','Record Associator','Geolocator');
$unsupportedPublicEntry = array('Record Associator','Geolocator');

?>
<form action="" method="post">
<table>
        <tr><td><?php echo gettext('Type');?>:</td><td><?php echo $array['type']; ?></td></tr>
        <tr><td><?php echo gettext('Name');?>:*</td><td><input type="text" name="name" value="<?php echo htmlEscape($array['name']);?>"/></td></tr>
        <tr><td><?php echo gettext('Description');?>:</td><td><textarea name="description" cols="20" rows="3"><?php echo htmlspecialchars($array['description']);?></textarea></td></tr>
        <?php if(!in_array($array['type'],$unsupportedPublicEntry)) {?>
        <tr><td><?php echo gettext('Required');?>?</td><td><input type="checkbox" name="required" <?php if($array['required']) echo 'checked="yes"';?> /></td></tr>
        <?php }?>
        <tr><td><?php echo gettext('Searchable');?>?</td><td><input type="checkbox" name="searchable" <?php if($array['searchable']) echo 'checked="yes"';?> /></td></tr>
        <?php if(!in_array($array['type'],$unsupportedAdvSearch)) {?>
        <tr><td><?php echo gettext('Advanced Searchable');?>?</td><td><input type="checkbox" name="advSearchable" <?php if($array['advSearchable']) echo 'checked="yes"';?> /></td></tr>
        <?php }?>
        <tr><td><?php echo gettext('Show in results');?>?</td><td><input type="checkbox" name="showinresults" <?php if($array['showInResults']) echo 'checked="yes"';?> /></td></tr>
		<?php if(!in_array($array['type'],$unsupportedPublicEntry)) {?>
		<tr><td><?php echo gettext('Public Ingest');?>?</td><td><input type="checkbox" name="publicEntry" <?php if($array['publicEntry']) echo 'checked="yes"';?> /></td></tr>
        <?php }?>
        <tr><td>
        </td><td>
        <input type="submit" name="submit" value="<?php echo gettext('Update Control');?>" /></td></tr>
    </table>
    <input type="hidden" name="cid" value="<?php echo $_REQUEST['cid'];?>" />
    </form>
<?php
echo '*'.gettext('Updating the Name can break front-end programming that has been completed.  Make certain you are doing what you intended.');

require_once('includes/footer.php');

?>
