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

require_once('includes/utilities.php');


if (!isset($_REQUEST['rid']))
{
	header('Location: schemeLayout.php');
	die();
}
else $rid = $_REQUEST['rid'];

$ridInformation = parseRecordID($rid);

// Make sure the user has permissions to delete this object
if (!hasPermissions(DELETE_RECORD, $ridInformation['project']))
{
    header('Location: schemeLayout.php');
    die();	
}

include_once('includes/header.php');

echo '<h2>'.gettext('Delete Record').': '.$_REQUEST['rid'].'</h2>';

echo gettext('Warning').': '.gettext('This will permanently delete this record and all data associated with it').'.  ';
echo gettext('Are you sure you really want to delete this record?').'<br /><br />';

// see if anything associates to this object
$assocQuery = $db->query('SELECT * FROM p'.$ridInformation['project'].'Data WHERE id='.escape($rid).' AND cid=0 LIMIT 1');
if ($assocQuery->num_rows > 0)
{
    echo '<div class="error">'.gettext('Warning').': '
    	.gettext('At least one record associates to this record.  Any such associations will be lost if this record is deleted.')
    	.'</div><br /><br />';
}

echo '<table border="0"><tr>';
echo '<td><form action="deleteObject2.php"><input type="hidden" value="'.$rid.'" name="rid" /><input type="submit" value="'.gettext('Yes').'" /></form></td>';
echo '<td><form action="viewObject.php"><input type="hidden" value="'.$rid.'" name="rid" /><input type="submit" value="'.gettext('No').'" /></form></td>';
echo '</tr></table>';

include_once('includes/footer.php');
?>