<?php 
use KORA\Manager;
use KORA\Project;
use KORA\Record;
use KORA\Scheme;
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

// Initial Version: Meghan McNeil, 2009
// Refactor: Joe Deming, Anthony D'Onofrio 2013

// This ajax file handles all requests related to a Control Model

require_once(__DIR__.'/../includes/includes.php');

Manager::Init();

//Validates a control for ingestion
function validateControl($pid,$sid,$cid)
{                                                  
	global $db;
	
	$query = "SELECT cid,type FROM p".$pid."Control WHERE cid=$cid AND schemeid=$sid LIMIT 1";
	$result = $db->query($query);
	$data = $result->fetch_assoc();
	$type = $data['type'];
	
	$ctl = new $type($pid, $cid);
	return $ctl->validateIngestion();
}

//Searches for records that a control can associate to
function AssocSearch($pid, $sid, $cid, $keywords)
{
	$pAssoc = new Project($pid);
	$ctype = $pAssoc->GetControlTypes(array($cid));
	
	if (sizeof($ctype) == 1 && $ctype[$cid]['class'] == 'AssociatorControl')
	{
		$ctrl = new $ctype[$cid]['class']($pid, $cid);
		$privs = $ctrl->GetAssocPrivs();
		
		if (!empty($privs['projects']) && !empty($privs['schemes']))
		{			
			$searchLink = 'href="ajax/control.php?action=assocSearch&pid='.urlencode($pid).'&sid='.urlencode($sid).'&cid='.urlencode($cid).'&keywords='.urlencode($keywords).'&page=%d"';
			$page = (Manager::CheckRequestsAreSet(['page'])) ? (int)$_REQUEST['page'] : 1;
			
			echo "<div class='assoc_search_results' kcid='".urlencode($cid)."'>";
			$keys = $_REQUEST['keywords'];
			$ctrl->PrintAssocSearch($keys);
			echo "</div>";
		}
		else { print gettext('You do not have access to associate to any schemes'); }
	}
	else
	{
		throw new Exception(gettext('Invalid cid submitted for AssocSearch'));
	}
	
}

