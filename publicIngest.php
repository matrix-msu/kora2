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

// Initial Version: Caitlin Russ, 2010

require_once('includes/utilities.php');
require_once('includes/ingestionClass.php');
require_once('includes/recaptcha/recaptchalib.php');

function publicIngestForm($pid = '', $sid = '')
{
	if(!isset($_SESSION['formURL'])) $_SESSION['formURL'] = $_SERVER['PHP_SELF'];
	global $db;
	
	//check for valid project and scheme IDs
	if(empty($pid)) die("There is no project selected.");
	if(empty($sid)) die("There is no scheme selected.");
	
	$publicQuery = 'SELECT publicIngestion FROM scheme WHERE pid='.escape($pid).' AND schemeid='.escape($sid).' LIMIT 1';
	$checkPublic = $db->query($publicQuery);
    if(!($checkPublic->num_rows > 0))
    {
    	//no results, so the project or scheme IDs entered were invalid
    	die("Either the project or scheme you are trying to ingest into are invalid.");
    }
	
    //project and scheme IDs were valid, so check if the scheme is publically ingestible
    $assoc = $checkPublic->fetch_assoc();
	$isPublic = array_pop($assoc);
	if(!$isPublic) die("The scheme you are trying to ingest into is not publically ingestible.");
	
	//everything is K, so continue!
	$rid = getNewPublicRecordID($pid);
	$_SESSION['currentProject'] = $pid;
	

	//reCAPTCHA
	echo '<script type="text/javascript">var RecaptchaOptions = {theme : \'white\'};</script>';
	$recaptchaValid = true;
	if (isset($_POST['ingestionForm']))
	{
		$resp = recaptcha_check_answer (PRIVATE_KEY,
		$_SERVER["REMOTE_ADDR"],
		$_POST["recaptcha_challenge_field"],
		$_POST["recaptcha_response_field"]);

		if (!$resp->is_valid)
		{
			$msg = "The reCAPTCHA was entered incorrectly. Please try again.";
			$recaptchaValid = false;
		}
	}

	if(isset($msg))
	{
		echo "<h3>$msg</h3>";
	}

	if (isset($_POST['ingestionForm']) && $recaptchaValid)
	{
		$schemeTest = $db->query('SELECT schemeid FROM scheme WHERE pid='.escape($pid).' AND schemeid='.escape($sid).' LIMIT 1');
		if ($schemeTest->num_rows > 0)
		{
			if (empty($_POST['recordid']))
			{
				$rid = getNewPublicRecordID($pid);
				$newRecord = true;
			}
			else
			{
				$rid = $_POST['recordid'];
				$newRecord = false;
			}
			//attempt to ingest the record
			$form = new IngestionForm($pid, $sid, $rid, '', $newRecord, true);
			$form->ingest(null, true);
		}
		else
		{
			echo gettext('Invalid Project/Scheme ID passed');
		}
	}
	else
	{
		//print out scheme's legal notice
		$legalQuery = 'SELECT legal FROM scheme WHERE pid='.escape($pid).' AND schemeid='.escape($sid).' LIMIT 1';
		$legalNotice = $db->query($legalQuery);
		while($result = $legalNotice->fetch_assoc())
		{
			if($result['legal']!=NULL)
			{
				echo "<strong>".gettext('Legal notice: ')."</strong>".$result['legal']."<br /><br />";
			}
		}
				
		//display the ingestion form
		$form = new IngestionForm($pid, $sid, '', '', false, true);
		$form->publicIngestDisplay(true);
		
	}
}

?>
