<?php
use KORA\Manager;
use KORA\ControlCollection;
use KORA\Record;
use KORA\Scheme;
use KORA\KoraSearch;
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
// Refactor: Joe Deming, Anthony D'Onofrio 2013

require_once(__DIR__.'/../includes/includes.php');
Manager::AddJS('controls/associatorControl.js', Manager::JS_CLASS); 

/**
 * @class AssociatorControl object
 *
 * This class respresents a AssociatorControl in KORA
 */
class AssociatorControl extends Control {
	protected $name = "Associator Control";
	protected $options;

	/**
	  * Standard constructor for a control. See Control::Construct for details.
	  *
	  * @return void
	  */
	public function AssociatorControl($projectid='', $controlid='', $recordid='', $inPublicTable = false)
	{
		if (empty($projectid) || empty($controlid)) return;
		global $db;
		
		$this->Construct($projectid,$controlid,$recordid,$inPublicTable);
		
		// THESE OPTIONS ARE NOT VALID FOR THIS CLASS
		$this->isRequiredValid = false;
		$this->isAdvSearchableValid = false;
		$this->isShowInPublicResultsValid = false;

		//$this->options = simplexml_load_string($this->options);

		// If data exists for this control, get it

		// If data exists for this control, get it
		if (!empty($this->rid))
		{
			$this->LoadValue();
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
	
	/**
	  * Delete this control from it's project
	  *
	  * @return void
	  */
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
						AssociatorControl::RemoveReverseAssociation((string)$kid, $a['id'], $this->cid);
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
						AssociatorControl::RemoveReverseAssociation((string)$kid, $a['id'], $this->cid);
					}
				}
			}
			// Delete the Data
			$deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid));

			// We don't need to Repeat for PublicData becuase associator controls cannot be filled out in public ingestion.
		}
		
		//function must be present in all delete extentions of base class, Control
		$this->deleteEmptyRecords();
	}
	
	/**
	  * Prints control view for public ingestion
	  *
	  * @param bool $isSearchForm Is this for a search form instead
	  *
	  * @return void
	  */
	public function display($defaultValue=true) {
		global $db;

		if (!$this->StartDisplay()) { return false; }
		?>
		<?php
		echo '<table border="0">';
		echo '<tr><td><input type="text" class="kcac_findrec" name="Search'.$this->cid.'" value="" /></td>';
		echo '<td><input type="button" class="kcac_findrec" value="'.gettext('Find a Record').'" /></td></tr>';
		echo gettext('Search using exact KID. Partial or incorrect KIDs will produce no results.');
		echo '<tr><td>';
		echo '<select id="'.$this->cName.'" name="'.$this->cName.'[]" class="kcac_curritems fullsizemultitext" multiple="multiple" size="5">'."\n";
		if (isset($this->value->kid))
		{
			foreach($this->value->kid as $kid) {
				echo '<option value="'.(string)$kid.'">'.(string)$kid."</option>\n";
			}
		}
		echo "</select>\n</td>";
		echo '<td><input type="button" class="kcac_moveitemup" value="'.gettext('Up').'" /><br />';
		echo '<input type="button" class="kcac_moveitemdown" value="'.gettext('Down').'" /><br />';
		echo '<input type="button" class="kcac_removeitem" value="'.gettext('Remove').'" /><br />';
		echo '<input type="button" class="kcac_viewitem" value="'.gettext('View Record').'" /></td></tr>';
		echo '</table>';
		$this->EndDisplay();
	}
	
	/**
	  * Print out the XML value of the RAC
	  *
	  * @return void
	  */
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

	/**
	  * Return string to enter into a Kora_Clause (RAC Incompatible)
	  *
	  * @param string $submitData Submited data for control
	  *
	  * @return false
	  */
	public function getSearchString($submitData) {
		return false;
	}
	
	public function getType() { return "Record Associator"; }
	
	/**
	  * Set the value of the XML imput
	  *
	  * @param string $value Value to set
	  *
	  * @return void
	  */
	public function setXMLInputValue($value) {
		$this->XMLInputValue = $value;
	}
	
	/**
	  * Ingest the data into the control
	  *
	  * @param string $publicIngest Are we ingesting the data publically
	  *
	  * @return void
	  */
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
		if (isset($_REQUEST[$this->cName])) {
				$kids = $_REQUEST[$this->cName];
		}
		else if( isset($this->XMLInputValue) && !empty($this->XMLInputValue)) {
				$kids = $this->XMLInputValue;
		}
		
		if (!empty($kids)) {
			// Get the list of projects/schemes we're allowed to associate to
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
			  
		  	  $kidDetails = Record::ParseRecordID($kid);
		  	  
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
		
		if ($this->existingData)
		{
			if ($this->isEmpty())
			{
					if (isset($this->value->kid)) {
						foreach($this->value->kid as $kid)
						{
						// clean the record
						AssociatorControl::RemoveReverseAssociation((string)$kid, $this->rid, $this->cid);
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
					if ($kInfo = Record::ParseRecordID($kid))
					{
						AssociatorControl::AddReverseAssociation($kid, $this->rid, $this->cid);
					}
				}
				
				foreach($removeFrom as $kid)
				{
					if ($kInfo = Record::ParseRecordID($kid))
					{
						AssociatorControl::RemoveReverseAssociation($kid, $this->rid, $this->cid);
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
					AssociatorControl::AddReverseAssociation($kid, $this->rid, $this->cid);
				}
			}
		}
	}
	
	/**
	  * Initialize function for control options
	  *
	  * @return void
	  */
	public static function initialOptions()
	{
		return '<options><defaultValue /></options>';
	}
	
	/**
	  * Does this control have data in it?
	  *
	  * @return true on success
	  */
	public function isEmpty() {
		return !( !empty($_REQUEST[$this->cName]) || isset($this->XMLInputValue));
	}

	public function isXMLPacked() { return true; }
	
	/**
	  * Get the data from the control for display
	  *
	  * @return control data
	  */
	public function showData() {
		
		$returnString = '';
		if (isset($this->value->kid))
		{
			$returnString = '<b>'.gettext('Currently Associated Objects').':</b><br />';
			$returnString = '<table>';
			foreach($this->value->kid as $kid)
			{
				$returnString .= '<tr><td><a href="viewObject.php?rid='.(string)$kid.'">'.(string)$kid.'</a></td><td>';
				$kidInfo = Record::ParseRecordID((string)$kid);
				if (isset($this->options->{'preview'.$kidInfo['scheme']})){
					$previewCid = (string)$this->options->{'preview'.$kidInfo['scheme']}->cid;
					$previewControl = Manager::GetControl($kidInfo['project'],$previewCid,(string)$kid);
					$returnString .= $previewControl->showData();
				}
				$returnString .='</td></tr>';
			}
			$returnString .='</table>';
		}
		return $returnString;
	}
	
	/**
	  * Gather information about control for display
	  *
	  * @param string $xml XML to write information to
	  * @param int $pid Project ID
	  * @param int $cid Control ID
	  *
	  * @return XML object
	  */
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
	
	/**
	  * Gathers values from XML
	  *
	  * @param string $xml XML object to get data from
	  *
	  * @return Array of values
	  */
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
	
	/**
	  * Validates the ingested data to see if it meets the data requirements for this control
	  *
	  * @param bool $publicIngest Is this a public ingestion
	  *
	  * @return Result string
	  */
	public function validateIngestion($publicIngest = false) {
		if ($this->required && $this->isEmpty()){
			return gettext('No value supplied for required field').': '.htmlEscape($this->name);
		}
		
		if ($this->required || !$this->isEmpty()) {		
			$kids = array();
			if (isset($_REQUEST[$this->cName])) {
				$kids = $_REQUEST[$this->cName];
			} else if( isset($this->XMLInputValue) && !empty($this->XMLInputValue)) {
				$kids = $this->XMLInputValue;
			}
			
			// check the format for all kids
			$kids = explode(',',$kids[0]);
			if(!empty($kids)){
				foreach($kids as $kid){
					$kidDetails = Record::ParseRecordID($kid);
					if ($kidDetails === false){
						return gettext("Invalid Record ID format").': '.htmlEscape($this->name);
					}
				}
			}
		}
		// we can't test if records exist for imported controls,
		// and normal ingestion *shouldn't* allow invalid records.
		return '';
	}
	
	/**
	  * Prints out list of records to assoc with
	  *
	  * @param string $keywords Keywords for search
	  *
	  * @return void
	  */
	public function PrintAssocSearch($keywords)
	{
		$privs = $this->GetAssocPrivs();
		if (!empty($privs['projects']) && !empty($privs['schemes']))
		{			
			$searchLink = 'href="ajax/control.php?action=assocSearch&pid='.urlencode($this->GetPID()).'&sid='.urlencode($this->GetSID()).'&cid='.urlencode($this->cid).'&keywords='.urlencode($keywords).'&page=%d"';
			$page = (isset($_REQUEST['page'])) ? (int)$_REQUEST['page'] : 1;
			
			echo "<div class='assoc_search_results' kcid='".urlencode($this->cid)."'>";
			$kids = KoraSearch::sortedInternalSearchResults($privs['projects'], $privs['schemes'], $keywords, 'AND', false, false, true);
			$this->PrintAssocSearchResults($kids);
			echo "</div>";
		}
		else { print gettext('You do not have access to associate to any schemes'); }
	}
	
	/**
	 * Takes an array of KIDs and prints out proper tags to show the associate_this_record link after JS has kicked in
	 *
	 * @param array $results_ array of KIDs
	 *
	 * @return true if print success, false otherwise
	 */
	public function PrintAssocSearchResults($results_)
	{
		$ctrlopts = $this->GetControlOptions();
		
		// TODO: MAYBE FIND A BETTER WAY TO PASS THIS ADJACENT_PAGES_SHOWN VAR TO JAVASCRIPT?
		print "<div class='ks_results' navlinkadj='".ADJACENT_PAGES_SHOWN."' >";
		print "<div class='ks_results_navlinks'></div><div class='ks_results_numresults'>Num Results</div><br /><br />";
		$currpage = 1;
		$currpagecount = 0;
		foreach ($results_ as $kid)
		{
			$recinfo = Record::ParseRecordID($kid);
			if (!$recinfo) { continue; }
			
			// PAGE SEPARATION
			if ($currpagecount == 0)
			{ print "<div class='ks_results_page' page='$currpage' >"; }
			
			print "<div class='kcac_assresult_item' pid='${recinfo['pid']}' sid='${recinfo['sid']}' rid='${recinfo['rid']}' loaded='false' >";
			// ONLY DIFFERENCE BETWEEN THIS AND THE STANDARD KORA SEARCH RESULTS RETURN IS THE CLASS (PREV LINE) AND THE EXTRA DATA INSIDE THE DIV (THIS)
			print "<span class='link kcac_assview' kcac_assval=$kid >$kid</span>";
			if (isset($ctrlopts->{'preview'.$recinfo['sid']}) && isset($ctrlopts->{'preview'.$recinfo['sid']}->cid))
			{
				// ONLY LOOK THIS JAZZ UP IF WE HAVE A 'PREVIEW' FIELD SET, OTHERWISE JUST PRINT THE KID W/OUT THE WASTED EFFORT
				$ctrlprev = Manager::GetControl($recinfo['pid'],(int)$ctrlopts->{'preview'.$recinfo['sid']}->cid, $kid);
				print "<span class='kcac_assresultprev'>:&nbsp;&nbsp;".$ctrlprev->showData()."</span>";
				
			}
			print "<span class='link kcac_assthis' kcac_assval=$kid >Associate This Record</span>";
			print "</div>";
			// A LITTLE EXTRA SPACING FOR THIS TYPE OF SEARCH, MAYBE DONE VIA .CSS LATER?
			print "<br /><br />";

			// PAGE SEPARATION
			if ($currpagecount == RESULTS_IN_PAGE)
			{ print "</div>"; $currpagecount = 0; $currpage++; }
			else
			{ $currpagecount++; }
		}
		// THIS WILL CLOSE OUT THE FINAL PAGE IF IT IS NOT EXACTLY THE RIGHT COUNT ALREADY
		if ($currpagecount != 0)
		{ print "</div>"; $currpagecount = 0; }
		
		print "<div class='ks_results_navlinks'></div><div class='ks_results_numresults'>Num Results</div>";
		print "</div>";
		return true;
	}
	
	/**
	  * Get a list of scheme and projects to assoc search
	  *
	  * @return list of schemes and projects
	  */
	public function GetAssocPrivs()
	{
		global $db;
		// get the list of schemes and projects to search
		$retary = array();
		
		$query = 'SELECT options FROM p'.$this->pid.'Control WHERE cid='.$this->cid.' LIMIT 1';
		$results = $db->query($query);
		if ($results->num_rows != 0)
		{
			// Get the list of acceptable schemes to search from the current scheme
			$schemeQuery = $db->query('SELECT crossProjectAllowed FROM scheme WHERE schemeid='.$this->sid.' LIMIT 1');
			$schemeQuery = $schemeQuery->fetch_assoc();
			$schemeQueryXML = simplexml_load_string($schemeQuery['crossProjectAllowed']);
			$schemeAllowedSchemes = array();
			if (isset($schemeQueryXML->from->entry))
			{
				foreach($schemeQueryXML->from->entry as $entry)
				{
					$schemeAllowedSchemes[] = (int)$entry->scheme;
				}
			}
		
			// Get the list of acceptable schemes to search from the current control
			$array = $results->fetch_assoc();
			if ($array['options'] != 'none')
			{
			   $xml = simplexml_load_string($array['options']);
			}
			else
			{
				$xml = simplexml_load_string('<options />');
			}
			
			if (!empty($xml->scheme))
			{
				$schemes = array();
				foreach($xml->scheme as $scheme)
				{
					// Make sure the scheme is in the list pulled from the scheme,
					// note just from the current control.  This is necessary due to
					// old control options being persisted when schemes are created
					// from templates
					if (in_array( (int)$scheme, $schemeAllowedSchemes ))
					{
						$schemes[] = (string)$scheme;
					}
				}
				
				if (!empty($schemes))
				{
				    $query = 'SELECT pid, schemeid FROM scheme WHERE schemeid IN ('.implode(',',$schemes).')';
				    $results = $db->query($query);
				    while($record = $results->fetch_assoc())
				    {
					$retary['projects'][] = $record['pid'];
					$retary['schemes'][] = $record['schemeid'];
				    }
				}
			}
		}
		
		if(isset($retary['projects'])){
			$retary['projects'] = array_unique($retary['projects']);
		}
		if(isset($retary['schemes'])){
			$retary['schemes'] = array_unique($retary['schemes']);
		}
		
		return $retary;
	}
	
	/**
	  * Get the list of currently allowed schemes for assoc
	  *
	  * @return Array of schemes
	  */
	public function GetAllowedAssociations() {
		global $db;
		
		// Get the list of currently allowed schemes from the pXControl table
		$currentSchemes = array();
		
		// The escape clause below IS necessary because $cid comes right from a POST variable which could
		// presumably be spoofed.
		$query = 'SELECT options FROM p'.$this->pid.'Control WHERE cid='.escape($this->cid).' LIMIT 1';
		$results = $db->query($query);
		if ($results->num_rows != 0) {
			$array = $results->fetch_assoc();
			if ($array['options'] == 'none') $array['options'] = '<options />';
			$xml = simplexml_load_string($array['options']);
			if (!empty($xml->scheme)) {
				foreach($xml->scheme as $scheme) $currentSchemes[] = (string)$scheme;
			}
		}
		
		return $currentSchemes;
	}
	
	/**
	  * Add a tag to the record being associated
	  *
	  * @param string $targetRecord Record ID to add association
	  * @param string $kidFrom Record ID that is associating
	  * @param int $cidFrom Control ID of the RAC from $kidFrom
	  *
	  * @return void
	  */
	public static function AddReverseAssociation($targetRecord, $kidFrom, $cidFrom)
	{
		global $db;
		
		// See if the target and association KIDs are valid
		$tInfo = Record::ParseRecordID($targetRecord);
		$aInfo = Record::ParseRecordID($kidFrom);
		
		if ($tInfo && $aInfo)
		{
			$table = 'p'.$tInfo['project'].'Data';
			
			$existenceQuery = $db->query("SELECT value FROM $table WHERE id=".escape($targetRecord).' AND cid=0 LIMIT 1');
			if ($existenceQuery->num_rows < 1)
			{
				// the record currently has no reverse association XML
				$xml = '<reverseAssociator><assoc><kid>'.xmlEscape($kidFrom).'</kid><cid>'.xmlEscape($cidFrom).'</cid></assoc></reverseAssociator>';
				$db->query('INSERT INTO '.$table.' (id, cid, schemeid, value) VALUES ('.escape($targetRecord).', 0, '.$tInfo['scheme'].', '.escape($xml).')');
			}
			else
			{
				$existenceQuery = $existenceQuery->fetch_assoc();
				$xml = simplexml_load_string($existenceQuery['value']);
				
				$duplicate = false;
				// Make sure this isn't a duplicate
				if (isset($xml->assoc))
				{
					foreach($xml->assoc as $assoc)
					{
						if (((string)$assoc->kid == $kidFrom) && ((string)$assoc->cid == $cidFrom))
						{
							$duplicate = true;
						}
					}
				}
				if (!$duplicate)
				{
					$assoc = $xml->addChild('assoc');
					$assoc->addChild('kid', xmlEscape($kidFrom));
					$assoc->addChild('cid', xmlEscape($cidFrom));
					$db->query('UPDATE '.$table.' SET value='.escape($xml->asXML()).' WHERE cid=0 AND id='.escape($targetRecord).' LIMIT 1');
				}
			}
		}
	}
	
	/**
	  * Remove a tag from the record being associated
	  *
	  * @param string $targetRecord Record ID to add association
	  * @param string $kidFrom Record ID that is associating
	  * @param int $cidFrom Control ID of the RAC from $kidFrom
	  *
	  * @return void
	  */
	public static function RemoveReverseAssociation($targetRecord, $kidFrom, $cidFrom)
	{
		global $db;
		
		// See if the target and association KIDs are valid
		$tInfo = Record::ParseRecordID($targetRecord);
		$aInfo = Record::ParseRecordID($kidFrom);
		
		if ($tInfo && $aInfo)
		{
			$table = 'p'.$tInfo['project'].'Data';
			
			$existenceQuery = $db->query("SELECT value FROM $table WHERE id=".escape($targetRecord).' AND cid=0 LIMIT 1');
			if ($existenceQuery->num_rows > 0)
			{
				$existenceQuery = $existenceQuery->fetch_assoc();
				$xml = simplexml_load_string($existenceQuery['value']);
				
				$newXML = simplexml_load_string('<reverseAssociator />');
				
				// Build the new XML set
				if (isset($xml->assoc))
				{
					foreach($xml->assoc as $assoc)
					{
						if (((string)$assoc->kid != $kidFrom) || ((string)$assoc->cid != $cidFrom))
						{
							$newAssoc = $newXML->addChild('assoc');
							$newAssoc->addChild('kid', (string)$assoc->kid);
							$newAssoc->addChild('cid', (string)$assoc->cid);
						}
					}
				}
				
				// Update the database.  If there's still at least one reverse assocation,
				// update the row.  Otherwise, delete it
				if (isset($newXML->assoc))
				{
					$db->query('UPDATE '.$table.' SET value='.escape($newXML->asXML()).' WHERE cid=0 AND id='.escape($targetRecord).' LIMIT 1');
				}
				else
				{
					$db->query('DELETE FROM '.$table.' WHERE cid=0 AND id='.escape($targetRecord).' LIMIT 1');
				}
				
			}
			// else { we don't care because there's no record to clean }
		}
	}
	
	/**
	  * Clean up associations on deletion of Record
	  *
	  * @param string $rid Record ID being deleted
	  *
	  * @return void
	  */
	public static function CleanUpAssociatorOnDelete($rid)
	{
		global $db;
		
		$kidInfo = Record::ParseRecordID($rid);
		$pid = $kidInfo['project'];
		
		$assocQuery = $db->query('SELECT value FROM p'.$pid.'Data WHERE id='.escape($rid).' AND cid=0 LIMIT 1');
		if ($assocQuery->num_rows > 0)
		{
			$assocQuery = $assocQuery->fetch_assoc();
			$xml = simplexml_load_string($assocQuery['value']);
			
			if (isset($xml->assoc->kid))
			{
				foreach($xml->assoc->kid as $kid)
				{
					AssociatorControl::RemoveAllAssociations($kid, $rid);
				}
			}
		}
		
		// Clean up the list of things that associate to this record
		$db->query('DELETE FROM p'.$pid.'Data WHERE cid=0 AND id='.escape($rid).' LIMIT 1');
	}
	
	/**
	  * Remove all associations from a record
	  *
	  * @param string $fromKID Record ID to be cleaned
	  * @param string $toKID TODO: Not entirely sure what this does
	  *
	  * @return void
	  */
	public static function RemoveAllAssociations($fromKID, $toKID)
	{
		global $db;
		
		$fromInfo = Record::ParseRecordID($fromKID);
		$toInfo = Record::ParseRecordID($toKID);
		
		if ($fromInfo && $toInfo)
		{
			$controlQuery = 'SELECT cid FROM p'.$fromInfo['project'].'Control WHERE schemeid='.$fromInfo['scheme'].' AND type=\'AssociatorControl\'';
			$dataQuery = 'SELECT id, cid, value FROM p'.$fromInfo['project'].'Data WHERE cid IN ('.$controlQuery.') AND id='.escape($fromKID);
			$dataQuery = $db->query($dataQuery);
			
			while($associator = $dataQuery->fetch_assoc())
			{
				$oldXML = simplexml_load_string($associator['value']);
				$newXML = simplexml_load_string('<associator />');
				
				foreach($oldXML->children() as $childType => $childValue)
				{
					if ($childType != 'kid')    // anything other than a <kid> tag should be
					{                           // maintained, although it shouldn't exist
						$newXML->addChild($childType, xmlEscape($childValue));
					}
					else if ($childValue != $toKID)   // otherwise, add in all <kid> tags except the
					{                               // one that needs to be deleted.
						$newXML->addChild($childType, xmlEscape($childValue));
					}
				}
				
				// Update the database
				if (isset($newXML->kid))
				{
					$db->query('UPDATE p'.$fromInfo['project'].'Data SET value='.escape($newXML->asXML()).'WHERE id='.escape($associator['id']).' AND cid='.escape($associator['cid']).' LIMIT 1');
				}
				else
				{
					$db->query('DELETE FROM p'.$fromInfo['project'].'Data WHERE id='.escape($associator['id']).' AND cid='.escape($associator['cid']).' LIMIT 1');
				}
			}
		}
	}

	/**
	  * Print out the control options for the control
	  *
	  * @return void
	  */
	public function PrintControlOptions()
	{
		Control::PrintControlOptions();
		$this->showDialog();
 	}

 	/**
	  * Print out each menu piece of the control options
	  *
	  * @return void
	  */
	public function showDialog()
	{
		print "<div class='kora_control kora_control_opts' pid='{$this->pid}' cid='{$this->cid}'>";
		$this->OptPrintDefaultValue();
		$this->OptPrintAllowedAssociations();
		print "</div>";
	}
	
	// TODO:  KILL ALL OF THESE UGLY TABLES
	/**
	  * Print out table for allowed associations
	  *
	  * @return void
	  */
	protected function OptPrintAllowedAssociations()
	{ 
		global $db;
		
		// Note: using form id="ingestionForm" should probably be classified as a hack.
		// It has to do with how the Associator Search Results page populates the field in
		// the calling page (normally, the actual Ingestion Form, but in this case, this page).
		// I'd be lying if I said I was truly COMFORTABLE with this naming scheme, but I see
		// no real danger and it saves a lot of recoding, so.....
		?>
		<table class="table kcopts_style">
		<tr>
		<td width="60%" class="kcopt_label"><b><?php print gettext('Association Permissions')?></b><br /><?php print gettext('Use the checkboxes to allow/disallow associating to records from the following schemes from this control')?>.</td>
		<td>
		<?php
			
		// IN THIS CONTEXT:
		// CPAVAIL    = SCHEMES THAT ARE AVIALABLE FOR ASSOCIATION
		// CPALLOWED  = SCHEMES THAT HAVE BEEN SET AS ALLOWED TO ASSOCIATE (I.E. THEY ARE CHECKED, I.E. THIS IS A SUBSET OF AVAIL) 
		$s = new Scheme($this->GetPID(), $this->GetSID());
		$cpavailxml = simplexml_load_string($s->GetCrossProject());
		$cpavail = array();
		if (!empty($cpavailxml->from) && !empty($cpavailxml->from->entry))
		{
			foreach ($cpavailxml->from->entry as $entry)
			{
				if (!isset($cpavail[(string)$entry->project])) { $cpavail[(string)$entry->project] = array(); }
				$cpavail[(string)$entry->project][(string)$entry->scheme] = (string)$entry->scheme;
			}
		}
		
		$cpallowed = $this->GetAllowedAssociations();
		
		// Display the Checkboxes!
		if (empty($cpavail))
		{
			print gettext('No schemes have granted access to this scheme yet').'.';
		}
		else
		{
			print '<table width="100%">';
			print '<tr><td><b>'.gettext('Scheme').'</b></td><td><b>'.gettext('Search').'?</b></td><td><b>'.gettext('Preview').'</b></td></tr>';
			foreach ($cpavail as $cppid => $cpap) 
			{
				foreach ($cpap as $cpas)
				{
					$cpasobj = new Scheme($cppid, $cpas);
					
					if($cpasobj->GetPID()==0){continue;}
					
					print '<tr><td>'.$cpasobj->GetProject()->GetName().'\\'.$cpasobj->GetName().'</td>';
					print '<td>'.'<input type="checkbox" class="kcacopts_cpallowed" pid="'.$cppid.'" sid="'.$cpas.'"';
					if (in_array($cpas, $cpallowed)) print ' checked ';
					print ' /></td>';
					print '<td><select class="kcacopts_cppreview" pid="'.$cppid.'" sid="'.$cpas.'" >';
					print "<option />";
					// THESE ARE THE SELECT OPTIONS FOR THE 'PREVIEW' FOR THE RECORD, ESSENTIALLY JUST A LISTING OF CONTROLS IN TARGET SCHEME IN GROUPS
					foreach ($cpasobj->GetControls() as $collid => $coll)
					{
						if ($collid == 0) { continue; }
						
						$objcoll = new ControlCollection($cppid, $cpas, $collid);
								
						if (sizeof($coll['controls']) != 0)
						{
							print "<optgroup label='".htmlspecialchars($objcoll->GetName())."'>";
							foreach ($coll['controls'] as $ctrl)
							{
								$selected = ((string)$this->GetControlOptions()->{'preview'.$cpas}->cid == $ctrl->cid) ? 'selected="selected"' : '';
								print '<option value="'.$ctrl->cid.'_'.$ctrl->GetType().'" '.$selected.'>';
								print htmlspecialchars($ctrl->GetName()).' ['.$ctrl->GetType().']</option>';
							}
						}
					}
					print "</select>";
					print '</td></tr>';
				}
			}
			print '</table>';
		}
		print '</td></tr></table>';
	}
	
	/**
	  * Print out table for default value
	  *
	  * @return void
	  */
	protected function OptPrintDefaultValue()
	{
		
		$opts = $this->GetControlOptions();
		// Show the Default Value form
		?>
		<table class="table kcopts_style">
		<tr>
		<td width="60%" class="kcopt_label">
		<b><?php print gettext('Default Value')?></b><br />
		<?php print gettext('Use this field to set an optional set of objects to which new objects in this scheme will be associated by default')?>.
		</td>
		<td>
		<?php
		echo '<table border="0"><tr><td>';
		echo '<select class="kcacopts_defcurritems fullsizemultitext" multiple="multiple" size="5">'."\n";
		if (isset($opts->defaultValue->value))
		{
			foreach($opts->defaultValue->value as $value)
			{ print '<option value="'.htmlEscape((string)$value).'">'.htmlEscape((string)$value).'</option>'; }
		}
		echo "</select>\n</td></tr>";
		echo '<tr><td><input type="button" class="kcacopts_defremoveitem" value="'.gettext('Remove').'" />	';
		echo '<input type="button" class="kcacopts_defmoveitemup" value="'.gettext('Up').'" />	';
		echo '<input type="button" class="kcacopts_defmoveitemdown" value="'.gettext('Down').'" /></td></tr>';
		echo '<tr><td><input type="text" class="kcacopts_defassrec" value="" /></td>';
		echo '<td><input type="button" class="kcacopts_defassrec" value="'.gettext('Add').'" /></td></tr>';
		echo '</table>';
		?>

		</td>
		</tr>
		</table>
		<?php
	}

	/**
	  * Update the schemes to search for a RAC
	  *
	  * @param Array[int] $schemes Schemes to add
	  *
	  * @return result string on success
	  */
	public function UpdateSearchSchemes($schemes)
	{
		$this->SetExtendedOption('scheme', $schemes);
		echo gettext('Searchable scheme(s) updated');
	}

	/**
	  * Update what schemes are in the preview for RAC
	  *
	  * @param string $schemeid Scheme ID to add
	  * @param string $preview Current preview
	  *
	  * @return result string on success
	  */
	// TODO: SHOULD BE ABLE TO DEPRECIATE THE NEED TO STORE THE 'TYPE' WITH THE PREVIEW SETTING WITH REFACTOR
	public function UpdatePreview($schemeid, $preview){
		list($pcid,$ptype) = explode('_',$preview);
		$this->SetExtendedOption('preview'.$schemeid, '<cid>'.xmlEscape($pcid).'</cid><type>'.xmlEscape($ptype).'</type>');
		echo gettext('Preview Option Updated').'.<br /><br />';
	}
	
	/**
	  * Update default value for RAC
	  *
	  * @param Array[string] $values Values to add
	  *
	  * @return result string
	  */
	public function updateDefaultValue($values)
	{
		$assprivs = $this->GetAssocPrivs();
		$defval = '';
		foreach ($values as $value)
		{
			$ridinfo = Record::ParseRecordID($value);
			
			if (!$ridinfo)
			{ Manager::PrintErrDiv("KID [$value] is not a valid record. Ignoring..."); continue; }
		
			if ((!in_array($ridinfo['pid'], $assprivs['projects'])) || (!in_array($ridinfo['sid'], $assprivs['schemes'])))
			{ Manager::PrintErrDiv("Not allowed to associate KID [$value] to this control. Ignoring..."); continue; }
		
			if(Manager::DoesRecordExist($value)){
				$defval .= "<value>".xmlEscape($value)."</value>"; 
			}else{
				Manager::PrintErrDiv("KID does not exist");
			}
		}
		$this->SetExtendedOption('defaultValue', $defval);
		echo gettext('Default Value Updated').'.<br /><br />';
	}	
}

?>
