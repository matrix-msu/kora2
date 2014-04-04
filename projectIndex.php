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

requireLogin();

// if a project has just been selected, set up the session variables
if (isset($_REQUEST['project_id']))
{
    // make sure the user is a member of the requested project
    $memberCheck = $db->query('SELECT uid FROM member WHERE uid='.$_SESSION['uid'].' AND pid='.escape($_REQUEST['project_id']).' LIMIT 1');
	
    if (($memberCheck->num_rows == 1) || isSystemAdmin())
    {
        $_SESSION['currentProject'] =  $_REQUEST['project_id'];
   
        // get the project name and store it in a session variable
        $pQuery = $db->query('SELECT name, styleid FROM project WHERE pid='.escape($_SESSION['currentProject']).' LIMIT 1');
        $pQuery = $pQuery->fetch_assoc();
	
        $_SESSION['currentProjectName'] = $pQuery['name'];
	   
        // clear any residual stylesheet info
        unset($_SESSION['currentProjectStyleSheet']);
        unset($_SESSION['lastIngestion']);

        // Only set the stylesheet variable if it's non-default.
        if ($pQuery['styleid'] > 0)
        {
            // Get the stylesheet filename
           $styleQuery = $db->query('SELECT filepath FROM style WHERE styleid='.$pQuery['styleid'].' LIMIT 1');
           $styleQuery = $styleQuery->fetch_assoc();
           $_SESSION['currentProjectStyleSheet'] = $styleQuery['filepath'];
       }
    }
}

requireProject();

// redirect to Scheme Selection
header('Location: selectScheme.php');
die();
?>
