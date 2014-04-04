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

// Initial Version: Matt Geimer/Brian Beck, 2008

require_once('conf.php');
require_once('utilities.php');

function loadSchemes() {
	global $db;
	
	$query = 'SELECT crossProjectAllowed FROM scheme WHERE crossProjectAllowed IS NOT NULL AND schemeid='.$_SESSION['currentScheme'];
	$results = $db->query($query);
	
	if($results->num_rows == 0 ) {
		echo '<br /> '.gettext('No schemes are currently allowed to associate to this project').'. <br/>';
		return;
	}
	
	$result = $results->fetch_assoc();
	$xml = simplexml_load_string($result['crossProjectAllowed']);
	
	$schemes = array();
	$name = array();
	$projectList = array();
	$schemeList = array('-1');
	
	// build the list of schemes
	for($n = sizeof($xml->to->entry)-1; $n>=0; $n--){
		$entry = $xml->to->entry[$n];
		
		// bad data. ignore it.
		if((string)$entry->scheme == 'null'){
			unset($xml->to->entry[$n]);
			continue;
		}
		$schemes[] = array('project' => (string)$entry->project, 'scheme' => (string)$entry->scheme);
		$schemeList[] = (string)$entry->scheme;
	}
	
	
	if (empty($xml->to) || empty($xml->to->entry)){
		echo '<br /> '.gettext('No schemes are currently allowed to associate to this project').'. <br/>';
		return;
	}
	
	
	// get the list of scheme names
	$nameQuery  = "SELECT project.name AS projName, scheme.schemeName AS schemeName, scheme.pid AS pid, scheme.schemeid AS schemeid ";
	$nameQuery .= "FROM project LEFT JOIN scheme USING (pid) ";
	$nameQuery .= "WHERE scheme.schemeid IN (".implode(',', $schemeList).")";
	
	
	$nameQuery = $db->query($nameQuery);
	while($nameResult = $nameQuery->fetch_assoc())
	{
		if(!isset($name[$nameResult['pid']])) {
			$name[$nameResult['pid']] = array();
		}
		$name[$nameResult['pid']]['name'] = $nameResult['projName'];
		$name[$nameResult['pid']][$nameResult['schemeid']] = $nameResult['schemeName'];
	}
	
	
	echo gettext('The following schemes are currently allowed to associate to records in this scheme').':';
	?>
	<br /><br />
	<table class="table">
		<tr><td>
			<strong><?php echo gettext('Project');?>?</strong>
		</td><td>
			<strong><?php echo gettext('Scheme');?></strong>
		</td><td>
			<strong><?php echo gettext('Delete');?></strong>
		</td></tr>
		<?php
		foreach($schemes as $scheme){
			if (!empty($scheme['scheme'])){
				?>
				<tr><td>
					<?php echo htmlEscape($name[$scheme['project']]['name']);?>
				</td><td>
					<?php echo htmlEscape($name[$scheme['project']][$scheme['scheme']]);?>
				</td><td>
					<a class="link" onclick="removeScheme(<?php echo $scheme['project'].','.$scheme['scheme'];?>)">X</a>
				</td></tr>
				<?php
			}
		}
		?>
	</table>
	<?php
}

/**
 * addScheme adds a project and scheme to the allowed list
 *
 * @param integer $pid
 * @param integer $sid
 */
