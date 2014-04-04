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

require_once("control.php");
//include_once("../includes/thickbox/thickbox.css");

class AssociatorControl extends Control {
	protected $name = "Associator Control";
	protected $ExistingData;
	protected $value;
	protected $options;

	public function AssociatorControl($projectid='', $controlid='', $recordid='', $presetid='', $inPublicTable = false)
	{
		if (empty($projectid) || empty($controlid)) return;
		global $db;
		
		$this->pid = $projectid;
		$this->cid = $controlid;
		$this->rid = $recordid;
		$this->cName = 'p'.$projectid.'c'.$controlid;
		$this->ExistingData = false;

		$controlCheck = $db->query('SELECT schemeid, name, description, required, options FROM p'.$projectid.'Control WHERE cid='.escape($controlid).' LIMIT 1');
		if ($controlCheck->num_rows > 0) {
			$controlCheck = $controlCheck->fetch_assoc();
			$this->sid = $controlCheck['schemeid'];
			foreach(array('name', 'description', 'required', 'options') as $field) {
				$this->$field = $controlCheck[$field];
			}
		} else $this->pid = $this->cid = $this->rid = $this->cName = '';
		
		$this->options = simplexml_load_string($this->options);

		// If data exists for this control, get it

		if (isset($_SESSION['lastIngestion']))
		{
			$lastIngestion = unserialize($_SESSION['lastIngestion']);
		}
		else
		{
			$lastIngestion = array();
		}

		if (!empty($recordid))
		{
			// See if there was a previous ingestion for this object
			if (isset($lastIngestion['editRecord']) && $lastIngestion['editRecord'] == $recordid && isset($lastIngestion[$this->cName]))
			{
				$this->value = simplexml_load_string('<associator></associator>');
				foreach($lastIngestion[$this->cName] as $selectedOption)
				{
					$this->value->addChild('kid', xmlEscape($selectedOption));
				}
				$this->ExistingData = true;
			}
			else
			{
				$valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($recordid).' AND cid='.escape($controlid).' LIMIT 1');
				if ($valueCheck->num_rows > 0) {
					$this->ExistingData = true;
					$valueCheck = $valueCheck->fetch_assoc();
					$this->value = simplexml_load_string($valueCheck['value']);
				}
			}
		}
		else if (isset($lastIngestion[$this->cName]) && !isset($lastIngestion['editRecord']))
		{
			// load value from session
			$this->value = simplexml_load_string('<associator></associator>');
			foreach($lastIngestion[$this->cName] as $selectedOption)
			{
				$this->value->addChild('kid', xmlEscape($selectedOption));
			}
			$this->ExistingData = true;
		}
		else if (!empty($presetid))
		{
			$valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($presetid).' AND cid='.escape($controlid).' LIMIT 1');
			if ($valueCheck->num_rows > 0)
			{
				$this->ExistingData = true;
				$valueCheck = $valueCheck->fetch_assoc();
				$this->value = simplexml_load_string($valueCheck['value']);
			}
		}
		else if (isset($this->options->defaultValue->value))
		{
			// Otherwise, this is an initial ingestion, so fill in the default
			$this->value = simplexml_load_string('<associator />');
			foreach($this->options->defaultValue->value as $option)
			{
				$this->value->addChild('kid', xmlEscape((string)$option));
			}
		}
	}
	
	public function delete() {
		global $db;
		
		if (!$this->isOK()) return;
		
		if (!empty($this->rid))
		{
			// Clean up the Reverse Associations
			$dataCall = $db->query('SELECT id, value FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid).' AND id='.escape($this->rid).' LIMIT 1');
			while($a = $dataCall->fetch_assoc())
			{
				$xml = simplexml_load_string($a['value']);
				if (isset($xml->kid))
				{
					foreach($xml->kid as $kid)
					{
						removeReverseAssociation((string)$kid, $a['id'], $this->cid);
					}
				}
			}
			// Delete the Data
			$deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1');
		}
		else
		{
			// Clean up the Reverse Associations
			$dataCall = $db->query('SELECT id, value FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid));
			while($a = $dataCall->fetch_assoc())
			{
				$xml = simplexml_load_string($a['value']);
				if (isset($xml->kid))
				{
					foreach($xml->kid as $kid)
					{
						removeReverseAssociation((string)$kid, $a['id'], $this->cid);
					}
				}
			}
			// Delete the Data
			$deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid));

			// We don't need to Repeat for PublicData becuase associator controls cannot be filled out in public ingestion.
		}
	}
	
	public function display() {
		global $db;
		?>
		<script type="text/javascript">
	 // <![CDATA[
			function popUpAssoc<?php echo $this->cid?>()
			{
				var w = window.open("assocSearch.php?cid=<?php echo $this->cid?>&keywords=" + encodeURI(document.ingestionForm.Search<?php echo $this->cid?>.value), "assocPopUp<?php echo $this->cid?>");
				w.focus();
			}
			
			function addAssoc<?php echo $this->cid?>()
			{
				var kidRegEx = new RegExp('^[A-F0-9]+-[A-F0-9]+-[A-F0-9]+$','');
				
				if (kidRegEx.test(document.ingestionForm.Input<?php echo $this->cid?>.value))
				{
					var optn = document.createElement("option");
					var selectbox = document.getElementById("<?php echo $this->cName?>");
					optn.text = document.ingestionForm.Input<?php echo $this->cid?>.value;
					optn.value = document.ingestionForm.Input<?php echo $this->cid?>.value;
					selectbox.options.add(optn);
				
					document.ingestionForm.Input<?php echo $this->cid?>.value = "";
				} else {
					document.ingestionForm.Input<?php echo $this->cid?>.value = "<?php echo gettext('Invalid').' KID';?>";
				}
			}
			
			function removeAssoc<?php echo $this->cid?>()
			{
				var selectbox = document.getElementById("<?php echo $this->cName?>");
				
				for(i = selectbox.length - 1; i >= 0; i--)
				{
					if (selectbox.options[i].selected)
					{
						for(j = i; j < (selectbox.length - 1); j++)
						{
							selectbox.options[j].text = selectbox.options[j+1].text;
							selectbox.options[j].value = selectbox.options[j+1].value;
						}
						
						selectbox.length--;
					}
				}
			}
			
			function moveAssocOptionUp<?php echo $this->cid?>()
			{
				var selectbox = document.getElementById("<?php echo $this->cName?>");
				
				// Start at 1 rather than 0 so that we don't even bother dealing with the top case
				// If the desired element is at the top it won't be moved anyway
				for(i = 1; i < selectbox.length; i++)
				{
					if (selectbox.options[i].selected)
					{
						var tempText = selectbox.options[i-1].text;
						var tempValue = selectbox.options[i-1].value;
						
						selectbox.options[i-1].text = selectbox.options[i].text;
						selectbox.options[i-1].value = selectbox.options[i].value;
						selectbox.options[i].text = tempText;
						selectbox.options[i].value = tempValue;
					}
				}
			}
			
			function moveAssocOptionDown<?php echo $this->cid?>()
			{
				var selectbox = document.getElementById("<?php echo $this->cName?>");
				
				// End at selectbox.length - 2 rather than -1 so that we don't even bother dealing
				// with the bottom case.  If the desired element is at the bottom it won't be moved anyway
				for(i = 0; i <= (selectbox.length - 2); i++)
				{
					if (selectbox.options[i].selected)
					{
						var tempText = selectbox.options[i+1].text;
						var tempValue = selectbox.options[i+1].value;
						
						selectbox.options[i+1].text = selectbox.options[i].text;
						selectbox.options[i+1].value = selectbox.options[i].value;
						selectbox.options[i].text = tempText;
						selectbox.options[i].value = tempValue;
					}
				}
			}
			
			function viewObject<?php echo $this->cid?>()
			{
				var selectbox = document.getElementById("<?php echo $this->cName?>");
				
				for(i = selectbox.length - 1; i >= 0; i--)
				{
					if (selectbox.options[i].selected)
					{
						var w = window.open("viewObject.php?rid=" + encodeURI(selectbox.options[i].value), "assocViewPopUp<?php echo $this->cid?>");
						w.focus();
					}
				}
			}
			
		 // ]]>
		</script>
		<?php
		echo '<div class="kora_control">';
		echo '<table border="0"><tr><td>';
		echo '<select id="'.$this->cName.'" name="'.$this->cName.'[]" multiple="multiple" size="5">'."\n";
		if (isset($this->value->kid))
		{
			foreach($this->value->kid as $kid) {
				echo '<option value="'.(string)$kid.'">'.(string)$kid."</option>\n";
			}
		}
		echo "</select>\n</td>";
		echo '<td><input type="button" onclick="removeAssoc'.$this->cid.'()" value="'.gettext('Remove').'" /><br /><br />';
		echo '<input type="button" onclick="viewObject'.$this->cid.'()" value="'.gettext('View Record').'" /></td></tr>';
		echo '<tr><td><input type="button" onclick="moveAssocOptionUp'.$this->cid.'()" value="'.gettext('Up').'" /></td>';
		echo '<td><input type="button" onclick="moveAssocOptionDown'.$this->cid.'()" value="'.gettext('Down').'" /></td></tr>';
		echo '<tr><td><input type="text" name="Search'.$this->cid.'" value="" /></td>';
		echo '<td><input type="button" onclick="popUpAssoc'.$this->cid.'()" value="'.gettext('Find a Record').'" /></td></tr>';
		echo '<tr><td><input type="text" name="Input'.$this->cid.'" value="" /></td>';
		echo '<td><input type="button" onclick="addAssoc'.$this->cid.'()" value="'.gettext('Associate this Record').'" /></td></tr>';
		echo '<tr><td colspan="2"><a href="includes/addRecordToAssocControl.php?height=700&width=700&control='.$this->getName().'" class="thickbox" >Add New Record</a></td></tr>';
		echo '</table>';
		echo '</div>';
	}
	
	public function displayXML() {
		if (!$this->isOK()) return '';
		
		$xmlString = '<associator>';
		if (isset($this->value->kid))
		{
			foreach($this->value->kid as $kid)
			{
				$xmlString .= "<kid>$kid</kid>";
			}
		}
		$xmlString .= '</associator>';
		
		return $xmlString;
	}

	public function displayOptionsDialog()
	{
	$controlPageURL = baseURI . 'controls/associatorControl.php';
?><!-- Javascript Code below for list add/remove/up/down buttons -->

<script type="text/javascript">
//<![CDATA[
	function setPreview(schemeid){
		$.post('<?php echo $controlPageURL?>', {action:'setPreview',source:'AssocControl',cid:<?php echo $this->cid?>,schemeid:schemeid,preview:$("#preview"+schemeid).val() }, function(resp){$("#ajax").html(resp);}, 'html');
	}
	function updateCheckBox(varschemeid) {
		$.post('<?php echo $controlPageURL?>', {action:'updateBoxes',source:'AssocControl',cid:<?php echo $this->cid?>,schemeid:varschemeid,checked:$("#searchbox"+varschemeid)[0].checked }, function(resp){$("#ajax").html(resp);}, 'html');
	}
	
	function moveDefaultValue(varDirection)
	{
		var defaultValue = $('#defaultValue').val();
		$.post('<?php echo $controlPageURL?>', {action:'moveDefaultValue',source:'AssocControl',cid:<?php echo $this->cid?>,defaultV:defaultValue,direction:varDirection }, function(resp){$("#ajax").html(resp);}, 'html');
	}

	function removeDefaultValue()
	{
		var defaultValue = $('#defaultValue').val();
		$.post('<?php echo $controlPageURL?>', {action:'removeDefaultValue',source:'AssocControl',cid:<?php echo $this->cid?>,defaultV:defaultValue }, function(resp){$("#ajax").html(resp);}, 'html');
	}

	function addDefaultValue()
	{
		var defaultValue = $('#Input<?php echo $this->cid?>').val();
		$.post('<?php echo $controlPageURL?>', {action:'addDefaultValue',source:'AssocControl',cid:<?php echo $this->cid?>,defaultV:defaultValue }, function(resp){$("#ajax").html(resp);}, 'html');
	}
	
	function findDefaultValue()
	{
		var w = window.open("assocSearch.php?cid=<?php echo $this->cid?>&keywords=" + encodeURI($('#defValSearch').val()), "assocPopUp");
		w.focus();
	}

	$.post('<?php echo $controlPageURL?>', {action:'showDialog',source:'AssocControl',cid:<?php echo $this->cid?>,schemeid:<?php echo $_SESSION['currentScheme']?> }, function(resp){$("#ajax").html(resp);}, 'html');
	
// ]]>
</script>

<div id="ajax"></div>

<?php
	}
	
	public function getName() { return $this->name; }
	
	public function getSearchString($submitData) {
		return false;
	}
	
	public function getType() { return "Record Associator"; }
	
	public function setXMLInputValue($value) {
		$this->XMLInputValue = $value;
	}
	
	public function ingest($publicIngest = false) {
		global $db;
		
		if(empty($this->rid)) {
		  echo '<div class="error">'.gettext('No Record ID Specified').'.</div>';
		  return;
		}
		
		//determine whether to insert into public ingestion table or not
		if($publicIngest)
			{
				$tableName = 'PublicData';
			}
			else $tableName = 'Data';
		
		$xml = '<associator>';
		
		$kids = array();
		if (isset($_POST[$this->cName])) {
				$kids = $_POST[$this->cName];
		}
		else if( isset($this->XMLInputValue) && !empty($this->XMLInputValue)) {
				$kids = $this->XMLInputValue;
		}
		
		if (!empty($kids)) {
				// Get the list of projects/schemes we're allowed to associate to
		
			// $targetSchemes = array( "1" => array("1", "3"), "2" => array("2") )
			$targetSchemes = array();
			
			if (!empty($this->options->scheme))
			{
				$schemes = array();
				foreach($this->options->scheme as $scheme)
				{
					$schemes[] = (string)$scheme;
				}
				
				if (!empty($schemes))
				{
					$query = 'SELECT pid, schemeid FROM scheme WHERE schemeid IN ('.implode(',',$schemes).')';
					$results = $db->query($query);
					while($record = $results->fetch_assoc())
					{
						if (!isset($targetSchemes[$record['pid']]))
						{
							$targetSchemes[$record['pid']] = array();
						}
						$targetSchemes[$record['pid']][] = $record['schemeid'];
					}
				}
			}
			  
		  foreach($kids as $kid)
		  {
			  // Verify that the KID corresponds to a record in a scheme we're
			  // allowed to associate to
			  
		  	  $kidDetails = parseRecordID($kid);
		  	  
			  // Make sure the project/scheme are acceptable
			  if ($kidDetails &&
				  isset($targetSchemes[$kidDetails['project']]) &&
				  in_array($kidDetails['scheme'], $targetSchemes[$kidDetails['project']]))
			  {
			  	  $dataQuery = $db->query('SELECT id FROM p'.$kidDetails['project'].$tableName.' WHERE id='.escape($kid));
			  	  if ($dataQuery->num_rows > 0)
			  	  {
			  		 $xml .= '<kid>'.$kid.'</kid>';
			  	  }
			  }
		  }
		}
		$xml .= '</associator>';
		
		if ($this->ExistingData)
		{
			if ($this->isEmpty())
			{
					if (isset($this->value->kid)) {
						foreach($this->value->kid as $kid)
						{
						// clean the record
						removeReverseAssociation((string)$kid, $this->rid, $this->cid);
						}
					}
				$db->query('DELETE FROM p'.$this->pid.$tableName.' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1');
			}
			else
			{
				// build the list of records to clean reverse associations from
				// and records to add reverse associations to
				$removeFrom = array();
				$addTo = array();
				if (isset($this->value->kid))
				{
					$currentKIDs = array();
					foreach($this->value->kid as $kid)
					{
						$currentKIDs[] = (string) $kid;
					}
					foreach($currentKIDs as $kid)
					{
						if (!in_array($kid, $kids))
						{
							$removeFrom[] = $kid;
						}
					}
					foreach($kids as $kid)
					{
						if (!in_array($kid, $currentKIDs))
						{
							$addTo[] = $kid;
						}
					}
				}
				else	// no existing data although it's set....wtf?
				{
					// Theoretically, this should never happen
					$addTo = $kids;
				}
				
				foreach($addTo as $kid)
				{
					if ($kInfo = parseRecordID($kid))
					{
						addReverseAssociation($kid, $this->rid, $this->cid);
					}
				}
				
				foreach($removeFrom as $kid)
				{
					if ($kInfo = parseRecordID($kid))
					{
						removeReverseAssociation($kid, $this->rid, $this->cid);
					}
				}
				
				$db->query('UPDATE p'.$this->pid.$tableName.' SET value='.escape($xml).' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1');
			}
				  	
		} else {
			
			$query = 'INSERT INTO p'.$this->pid.$tableName.' (id,cid,schemeid,value) VALUES ('.escape($this->rid).', '.escape($this->cid)
				.', '.escape($this->sid).', '.escape($xml).')';
			if (!$this->isEmpty())
			{
				$db->query($query);
				
				// Add the reverse associations
				foreach($kids as $kid)
				{
					addReverseAssociation($kid, $this->rid, $this->cid);
				}
			}
		}
	}
	
	public static function initialOptions()
	{
		return '<options><defaultValue /></options>';
	}
	
	public function isEmpty() {
		return !( !empty($_REQUEST[$this->cName]) || isset($this->XMLInputValue));
	}

	public function isXMLPacked() { return true; }
	
	public function showData() {
		
		$returnString = '';
		if (isset($this->value->kid))
		{
			$returnString = '<b>'.gettext('Currently Associated Objects').':</b><br />';
			$returnString = '<table>';
			foreach($this->value->kid as $kid)
			{
				$returnString .= '<tr><td><a href="viewObject.php?rid='.(string)$kid.'">'.(string)$kid.'</a></td><td>';
				$kidInfo = parseRecordID((string)$kid);
				if (isset($this->options->{'preview'.$kidInfo['scheme']})){
					$type = (string)$this->options->{'preview'.$kidInfo['scheme']}->type;
					$previewCid = (string)$this->options->{'preview'.$kidInfo['scheme']}->cid;
					require_once basePath.'controls/'.lcfirst($type).'.php';
					$previewControl = new $type($kidInfo['project'],$previewCid,(string)$kid);
					$returnString .= $previewControl->showData();
				}
				$returnString .='</td></tr>';
			}
			$returnString .='</table>';
		}
		return $returnString;
	}
	
	public function storedValueToDisplay($xml,$pid,$cid)
	{
		$xml = simplexml_load_string($xml);
		
		$returnVal = '<b>'.gettext('Currently Associated Objects').':</b><br />';
		if (isset($xml->kid))
		{
			foreach($xml->kid as $kid)
			{
				$returnVal .= '<a href="viewObject.php?rid='.$kid.'">'.(string)$kid.'</a><br />';
			}
		}
		
		return $returnVal;
	}
	
	public function storedValueToSearchResult($xml)
	{
		$returnVal = array();
		$xml = simplexml_load_string($xml);
		if (isset($xml->kid))
		{
			foreach($xml->kid as $kid)
			{
				$returnVal[] = (string)$kid;
			}
		}
		
		return $returnVal;
	}
	
	public function validateIngestion($publicIngest = false) {
		if ($this->required && $this->isEmpty()){
			return gettext('No value supplied for required field').': '.htmlEscape($this->name);
		}
				 
		$kids = array();
		if (isset($_POST[$this->cName])) {
			$kids = $_POST[$this->cName];
		} else if( isset($this->XMLInputValue) && !empty($this->XMLInputValue)) {
			$kids = $this->XMLInputValue;
		}
		
		// check the format for all kids
		if(!empty($kids)){
			foreach($kids as $kid){
				$kidDetails = parseRecordID($kid);
				if ($kidDetails === false){
					return gettext("Invalid Record ID format").': '.htmlEscape($this->name);
				}
			}
		}
		
		// we can't test if records exist for imported controls,
		// and normal ingestion *shouldn't* allow invalid records.
		return '';
	}
	
}