if(Manager::CheckRequestsAreSet(['action'])) {
	//Handles validation of a control
	if ($_REQUEST['action'] == 'validateControl' && Manager::GetProject() && Manager::GetScheme() && Manager::CheckRequestsAreSet(['cid'])) {
		print validateControl(Manager::GetProject()->GetPID(),Manager::GetScheme()->GetSID(),$_REQUEST['cid']);
	}
	//Handles setting the name of a control
	else if ($_REQUEST['action'] == 'SetName' && Manager::GetProject() && Manager::GetScheme() && Manager::CheckRequestsAreSet(['cid','cname'])) {
		$s = new Scheme(escape(Manager::GetProject()->GetPID(), false), escape(Manager::GetScheme()->GetSID(), false));
		$c = $s->GetControl(escape($_REQUEST['cid'], false));
		$c->SetName($_REQUEST['cname']);
	}
	//Handles setting of a controls description
	else if ($_REQUEST['action'] == 'SetDesc' && Manager::GetProject() && Manager::GetScheme() && Manager::CheckRequestsAreSet(['cid','cdesc'])) {
		$s = new Scheme(escape(Manager::GetProject()->GetPID(), false), escape(Manager::GetScheme()->GetSID(), false));
		$c = $s->GetControl(escape($_REQUEST['cid'], false));
		$c->SetDesc($_REQUEST['cdesc']);
	}
	//Handles search of records to associate to    
	else if ($_REQUEST['action'] == 'assocSearch' && Manager::GetProject() && Manager::GetScheme() && Manager::CheckRequestsAreSet(['cid', 'keywords'])) {
		AssocSearch(Manager::GetProject()->GetPID(),Manager::GetScheme()->GetSID(),$_REQUEST['cid'],$_REQUEST['keywords']);
	}
	//Handles ingestion of association
	else if ($_REQUEST['action'] == 'assocIngest' && Manager::GetProject() && Manager::GetScheme()) {
		$form = new Record(Manager::GetProject()->GetPID(),Manager::GetScheme()->GetSID());
		$form->PrintRecordDisplay();
	}
	//Handles setting of standard options in controls
	else if ($_REQUEST['action'] == 'SetStdOption' && Manager::GetProject() && Manager::GetScheme() && Manager::CheckRequestsAreSet(['cid', 'ctrlopt', 'ctrloptval'])) {
		$s = new Scheme(escape(Manager::GetProject()->GetPID(), false), escape(Manager::GetScheme()->GetSID(), false));
		$c = $s->GetControl(escape($_REQUEST['cid'], false));
		switch ($_REQUEST['ctrlopt'])
		{
		case 'required': //Is the control required in a record
			$c->SetRequired($_REQUEST['ctrloptval']);
			$c->SetShowInPublicResults($_REQUEST['ctrloptval']);
			break;
		case 'searchable': //Is the control searchable in any context
			$c->SetSearchable($_REQUEST['ctrloptval']);
			break;
		case 'advsearchable': //Is the control searchable in the advanced search
			$c->SetAdvSearchable($_REQUEST['ctrloptval']);
			break;
		case 'showinresults': //Is the control allowed to show up in the search results
			$c->SetShowInResults($_REQUEST['ctrloptval']);
			break;
		case 'publicentry': //Is the control visible in a public ingestion form
			$c->SetShowInPublicResults($_REQUEST['ctrloptval']);
			break;
		}
	}
	//Handles ingestion of a record
	else if ($_REQUEST['action'] == 'RecordIngest' && Manager::GetProject() && Manager::GetScheme()) {
		$finaldata = null;
		$rid = (Manager::GetRecord()) ? Manager::GetRecord()->GetRID() : null;
		$ingestion = new Record(Manager::GetProject()->GetPID(),Manager::GetScheme()->GetSID(),$rid);
		if (Manager::CheckRequestsAreSet(['ingestdata', 'ingestmap']))
			{ $finaldata = $ingestion->GetImportData(); }
		
		if (!$ingestion->ingest($finaldata)) { Manager::PrintErrDiv(gettext('Please fix errors and try again')); }
		echo gettext("Ingesting object ")."... <br/>%".$ingestion->GetRID();
	}
	//Handles printing of a control's options form
	else if (($_REQUEST['action'] == 'ShowDialog') && Manager::GetProject() && Manager::GetScheme() && Manager::CheckRequestsAreSet(['cid', 'pid'])) {
		Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid'])->PrintControlOptions();
	}
}

// Handle the AJAX Calls for specific controls
require_once('controlTypes/AssociatorControl.php');
require_once('controlTypes/DateControl.php');
require_once('controlTypes/FileControl.php');
//require_once('controlTypes/GeolocatorControl.php');
require_once('controlTypes/ImageControl.php');
require_once('controlTypes/ListControl.php');
require_once('controlTypes/MultiDateControl.php');
require_once('controlTypes/MultiListControl.php');
require_once('controlTypes/MultiTextControl.php');
require_once('controlTypes/TextControl.php');
//

if(Manager::CheckRequestsAreSet(['action', 'source']) && $_REQUEST['source'] == 'PresetFunctions'){
	$action = $_REQUEST['action'];
	
	//Handles updating the name of a control preset
	if($action == 'updateControlPresetName' && Manager::CheckRequestsAreSet(['preID', 'name'])) {
		Control::updateControlPresetName($_REQUEST['preID'], $_REQUEST['name']);
	} 
	//Handles toggling a control preset to be global
	elseif($action == 'updateControlPresetGlobal' && Manager::CheckRequestsAreSet(['preID', 'global']) ) {
		Control::updateControlPresetGlobal($_REQUEST['preID'],$_REQUEST['global']);	
	} 
	//Handles deletion of a control preset
	elseif($action == 'deleteControlPreset' && Manager::CheckRequestsAreSet(['preID'])) {
		Control::deleteControlPreset($_REQUEST['preID']);
	} 
	//handles printing of control preset form
	elseif($action == 'showControlPresetDialog') {
		Control::showControlPresetDialog();
	} 
}

?>