function addScheme($pid,$sid) {
	global $db;
	
	if (empty($pid) || empty($sid)) {
		loadSchemes();
		return;
	}
	
	$sids = array();
	if($sid == 'all'){
		// get all schemes within the project
		$results = $db->query("SELECT schemeid FROM scheme WHERE pid=".escape($pid));
		while($result = $results->fetch_assoc()) $sids[]=$result['schemeid'];
	}
	else $sids[]=escape($sid,false);
	
	// we are granting permissions TO other schemes to search this scheme
	$query = 'SELECT crossProjectAllowed FROM scheme WHERE schemeid='.$_SESSION['currentScheme'].' LIMIT 1';
	$results = $db->query($query);
	$result = $results->fetch_assoc();
	
	if($result['crossProjectAllowed'] == '') {
		//there is nothing in the field, use this as defualt xml
		$result['crossProjectAllowed'] = '<crossProjectAllowed><from></from><to></to></crossProjectAllowed>';
	}
	$toScheme =simpleXML_load_string($result['crossProjectAllowed']);
	
	// grant permissions TO other schemes
	foreach($sids as $sid){
		
		// check if permissions exist
		if (isset($toScheme->to->entry)){
			for($n = sizeof($toScheme->to->entry)-1; $n >= 0; $n-- ){
				$entry = $toScheme->to->entry[$n];
				
				// bad data.  clean it up.
				if((string)$entry->scheme == 'null') {
					unset($toScheme->to->entry[$n]);
					continue;
				}
				
				// remove any matching entries, then re-add later
				if (((string)$entry->project == $pid) && ((string)$entry->scheme == $sid)){
					unset($toScheme->to->entry[$n]);
				}
			}
		}
		
		if (!isset($toScheme->to)) $toScheme->addChild('to');
		$node = $toScheme->to->addChild('entry');
		$node->addChild('project',xmlEscape($pid));
		$node->addChild('scheme',xmlEscape($sid));
	}
	
	// we are not recording permissions granted FROM other schemes to this
	// scheme right now, so update the data in the db
	$db->query('UPDATE scheme SET crossProjectAllowed='.escape($toScheme->asXML()).' WHERE schemeid='.$_SESSION['currentScheme']);
	
	
	// get all schemes we want to grant permissions FROM this scheme
	// the current scheme may be included, so we do this after updating the db
	$fromSchemes = array();
	$query = 'SELECT schemeid,crossProjectAllowed FROM scheme WHERE schemeid IN (\''.implode("','",$sids).'\')';
	$results = $db->query($query);

	while($result = $results->fetch_assoc()) {
		if($result['crossProjectAllowed'] == '') {
			//there is nothing in the field, use this as defualt xml
			$result['crossProjectAllowed'] = '<crossProjectAllowed><from></from><to></to></crossProjectAllowed>';
		}
		$fromSchemes[$result['schemeid']] = simplexml_load_string($result['crossProjectAllowed']);
	}
	

	// grant permissions from other schemes
	foreach($sids as $sid){
		$fromScheme = $fromSchemes[$sid];
		// check if permissions exist
		if (isset($fromScheme->from->entry)){
			// bad data. clean it up.
			if ((string)$fromScheme->from == '.') $fromScheme->from = '';
			
			for($n = sizeof($fromScheme->from->entry)-1; $n >= 0; $n-- ){
				$entry = $fromScheme->from->entry[$n];
				
				// bad data.  clean it up.
				if((string)$entry->scheme == 'null') {
					unset($fromScheme->from->entry[$n]);
					continue;
				}
				
				// remove any matching entries, then re-add later
				if (((string)$entry->project == $pid) && ((string)$entry->scheme == $sid)){
					unset($fromScheme->from->entry[$n]);
				}
			}
		}
		
		
		if (!isset($fromScheme->from)) $fromScheme->addChild('from');
		$node = $fromScheme->from->addChild('entry');
		$node->addChild('project',xmlEscape($_SESSION['currentProject']));
		$node->addChild('scheme',xmlEscape($_SESSION['currentScheme']));
	}

	$querys = array();
	foreach($fromSchemes as $sid=>$fromScheme){
		$querys[] = 'UPDATE scheme SET crossProjectAllowed='.escape($fromScheme->asXML()).' WHERE schemeid='.escape($sid);
	}
	foreach($querys as $query){
		$db->query($query);
	}
	
	loadSchemes();
}

/**
 * removeScheme removes a project and scheme from the allowed list
 *
 * @param integer $pid
 * @param integer $sid
 */
