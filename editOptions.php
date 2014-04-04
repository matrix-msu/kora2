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

require_once('includes/utilities.php');

// Initial Version: Brian Beck, 2008

// A scheme must be selected to use this page
requireScheme();
requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');
// make sure a control ID has been passed
if (!isset($_REQUEST['cid'])) header('Location: schemeLayout.php');

require_once('includes/header.php');

// check to make sure the information passed is valid
$controlTable = 'p'.$_SESSION['currentProject'].'Control';

$query = "SELECT control.file AS file, $controlTable.type AS type, $controlTable.name AS name FROM $controlTable LEFT JOIN control ON $controlTable.type = control.class WHERE $controlTable.cid = ".escape($_REQUEST['cid']).' LIMIT 1';
$query = $db->query($query);
if ($query->num_rows != 1) echo gettext('Invalid Control ID Specified');
else {

    // get the information
    $controlInfo = $query->fetch_assoc();
    // include the file for the class

    if (!empty($controlInfo['file']))
    {
    
        require_once(basePath.CONTROL_DIR.$controlInfo['file']);
        // instantiate the control
        $theControl = new $controlInfo['type']($_SESSION['currentProject'], $_REQUEST['cid']);

        // display the form
    
        echo '<h2>'.gettext('Editing Options for ').htmlEscape($controlInfo['name']).'</h2>';
            $theControl->displayOptionsDialog();
    }
    else
    {
	    echo gettext('Could not load control of this type.  Please ensure all controls are properly installed.');
    }
    
}

require_once('includes/footer.php');

?>
