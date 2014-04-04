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

// Make sure an rid is passed
if (!isset($_REQUEST['rid']))
{
    header('Location: schemeLayout.php');
    die();
}
else $rid = $_REQUEST['rid'];

$ridInformation = parseRecordID($rid);
$pid = $ridInformation['project'];

// Make sure the user has permissions to delete this object
if (!hasPermissions(DELETE_RECORD, $pid))
{
    header('Location: schemeLayout.php');
    die();  
}

// First, the obvious part: Instantiate all of its controls and let them delete themselves.
// This allows for pleasant things like files and thumbnails actually getting deleted with the
// record; imagine that! 

$dataTable = 'p'.$pid.'Data';
$controlTable = 'p'.$pid.'Control';

$controlQuery  = "SELECT DISTINCT $dataTable.cid AS cid, $controlTable.type AS class, control.file AS file ";
$controlQuery .= "FROM $dataTable LEFT JOIN $controlTable USING (cid) LEFT JOIN control ON ($controlTable.type = control.class) ";
$controlQuery .= "WHERE $dataTable.id = ".escape($rid).' AND cid != 0'; 
$controlQuery = $db->query($controlQuery);

while($control = $controlQuery->fetch_assoc())
{
    require_once(basePath.CONTROL_DIR.$control['file']);
    $theControl = new $control['class']($pid, $control['cid'], $rid);
    $theControl->delete();
}

// Also remove any references to the record in the object preset table
$db->query('DELETE FROM recordPreset WHERE kid='.escape($rid));

// And Dublin Core
$db->query('DELETE FROM dublinCore WHERE kid='.escape($rid).' LIMIT 1');

// Clean the associations
cleanUpAssociatorOnDelete($rid);

header('Location: schemeLayout.php');
die();

?>