function removeScheme($pid,$sid) {
	global $db;
	
	if (empty($pid) || empty($sid)) {
		loadSchemes();
		return;
	}
	
	// the scheme we are removing permissions to
	$query = 'SELECT crossProjectAllowed FROM scheme WHERE schemeid='.$_SESSION['currentScheme'].' LIMIT 1';
	$results = $db->query($query);
	$result = $results->fetch_assoc();
	
	if($result['crossProjectAllowed'] == '') {
		//there is nothing in the field, use this as defualt xml
		$result['crossProjectAllowed'] = '<crossProjectAllowed><from></from><to></to></crossProjectAllowed>';
	}
	$toScheme =simpleXML_load_string($result['crossProjectAllowed']);
	
	// keep track of schemes we previously granted to
	$sids = array();
	
	// remove permissions to this scheme
	if (isset($toScheme->to->entry)){
		for($n = sizeof($toScheme->to->entry)-1; $n >= 0; $n-- ){
			$entry = $toScheme->to->entry[$n];
			
			// bad data.  clean it up.
			if((string)$entry->scheme == 'null') {
				unset($toScheme->to->entry[$n]);
				continue;
			}
			
			// remove any matching entries
			if (((string)$entry->project == $pid) && ((string)$entry->scheme == $sid)){
				$sids[]=$sid;
				unset($toScheme->to->entry[$n]);
			}
		}
	}
	
	$db->query('UPDATE scheme SET crossProjectAllowed='.escape($toScheme->asXML()).' WHERE schemeid='.$_SESSION['currentScheme']);
	
	
	
	// all schemes we are removing permissions from
	// the current scheme may be included, so we do this after updating the db
	$fromSchemes = array();
	$query = 'SELECT schemeid,crossProjectAllowed FROM scheme WHERE schemeid IN (\''.implode("','",$sids).'\')';
	$results = $db->query($query);

	while($result = $results->fetch_assoc()) {
		if($result['crossProjectAllowed'] == '') {
			//there is nothing in the field, use this as defualt xml
			$result['crossProjectAllowed'] = '<crossProjectAllowed><from></from><to></to></crossProjectAllowed>';
		}
		$fromSchemes[$result['schemeid']] = simplexml_load_string($result['crossProjectAllowed']);
	}
	

	// remove permissions from other schemes
	foreach($sids as $sid){
		$fromScheme = $fromSchemes[$sid];
		// check if permissions exist
		if (isset($fromScheme->from->entry)){
			// bad data. clean it up.
			if ((string)$fromScheme->from == '.') $fromScheme->from = '';
			
			for($n = sizeof($fromScheme->from->entry)-1; $n >= 0; $n-- ){
				$entry = $fromScheme->from->entry[$n];
				
				// bad data.  clean it up.
				if((string)$entry->scheme == 'null') {
					unset($fromScheme->from->entry[$n]);
					continue;
				}
				
				// remove any matching entries, then re-add later
				if (((string)$entry->project == $pid) && ((string)$entry->scheme == $sid)){
					unset($fromScheme->from->entry[$n]);
				}
			}
		}
	}

	$querys = array();
	foreach($fromSchemes as $sid=>$fromScheme){
		$querys[] = 'UPDATE scheme SET crossProjectAllowed='.escape($fromScheme->asXML()).' WHERE schemeid='.escape($sid);
	}
	foreach($querys as $query){
		$db->query($query);
	}
	
	loadSchemes();
}

if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'ProjectFunctions'){
	requireLogin();
	requireProjectAdmin();
	
	$action = $_POST['action'];
	if($action == 'loadSchemes')
		loadSchemes();
	elseif($action == 'addScheme')
		addScheme($_POST['pid'],$_POST['sid']);
	elseif($action == 'removeScheme')
		removeScheme($_POST['pid'],$_POST['sid']);
}

?>
