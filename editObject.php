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

// Overall, this page should function and look very, very close to ingestObject.php
// the only difference should be that when instantiating controls, the record ID is
// passed as an argument.

require_once('includes/ingestionClass.php');

// show the initial edit form if nothing was submitted
if (isset($_POST['ingestionForm']))
{
    if (!isset($_POST['recordid']))
    {
        header('Location: schemeLayout.php');
        die();
    }
    else
    {
        $rid = $_POST['recordid'];
    }
}
else
{    
    // Make sure a Record ID was passed
    if (!isset($_REQUEST['rid']))
    {
        header('Location: schemeLayout.php');
        die();
    }
    else
    {
        $rid = $_REQUEST['rid'];
    }
}
// make sure the rid is valid
$recordInfo = parseRecordID($rid);
if ($recordInfo === false)
{
    header('Location: schemeLayout.php');
    die();
}
	
requireProject();
requirePermissions(INGEST_RECORD, 'schemeLayout.php');
	
// make sure the record's scheme matches the current scheme
if ($_SESSION['currentProject'] != $recordInfo['project'])
{
    header('Location: selectProject.php');
    die();
}
	
include_once('includes/header.php');
	
$form = new IngestionForm($recordInfo['project'], $recordInfo['scheme'], $rid);
if (isset($_POST['ingestionForm'])) $form->ingest();
else $form->display();

include_once('includes/footer.php');
?>