// This class stores the functioned called via AJAX for setting the properties of
// an associator control (which schemes it's allowed to associate to, etc.)
class AssociatorControlOptions
{
	public static function showDialog($cid)
	{
		global $db;
			
		// Note: using form id="ingestionForm" should probably be classified as a hack.
		// It has to do with how the Associator Search Results page populates the field in
		// the calling page (normally, the actual Ingestion Form, but in this case, this page).
		// I'd be lying if I said I was truly COMFORTABLE with this naming scheme, but I see
		// no real danger and it saves a lot of recoding, so.....
		?>
		<form id="ingestionForm" name="ingestionForm" >
		<table class="table">
		<tr>
			<td width="30%"><b><?php echo gettext('Association Permissions')?></b><br /><?php echo gettext('Use the checkboxes to allow/disallow associating to records from the following schemes from this control')?>.</td>
			<td>
		<?php

		// Get the list of all allowed schemes from the scheme table
		//
		// Example for P1S1, P1S3, P2S2:
		// $allSchemes = array( "1" => array("1", "3"), "2" => array("2") )
		$options = getControlOptions($cid);
		if(!$options) return;
		
		$allSchemes = array();
		$previewOptions = array();
		$query = 'SELECT crossProjectAllowed FROM scheme WHERE crossProjectAllowed IS NOT NULL AND schemeid='.$_SESSION['currentScheme'].' LIMIT 1';
		$results = $db->query($query);
		if($results->num_rows != 0 ) {
			$array = $results->fetch_assoc();
			$xml = simplexml_load_string($array['crossProjectAllowed']);
			if (!empty($xml->from) && !empty($xml->from->entry))
			{
				foreach($xml->from->entry as $entry)
				{
					$pid = (string)$entry->project;
					$sid = (string)$entry->scheme;
					if (!isset($allSchemes[$pid])) {
						$allSchemes[$pid] = array();
					}
					$allSchemes[$pid][] = $sid;

					$controls = $db->query("SELECT schemeid, cid, collid, type, p{$pid}Control.name, p{$pid}Control.sequence, collection.name AS collname, collection.sequence AS collseq
						FROM p{$pid}Control LEFT JOIN collection USING(schemeid,collid)
						where collid != 0 AND schemeid='$sid'
						ORDER BY schemeid, collseq, collid, sequence");

					if($controls->num_rows > 0){
						$collections = array();
						while($control = $controls->fetch_assoc()){
							if(!isset($collections[$control['collname']])) $collections[$control['collname']] = array();
							$collections[$control['collname']][]=$control;
						}
						
						$string = '<option></option>';
						foreach($collections as $collname => $collection){
							$string .= '<optgroup label="'.htmlspecialchars($collname).'">';
							foreach($collection as $control){
								$selected = '';
								if((string)$options->{'preview'.$sid}->cid == $control['cid']) $selected = 'selected="selected"';
								$string .= '<option value="'.$control['cid'].'_'.$control['type'].'" '.$selected.'>';
								$string .= htmlspecialchars($control['name']).' ('.substr($control['type'],0,-7).')</option>';
							}
							$string .= '</optgroup>';
						}
						$string .= '</select>';
						$previewOptions[$sid] = $string;
					}
				}
			}
		}
		 
		$currentSchemes = getAllowedAssociations($cid);
		
		$schemeIds = array();
		if( !empty($allSchemes) )
		{
			foreach($allSchemes as $schemes)
			{
				foreach($schemes as $scheme)
				{
					$schemeIds[] = $scheme;
				}
			}
		}
		$schemeNames = getSchemeNames($schemeIds);
		
		$results = $db->query("SELECT * FROM p$_SESSION[currentProject]Control WHERE schemeid IN (".implode(',',array_keys($schemeNames)).") ORDER BY collid,name");
		
		
		
		// Display the Checkboxes!
		if (empty($allSchemes))
		{
			echo gettext('No schemes have granted access to this scheme yet').'.';
		}
		else
		{
			echo '<table width="100%">';
			echo '<tr><td><b>'.gettext('Scheme').'</b></td><td><b>'.gettext('Search').'?</b></td><td><b>'.gettext('Preview').'</b></td></tr>';
			foreach($allSchemes as $project => $schemes)
			{
				foreach($schemes as $scheme)
				{
					if (isset($schemeNames[$scheme]))
					{
							echo '<tr><td>'.$schemeNames[$scheme]['project'].'\\'.$schemeNames[$scheme]['scheme'].'</td>';
							echo '<td>'.'<input type="checkbox" name="searchbox'.$scheme.'" id="searchbox'.$scheme.'"';
							if (in_array($scheme, $currentSchemes)) echo ' checked ';
							echo ' onclick="updateCheckBox('.$scheme.')" /></td>';
							echo '<td><select name="preview'.$scheme.'" id="preview'.$scheme.'" onchange="setPreview('.$scheme.')">';
							echo $previewOptions[$scheme];
							echo '</td></tr>';
					}
				}
			}
			echo '</table>';
		}
		
		echo '</td></tr>';
		
		// Show the Default Value form
		?>
		<tr>
			<td>
				<b><?php echo gettext('Default Value')?></b><br />
				<?php echo gettext('Use this field to set an optional set of objects to which new objects in this scheme will be associated by default')?>.
			</td>
			<td>
		<table>
		<tr><td>
		<?php
		echo '<select name="defaultValue" id="defaultValue" size="5">';
  
		if (isset($options->defaultValue->value))
		{
			$i = 0;
			foreach($options->defaultValue->value as $value)
			{
				echo '<option value="'.$i.'">'.htmlEscape((string)$value).'</option>';
				$i++;
			}
		}
	
		echo '</select>'
		?>
		</td>
			<td>
				<input type="button" value="<?php echo gettext('Remove')?>" onclick="removeDefaultValue()" /><br /><br />
				<input type="button" value="<?php echo gettext('View Record')?>" onclick="viewDefaultValueObject()" />
			</td></tr>
		<tr>
			<td><input type="button" value="<?php echo gettext('Up')?>" onclick="moveDefaultValue('up')" /></td>
			<td><input type="button" value="<?php echo gettext('Down')?>" onclick="moveDefaultValue('down')" /></td>
		</tr>
		<tr>
			<td><input type="text" name="defValSearch" id="defValSearch" /></td>
			<td><input type="button" value="<?php echo gettext('Find a Record')?>" onclick="findDefaultValue()" /></td>
		</tr>
		<tr>
			<td><input type="text" name="Input<?php echo $cid?>" id="Input<?php echo $cid?>" /></td>
			<td><input type="button" value="<?php echo gettext('Add')?>" onclick="addDefaultValue()" /></td>
		</tr>
		</table>
			</td>
		</tr>
		</table>
		</form>
		<?php
	}
	
	public static function updateBoxes($cid, $schemeid, $checked)
	{
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		// this must be done in reverse if we want to unset more than one
		$n = 0;
		foreach($xml->scheme as $scheme){
			if((string)$scheme == $schemeid){
				unset($xml->scheme[$n]);
				break;
			}
			$n++;
		}

		if ($checked == "true"){
			$xml->addChild('scheme',$schemeid);
		}
		
		setControlOptions($cid, $xml);
	}

	public static function moveDefaultValue($cid, $default, $direction)
	{
		global $db;
		
		// Make sure the default to be moved is a number (corresponding to position in list)
		if (is_numeric($default)) $default = (int)$default;
		else return;
		
		// Make sure the direction is valid
		if (!in_array($direction, array('up', 'down'))) return;

		
		// get the XML list of modifiers
		$xml = getControlOptions($cid);

		// Ensure that the key is valid
		if (!isset($xml->defaultValue->value[$default])) return;

		// Otherwise, make sure this isn't a redundant move
		if ($direction == 'up'){
			if( $default == 0 ) return;
			$newPos = $default-1;
			
		}
		else if($direction == 'down'){
			if( $default == count($xml->defaultValue->value)-1 ) return;
			$newPos = $default+1;
		}
		$defaultValue = $xml->defaultValue->value[$default];
		echo 'newpos: '.$newPos;
		echo 'default: '.$default;
		
		$values = array();
		$n = 0;
		foreach($xml->defaultValue->value as $value){
			if($n == $default) {
				$n++;
				continue;
			}
			if($direction == 'down') $values[]=(string)$value;
			if($n == $newPos) $values[]= (string)$defaultValue;
			if($direction == 'up')   $values[]=(string)$value;
			$n++;
		}
				
		unset($xml->defaultValue,$value);
		$xml->addChild('defaultValue');
		
		foreach($values as $value){
			$xml->defaultValue->addChild('value',$value);
		}
	
		setControlOptions($cid, $xml);
	}
	
	public static function removeDefaultValue($cid, $default)
	{
		// Make sure the default to be moved is a number (corresponding to position in list)
		if (is_numeric($default)) $default = (int)$default;
		else return;
		
		// get the XML list of modifiers
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		// no default values to remove!
		if (!isset($xml->defaultValue)) return;
		
		// Ensure that the key is valid
		if (isset($xml->defaultValue->value[$default])){
			unset($xml->defaultValue->value[$default]);
		}
		
		setControlOptions($cid, $xml);
	}
	
	public static function addDefaultValue($cid, $default)
	{
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		// Verify that the KID corresponds to a record in a scheme we're
		// allowed to associate to
		$allowed = false;
		$kidDetails = parseRecordID($default);
		if ($kidDetails){
			foreach($xml->scheme as $s)	{
				if ((string)$s == $kidDetails['scheme']){
					$allowed = true;
					break;
				}
			}
		}
		if(!$allowed) return;

		if (!isset($xml->defaultValue)) $xml->addChild('defaultValue');
		$xml->defaultValue->addChild('value', xmlEscape($default));
		setControlOptions($cid, $xml);
	}
	
	public static function setPreview($cid, $schemeid, $preview){
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		unset($xml->{'preview'.$schemeid});
		
		if($preview != ''){
			list($previewCid,$type) = explode('_',$preview);
			$node = $xml->addChild('preview'.$schemeid);
			$node->addChild('cid',xmlEscape($previewCid));
			$node->addChild('type',xmlEscape($type));
		}
		setControlOptions($cid, $xml);
	}
}

// Handle the AJAX Calls
if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'AssocControl'){
	$action = $_POST['action'];
	if($action == 'updateBoxes') {
		AssociatorControlOptions::updateBoxes($_POST['cid'], $_POST['schemeid'], $_POST['checked']);
		AssociatorControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'addDefaultValue') {
		AssociatorControlOptions::addDefaultValue($_POST['cid'], $_POST['defaultV']);
		AssociatorControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'removeDefaultValue') {
		AssociatorControlOptions::removeDefaultValue($_POST['cid'], $_POST['defaultV']);
		AssociatorControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'moveDefaultValue') {
		AssociatorControlOptions::moveDefaultValue($_POST['cid'], $_POST['defaultV'], $_POST['direction']);
		AssociatorControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'setPreview') {
		AssociatorControlOptions::setPreview($_POST['cid'], $_POST['schemeid'], $_POST['preview']);
		AssociatorControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'showDialog') {
		AssociatorControlOptions::showDialog($_POST['cid']);
	}
}

?>
