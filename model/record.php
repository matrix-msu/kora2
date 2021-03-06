<?php
namespace KORA;

use KORA\Manager;
use KORA\Scheme;
use Exception;
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

// Initial Version: Joseph Deming, 2013

require_once(__DIR__.'/../includes/includes.php');

Manager::AddCSS('javascripts/colorbox/colorbox.css', Manager::CSS_LIB);
Manager::AddJS('javascripts/colorbox/jquery.colorbox-min.js', Manager::JS_LIB);
// TODO: MOVE THIS TO TEXT-EDITOR CLASS?
Manager::AddJS('ckeditor/ckeditor.js', Manager::JS_LIB);
Manager::AddJS('javascripts/record.js', Manager::JS_CLASS);
// TODO: MOVE THIS TO CONTROL CLASS?
Manager::AddJS('javascripts/control.js', Manager::JS_CLASS);

/**
 * @class Record object
 *
 * This class respresents a Record in KORA
 */
class Record
{
	protected $pid;
	protected $sid;
	protected $rid = null;          // if this is not null, the form is editing; if it is, it's submitting.
	protected $presetid = null;     // the id of another record in the scheme to copy the values from
	protected $pubingest = null;
	protected $hasdata = false;
	
	// DB HERE SHOULD REALLY BE A CLASS EVENTUALLY
	protected $db;
	protected $controlcollections;
	protected $associatedrecords = null;
	
	/**
	  * Constructor for a Record model
	  *
	  * @param int $pid_ Project ID the Record belongs to
	  * @param int $sid_ Scheme ID the Record belongs to
	  * @param int $rid_ Record ID
	  *
	  * @return void
	  */
	function __construct($pid_, $sid_, $rid_=null)
	{
		global $db; $this->db = $db;
		
		$this->pid = $pid_;
		$this->sid = $sid_;
		$this->rid = $rid_;
		
		// verify pid, sid, rid, presetid are accurate and correspond to data within the system
		$test = $this->db->query("SELECT schemeName FROM scheme WHERE schemeid='".$this->sid."' AND pid='".$this->pid."' LIMIT 1");
		
		if ($test->num_rows <= 0) {
			try{ 
				throw new Exception(gettext('Invalid pid and sid combination when creating Record object')); 
			}catch(Exception $e){
				return $e->GetMessage();
			}
		}
		
		// Catch if a record ID was provided.  This has to correspond to a record from
		// the provided project/scheme, but does not have to correspond to an existing
		// object due to the way new object ingestion is handled.
		if (!empty($this->rid)) 
		{
			//check if rid is valid format
			$rid = Record::ParseRecordID($this->rid);
			
			if (!($rid && ($rid['pid'] == $this->pid) && ($rid['sid'] == $this->sid)))
			{ 
				try{ 
					throw new Exception(gettext('Invalid rid when creating Record object, rid must align with project and scheme'));
				}catch(Exception $e){
					return $e->GetMessage();
				} 
			}			
		}
		
		$this->LoadControls();
	}
	
	/**
	  * Loads the scheme controls that make up this Record from the DB
	  *
	  * @return void
	  */
	protected function LoadControls()
	{
		$controls = array();
		// INIT THIS ARRAY WITH THE '0'/INTERNAL COLLID DEFINED
		$this->controlcollections = [ 0 => ['name' => 'Internal', 'description' => 'Kora Record Data', [] ] ];
		
		// get a list of the collections in the scheme
		$collectionQuery = $this->db->query('SELECT collid, name, description, sequence FROM collection WHERE schemeid='.escape($this->sid).' ORDER BY sequence DESC');
		while($coll = $collectionQuery->fetch_assoc())
		{
			$this->controlcollections[$coll['collid']] = array('name' => $coll['name'], 'description' => $coll['description'], 'controls' => array());
		}
		
		$cTable = 'p'.$this->pid.'Control';
		
		// get an ordered list of the controls in the project
		$controlQuery =  "SELECT $cTable.name AS name, $cTable.cid AS cid, $cTable.collid AS collid, $cTable.sequence AS sequence, ";
		$controlQuery .= "$cTable.description AS description, $cTable.type AS type, $cTable.publicEntry AS publicEntry, $cTable.required AS required ";
		$controlQuery .= "FROM $cTable LEFT JOIN collection USING (collid) WHERE $cTable.schemeid=";
		$controlQuery .= escape($this->sid)." ORDER BY collection.sequence, $cTable.sequence";
		// THIS LINE USED TO BE IN A SEPARATE 'IF' !publicIngest... SO CONSIDER THIS TINY DETAIL WHEN OUTPUTING PUBINGEST FORM
		//$controlQuery .= escape($this->sid)." AND $cTable.type != 'AssociatorControl' ORDER BY collection.sequence, $cTable.sequence";
		
		$controlQuery = $this->db->query($controlQuery);
		
		$controlList = array();
		while ($ctrl = $controlQuery->fetch_assoc()) {
			$cobj = Manager::GetControl($this->pid, $ctrl['cid'], $this->rid);
			$this->controlcollections[$ctrl['collid']]['controls'][] = $cobj;
			if ($cobj->HasData()) { $this->hasdata = true; }
		}
	}
	
	public function GetPID() { return $this->pid; }
	public function GetSID() { return $this->sid; }
	public function GetRID() { return $this->rid; }
	// HELPER ALIAS FOR SAME FUNCTION
	public function GetKID() { return $this->GetRID(); }
	public function isGood() { return (($this->pid != '') && ($this->sid != '')); }
	public function HasData() { return $this->hasdata; }
	public function isPublic() { return $this->pubingest; }
	
	/**
	  * Gather all data that was ingested to create the Record
	  *
	  * @return result string on error
	  */
	public function GetImportData()
	{
		global $db;
		
		$data = $_REQUEST['ingestdata'];
		$map = $_REQUEST['ingestmap'];
		
		if (!is_array($data)) { Manager::PrintErrDiv(gettext('Invalid data was submitted for ingestion')); return null; }
		if (!is_array($map)) { Manager::PrintErrDiv(gettext('Invalid datamap was submitted for ingestion')); return null; }
		
		$finaldata = array();
		foreach ($data as $index => $cdata)
		{
			// WE GET THE NAME FROM THE CORRESPONDING MAP ARRAY, AGAIN, HATE USING 2 
			// ARRAYS BUT SINCE IT'S PASSED FROM JQUERY AJAX CAN'T DO ASSOC OR OBJ INCOMING
			$cname = $map[$index];
			
			// THESE USUALLY COME IN AS ARRAYS FROM JQUERY POST, FIX THEM ASSUMING SO
			if (is_array($cname)) { $cname = reset($cname); }
			
			// WIERD CASE WHERE WE'RE LOOKING UP CONTROL BY NAME INSTEAD OF KNOWN CID SO DIRECT SQL QUERY HERE IS OK
			$query = "SELECT cid,type,options FROM p".$this->pid."Control WHERE schemeid=".$this->sid." AND name='".$cname."' LIMIT 1";
			$query = $db->query($query);
			$paramData = $query->fetch_assoc();
			
			// CRAP, NOT SURE ABOUT THIS, FIGURED IT WAS DEFAULT, BUT APPEARS TO BE AN AUTO-FILL BASED ON EXTENDED LOOKUP
			$paramOptions = simplexml_load_string($paramData['options']);
			//TODO: More autofill stuff
			/*if (isset($paramOptions->autoFill) && !empty($paramOptions->autoFill)) {
				$i = new Importer($this->GetPID(),$this->GetSID());
				$autoFillVal = $i->FindAutoFill($paramOptions,$paramData,$value);
				if ($autoFillVal) {
					$$finaldata[$autoFillVal[0]] = $autoFillVal[1];
				}
			}*/
			if (!empty($cdata)) { $finaldata[$cname] = is_array($cdata) ? $cdata : array($cdata); }
		}
		
		return $finaldata;
	}	
	
	/**
	  * Get a list of the controls in this Record
	  *
	  * @return Array of control objects
	  */
	public function GetControls()
	{
		$controls = [];
		foreach ($this->controlcollections as $coll)
			{ $controls = array_merge($coll['controls'], $controls); }
		return $controls;
	}
	
	/**
	  * Get a list of all Records that associate to this record
	  *
	  * @return Array of rids
	  */
	public function GetAssociatedRecords()
	{
		// LAZY LOAD
		if ($this->associatedrecords) { return $this->associatedrecords; }
		
		global $db;
		$this->associatedrecords = array();
		
		$assocQuery = $db->query('SELECT value FROM p'.$this->GetPID().'Data WHERE id='.escape($this->GetRID()).' AND cid=0 LIMIT 1');
		if ($assocQuery->num_rows > 0)
		{
			$assocQuery = $assocQuery->fetch_assoc();
			$xml = simplexml_load_string($assocQuery['value']);
			
			if (isset($xml->assoc->kid)){ 
				$kids = (array)$xml;
				if(!is_array($kids['assoc'])){
					array_push($this->associatedrecords,$kids['assoc']->kid);
				}else{
					for($i=0;$i<sizeof($kids['assoc']);$i++){
						array_push($this->associatedrecords,$kids['assoc'][$i]->kid);
					}
				}
			}
		}
		
		return $this->associatedrecords;
	}
	
	/**
	  * Get the next record ID available in the sequence
	  *
	  * @return new rid
	  */
	public function GetNewRecordID()
	{
		global $db;
		
		$s = new Scheme($this->GetPID(),$this->GetSID());
		
		$updateQuery = $db->query("UPDATE scheme SET nextid=nextid+1 WHERE schemeid=".escape($this->sid)." LIMIT 1");
		return  strtoupper(dechex($this->GetPID())).'-'
		.strtoupper(dechex($this->GetSID())).'-'
		.strtoupper(dechex($s->GetNextID()));
	}
	
	/**
	  * Manually assigns a new rid to the record
	  *
	  * @param int $rid_ New Record ID
	  *
	  * @return void
	  */
	public function SetNewRID($rid_)
	{
		$this->rid = $rid_;
		foreach ($this->GetControls() as $ctrl)
		{ $ctrl->SetNewRID($rid_); }
	}
	
	/**
	  * Sets if this Record is a public record
	  *
	  * @param bool $public Is it public or not
	  *
	  * @return void
	  */
	public function SetPublicIngestion($public)
	{
		//Added by James 1/14/14
		if ($public == true)
		{
			$this->pubingest = true;
		}
		else
		{
			$this->pubingest = false;
			// Copied from old code
			// bad setup, so clear all variables to prevent use of this form
			$this->pid = $this->sid = $this->rid = $this->presetid = '';
		}
	}
	
	/**
	  * Create a record from a preset
	  *
	  * @param string $kid_ Record ID of the preset
	  *
	  * @return void
	  */
	public function LoadPreset($kid_)
	{
		$pr = new Record($this->pid, $this->sid, $kid_);
		foreach ($pr->GetControls() as $pc) {
			foreach ($this->GetControls() as $c) {
				if ($pc->GetCID() == $c->GetCID()) {
					$c->SetValue($pc->GetValue());
				}
			}
		}			
	}
	
	/**
	  * Print out the html for a Kora ingestion form
	  *
	  * @param bool $publicIngest Is this record being ingested publically?
	  *
	  * @return void
	  */
	public function PrintRecordDisplay($publicIngest = false)
 	{
 		if (!$this->isGood()) {
 			echo gettext('There was an error preparing the ingestion form').'.';
 			
 			return;
 		}
 		
 		echo '<br /><strong>';
 		printf(gettext('Controls marked with a %s are required'),'<font color="#FF0000">*</font>');
 		echo '</strong>';
		
		if(Manager::CheckRequestsAreSet(['preset'])){
			echo "<br />".gettext("File and image fields do not populate during the cloning process. In order to clone files or images, you must download them from the original record and upload them into the appropriate field of the cloned record.");
		}
		
		echo "<br /><br />";

		// BEGIN OUTPUT HERE
 		$currpagecount = 0;
 		$collDivs = array();
		//controllcollection is displayed in DESC order, ingestion uses ASC order, so we
		// are reversing the order, however the recordowner/timestamp still needs to be hidden
		//  so the second line puts those fields back at the first place of the array.
		$this->controlcollections = array_reverse($this->controlcollections);
		array_unshift($this->controlcollections, array_pop($this->controlcollections));
		
 		foreach ($this->controlcollections as $coll)
 		{
 			// DON'T EVEN PRINT A PAGE WITH NO CONTROLS IN IT'S COLLECTION
 			if (sizeof($coll['controls']) <= 0) { continue; }
 			// DON"T DISPLAY INTERNAL CONTROLS
 			if($publicIngest && $coll['name']=='Internal') { continue; }

			ob_start();     
			echo '<div class="'.'id'.$currpagecount.' controlCollection">'."\n";
			echo '<h3>'.htmlEscape($coll['name']).'</h3>';
			echo '<p id="thickboxDescrip">'.htmlEscape($coll['description']).'</p>'."\n";
			echo '<br/><input type="submit" class=kcri_submit value="'.gettext('Submit Data').'" /><br/><br/>';
			foreach($coll['controls'] as $ctrl)
			{
				$display = false;
				if($publicIngest){
					//If were are ingesting publically and a control isn't allowed to be ingested publically, we don't want to display it
					if($ctrl->GetRequired()){
						//required controls must be public
						$display = true;
					}
					else if($ctrl->GetShowInPublicResults()){
						//this control is allowed to be public
						$display = true;
					}
				}else{
					$display = true;
				}
				
				if($display){
					// THIS OUTPUTS THE ACTUAL COLUMNS
					echo '<div class="ctrlEdit"><div><strong>'.htmlEscape($ctrl->GetName()).'</strong>';
					if ($ctrl->GetRequired()) echo ' <font color="#FF0000" class="kc_required">*</font> ';
					echo '</div><br>';
					echo '<div id="inlineInput">';
					$ctrl->display();
					echo '</div>';
					if ($ctrl->GetDesc() != '') { echo '<div class="ctrlDesc">'.htmlEscape($ctrl->GetDesc()).'</div>'; }
					echo '</div>'."<br />";
				}
			}
			
			echo "\n</div>\n";
			$collDivs[$currpagecount] = ob_get_contents();
			ob_end_clean();         // flush the output buffer
			$currpagecount++;
 		}
 		//THIS FORM IS LEFT A FORM BECAUSE PUBLIC INGESTION RELYS ON IT.?>		
		<div id='ajaxstatus'></div>
		
		<form name="ingestionForm" id="ingestionForm" class='ingestionForm' enctype="multipart/form-data" method="post">
		<div id="navNum1" class="kora_navNumbers"></div>
		<input type="hidden" name="ingestionForm" value="true" />
		<input type="hidden" name="pid" value="<?php echo $this->pid?>" />
		<input type="hidden" name="sid" value="<?php echo $this->sid?>" />
		<input type="hidden" name="rid" value="<?php echo $this->rid?>" />
		
		<?php foreach($collDivs as $c) echo $c."\n"; ?>
		<div id="navNum2" class="kora_navNumbers"></div>
		
		<?php
		
		//only want reCAPTCHA to display for external sites, don't ever want it to show up in internal KORA
		if($publicIngest)
		{
			?>
			<a id="previewer" title="Preview">Preview Entry</a>
			<script type="text/javascript" src="http://api.recaptcha.net/challenge?k=6LdGJwAAAAAAAHpPVLBwS4Hdwy7DIicU48JsoaHR"></script>
			<noscript>
			<iframe src="http://api.recaptcha.net/noscript?k=6LdGJwAAAAAAAHpPVLBwS4Hdwy7DIicU48JsoaHR" height="300" width="500" frameborder="0"></iframe>
			<textarea name="recaptcha_challenge_field" rows="3" cols="40">
			</textarea>
			<input type="hidden" name="recaptcha_response_field" value="manual_challenge">
			</noscript>
			<?php
			echo recaptcha_get_html( PUBLIC_KEY );
		}
		
		//        echo '<input id="pPage" type="button" value="Previous Page" onclick="prevPage();" />';
		//        echo '<input id="nPage" type="button" value="Next Page" onclick="nextPage();" />';
		//        echo '<br /><br /><input type="submit" value="Submit Data" />';
		echo '<br /><br /><input type="submit" class=kcri_submit value="'.gettext('Submit Data').'" />';
		?>
		</form>
		
		
		<?php
	}
	
	/**
	  * Prints out a readable view of the record
	  *
	  * @param bool $showall_ Determines if all hidden record data is shown
	  *
	  * @return void
	  */
	public function PrintRecordView($showall_ = false)
	{
		global $db;
		
		echo '<table class="table kr_view">';
		$ctrls = $this->GetControls();
		foreach($ctrls as &$ctrl)
		{
			if ($showall_ || $ctrl->GetShowInResults())
			{
				echo '<tr><td class="kora_ccLeftCol">'.$ctrl->GetName().'</td><td>';
				echo $ctrl->showData();
				echo '</td></tr>';
			}
		}
		
		if (sizeof($this->GetAssociatedRecords()) > 0)
		{
			$assockids = array();
			foreach($this->GetAssociatedRecords() as $assockid){
				array_push($assockids,$assockid);
			}
			if(sizeof($assockids)>0){
				echo '<tr><td colspan="2"><strong>'.gettext('The following records associate to this record').':</strong><br /><br />';
				foreach(array_unique($assockids) as $assockid)
				{
					$parts = explode('-',$assockid);
					$apid = hexdec($parts[0]);
					$asid = hexdec($parts[1]);
					
					$collection = $db->query('SELECT * FROM collection WHERE schemeid='.$asid.' AND sequence=1');
					if ($collection->num_rows > 0){
						$acollid = $collection->fetch_assoc()['collid'];
					}
					
					$control = $db->query('SELECT * FROM p'.$apid.'Control WHERE schemeid='.$asid.' AND collid='.$acollid.' AND sequence=1');
					if ($control->num_rows > 0){
						$acid = $control->fetch_assoc()['cid'];
					}
					
					$ctrlprev = Manager::GetControl($apid,$acid, $assockid);

					echo '<a href="viewObject.php?rid='.(string)$assockid.'">'.(string)$assockid.'</a> ['.$ctrlprev->showData().']<br />';
				}
				echo '<br /><em>'.gettext('Note: You may not have authorization to view any or all of these records').'</em><br />';
				echo '</td></tr>';    
			}				
		}
		echo '</table>';
	}

	/**
	  * Prints out html form for using a Record preset
	  *
	  * @return void
	  */
	public function PrintUseRecordPreset()
	{
		$presetQuery = $this->db->query('SELECT name, kid FROM recordPreset WHERE schemeid='.$this->sid.' ORDER BY name ASC');
		if ($presetQuery->num_rows > 0)
		{
			
			
			?>
			<table class="table">
			<tr>
			<td style="width:30%"><strong><?php echo gettext('Load Values from Preset');?>:</strong></td>
			<td><select name="preset" class='krwhatpreset'>
			<?php 
			while ($presetRow = $presetQuery->fetch_assoc())
			{
				echo '<option value="'.$presetRow['kid'].'">'.htmlEscape($presetRow['name']).'&nbsp;'.'</option>';	
			}
			?>    
			</select>
			<input type="button" value="<?php echo gettext('Load');?>" class='krusepreset' /></td>
			</tr>
			</table>
			
			<?php 
		} // endif presetQuery->num_rows > 0
	}
	
	/**
	  * Prints out html form for record deletion
	  *
	  * @return void
	  */
	public function PrintRecordDeleteForm(){
		?><table border="0" class="kr_delete_form"><tr>
			<input type="hidden" value="<?php echo $this->pid; ?>" name="pid" />
			<input type="hidden" value="<?php echo $this->sid; ?>" name="sid" />
			<input type="hidden" value="<?php echo $this->rid; ?>" name="rid" />
			<td><input type="button" class="kr_delete_yes" value="<?php echo gettext('Yes')?>" /></td>
			<td><input type="button" class="kr_delete_no" value="<?php echo gettext('No')?>" /></td>
		</tr></table>
		
		<?php
	}
	
	/**
	  * Prints out html form for managing Record presets
	  *
	  * @param string $sid Scheme ID
	  * @param string $pid Project ID
	  *
	  * @return void
	  */
	public static function PrintRecordPresetsForm($sid, $pid){
		echo '<h2>'.gettext('Manage Record Presets').'</h2>';
		echo '<p>'.gettext('Note: demoting an record will cause it to no longer be a preset, but the record will still remain and will now appear in search results, etc.  To truly delete the record, please select').' "'.gettext('Delete').'".</p><br />';

		Record::showRecordPresetDialog($sid,$pid);
		
		?>
		<br />
		<strong><?php echo gettext('Rename a Preset');?></strong><br />
		<table class="table" id="presetRecordTableRename">
		<tr>
		    <td><strong><?php echo gettext('Old Name');?></strong></td>
		    <td><select name="oldName" id="oldName"><?php Record::loadRecordPresetList($sid);?></select></td>
		</tr>
		<tr>
		    <td><strong><?php echo gettext('New Name');?></strong></td>
		    <td><input type="text" name="newName" id="newName" /></td>
		</tr>
		<tr>
		    <td>&nbsp;</td>
		    <td><input type="button" value="<?php echo gettext('Rename');?>" class="preset_record_rename" /></td>
		</tr>
		</table>
		<?php 
	}
	
	/**
	  * Breaks up a RID into an array parts
	  *
	  * @param int $rid Record ID to parse
	  *
	  * @return array of parsed information (pid,sid,rid,etc.)
	  */
	public static function ParseRecordID($rid)
	{
		// make sure it conforms to the pattern
		// pattern: #-#-#(c#)* where # represents a string of digits
		// First # : project id
		// Second # : scheme  id
		// Third # : record  id
		// Successive # : child relationships?
		// Where # is a hexadecimal number (capital letters only)
		if (!preg_match("/^[0-9A-F]+-[0-9A-F]+-[0-9A-F]+(-[0-9A-F]+)*$/", $rid))
		{
			echo '<br> '.'manager.php, rid has wrong format'.'<br>';
			return false;
		}
		
		$fields = explode('-', $rid);
		//echo $rid . '<br>';
		//var_dump($fields);
		$numfields = count($fields);
		
		$result = array('project' => hexdec($fields[0]),
						'scheme'  => hexdec($fields[1]),
						'record'  => hexdec($fields[2]),
						'pid'     => hexdec($fields[0]),
						'sid'     => hexdec($fields[1]),
						'rid'     => $rid
		);
		
		//var_dump($result);
		if ($numfields > 3) 
		{
			$result['child'] = array();
			for($i = 3; $i < $numfields; $i++) $result['child'][] = hexdec($fields[$i]);
		}

		return $result;
	}
	
	/**
	  * Ingest data into a record
	  *
	  * @param string $overrideData Override data from import
	  * @param bool $publicIngest Are we ingesting publically
	  *
	  * @return result on error
	  */
	public function ingest($overrideData=null, $publicIngest = false)
	{
		// update or insert the data into the database.  No idea how this is going to work yet.
		if (!$this->isGood()) {
			echo gettext('There was an error preparing the ingestion form').'.';
			return false;
		}
		// get list of collections and controls.  Display them in <div> tags with ids corresponding
		// to the collection id.  Include Javascript scripts and buttons to jump between pages.  Call
		// the display() method on each control to have it show.
		
		// THIS HANDLES SETTING OVERRIDE DATA PASSED IN, TYPICALLY FROM AN IMPORT
		$ctrls = $this->GetControls();
		foreach($ctrls as &$ctrl)
		{
			if (!empty($overrideData) && isset($overrideData[$ctrl->GetName()])) {
				$ctrl->setXMLInputValue($overrideData[$ctrl->GetName()]);
			}
		}
		
		$ingestionGood = true;
		$allEmpty = true;
		$errorList = array();
		
		foreach($ctrls as &$ctrl)
		{
			// make sure the control is OK for ingestion - required fields have data, etc.
			$iVal = $ctrl->validateIngestion($publicIngest);
			if (!empty($iVal)) {
				$ingestionGood = false;
				$errorList[] = $iVal;
			}
			$allEmpty = $allEmpty && $ctrl->isEmpty();
		}
		
		if ($ingestionGood && !$allEmpty) {
			
			$isNewRec = false;
			if (!$this->rid)
			{
				$newrid = $this->GetNewRecordID();
				$this->SetNewRID($newrid);
				$isNewRec = true;
			}
			
			if($this->rid) {  //should always be true
				$query = "SELECT * FROM dublinCore WHERE kid = '$this->rid' LIMIT 1";
				$query_result = $this->db->query($query);
				$result =& $query_result;
				if($result->num_rows != 0 ) {  // record exists in dublinCore table, kill it so it can be inserted after edit.
					$query = "DELETE FROM dublinCore WHERE kid = '$this->rid' LIMIT 1";
					$this->db->query($query);
				}
			}
			
			// NOTE FROM JMD, I DON'T KNOW WHY WE CHECK/USE SESSION HERE BUT NEEDS TO BE KILLED EVENTUALLY!!!
			//if $_SESSION variable are defined, use them for DublinCore fields
			//otherwise use the class' scheme and project id
			$currentScheme = $this->sid;
			$currentProject = $this->pid;
			
			$dcfields = getDublinCoreFields($currentScheme,$currentProject);
			$dcarray = array(); //will be used to store xml objects for the DC fields.
			//$dcfieldarray = array();
			if (is_array($dcfields))
			{
				foreach($dcfields as $dcfield => $cids) {
					if($cids) $dcarray[$dcfield] = simplexml_load_string("<$dcfield></$dcfield>");
					
				}
			}
			
			// THIS LOOP HANDLES THE ACTUAL REQUEST HANDLING
			foreach($ctrls as &$control) {    
				//Hack specifically for the timestamp 'object' ... add the current time to the request
				//array under key cName
				if($control->GetName() == "systimestamp"){
					if(empty($overrideData) || !isset($overrideData["systimestamp"])) {
						$_REQUEST["p".$this->pid."c".$control->cid] = date('c');
					}
					//Also add this control to the dcfields array if there is at least 1 field in the array already
					//so that timestamp gets added to any record in the dublin core table
					if(is_array($dcfields) && !empty($dcfields)){
						//print_r($dcfields);
						$dcfields['timestamp']=array(); // LOOK AT HOW DUBLIN CORE DATA IS STORED ... index is likely not just 'systimestamp'
						$dcfields['timestamp'][]=simplexml_load_string("<id>".$control->cid."</id>");
						$dcarray['timestamp'] = simplexml_load_string("<timestamp></timestamp>");
					}
				}
				
				//Hack specifically for the recordowner 'object' ... add the current time to the request
				//array under key cName
				if($control->GetName() == "recordowner")
				{
					//get the record owner
					
					if(empty($overrideData) || !isset($overrideData["recordowner"]))
					{
						if($publicIngest)
						{
							$recordOwner = "public ingestion";
						}
						else
						{
							$currUser = $_SESSION['uid'];
							$userquery = $this->db->query("SELECT username FROM user WHERE uid='$currUser'");
							$userquery = $userquery->fetch_assoc();
							$recordOwner = $userquery['username'];
						}
						$_REQUEST["p".$this->pid."c".$control->cid] = $recordOwner;
					}
					
					//Dublin core stuff?
					/*if(is_array($dcfields) && !empty($dcfields)){
					//print_r($dcfields);
					$dcfields['timestamp']=array(); // LOOK AT HOW DUBLIN CORE DATA IS STORED ... index is likely not just 'systimestamp'
					$dcfields['timestamp'][]=simplexml_load_string("<id>".$control->cid."</id>");
					$dcarray['timestamp'] = simplexml_load_string("<timestamp></timestamp>");
					}*/
				}
				
				$control->ingest($publicIngest);
				
				//add in dc on ingestion...
				if($dcfields) {
					foreach($dcfields as $dcfield => $cids) {
						foreach($cids as $cid) {
							if($cid == $control->cid) {
								$dcarray[$dcfield]->addChild($control->cid,$control->displayXML());
							}
						}
					}
				}
			} // end foreach controlList as control
			
			// create the query and insert into the dublinCore table
			if($dcfields) {
				$query = "INSERT INTO dublinCore(kid,pid,sid,";
				$query .= implode(',',array_keys($dcarray)).") VALUES ('$this->rid','$this->pid','$this->sid',";
				
				$xmlarray = array();
				foreach($dcarray as $dctype => $values) {
					$xmlstring = simplexml_load_string("<$dctype></$dctype>");
					foreach($values as $id => $value) {
						$xmlstring->addChild($id,xmlEscape($value));
					}
					$xmlarray[] = escape($xmlstring->asXML());
				}
				$query .= implode(',',$xmlarray);
				$query .= ")";
				//               print "<br /> .".htmlEscape($query)." <br />   ";
				$this->db->query($query);
				echo $this->db->error;
			}
			
			if($publicIngest)
			{
				//doesn't increment when adding to publicData table
				$incQuery = "UPDATE scheme SET nextid = nextid + 1 WHERE schemeid=".$this->sid;
				$incResult = $this->db->query($incQuery);
				if(!$incResult)
				{
					echo gettext("A database error has occured. Scheme nextid failed to increment") . '<br>';
				}

				echo gettext('Object Ingested and awaiting approval from a moderator.').'<br/><br/>';
			}
			
			return true;
			
		}
		else {
			//doesnt work for multi-text controls/multi-date control
			echo gettext('Ingestion Failed.  See below errors.').'<br />';
			foreach($errorList as $e) echo "<div class=\"error\">".gettext($e)."</div>";
			if ($allEmpty) echo '<div class="error">'.gettext('At least one control must have data').'.</div>';
			
			return false;
		}
	}
	
	/**
	  * Self delete a Record and all it's data
	  *
	  * @return void
	  */
	public function Delete()
	{
		// First, the obvious part: Instantiate all of its controls and let them delete themselves.
		// This allows for pleasant things like files and thumbnails actually getting deleted with the
		// record; imagine that! 
		$rid=$this->GetRID();
		$kid=$this->GetKID();
		
		foreach ($this->GetControls() as $ctrl)
			{ 
				\AssociatorControl::RemoveReverseAssociation($rid,$kid,$ctrl->GetCID());
				$ctrl->delete(); 
			}
			
		// Clean the associations
		\AssociatorControl::CleanUpAssociatorOnDelete($rid);
		
		return false;
		
		// Also remove any references to the record in the object preset table
		$db->query('DELETE FROM recordPreset WHERE kid='.escape($rid));
		
		// And Dublin Core
		$db->query('DELETE FROM dublinCore WHERE kid='.escape($rid).' LIMIT 1');
		
		// And Public Database
		$db->query('DELETE FROM p'.$this->pid.'PublicData WHERE id='.escape($rid));
		
	}
	
	/////////////////////////////////
	///These functions handle presets
	/////////////////////////////////
	
	/**
	  * Adds a record preset to Kora
	  *
	  * @param int $kid Record ID that is being set as a preset
	  * @param string $name Name of the new preset
	  * @param int $sid Scheme that record belings to
	  *
	  * @return result on error
	  */
	public static function addRecordPreset($kid, $name, $sid)
	{
		global $db;
		
		// Make sure the object is in the current scheme
		$objectInfo = Record::ParseRecordID($kid);
		
		if (!$objectInfo)
		{
			die('<div class="error">'.gettext('Invalid').' KID</div>');
		}
		if ($objectInfo['scheme'] != $sid)
		{
			die('<div class="error">'.gettext('Object is not in current scheme').'</div>');
		}
		if (strlen($name) < 1)
		{
			die('<div class="error">'.gettext('You cannot have an empty name').'</div>');
		}
		
		if (!Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT))
		{
			die('<div class="error">'.gettext('You do not have permission to create presets').'.</div>');
		}
		
		// Make sure the name is available
		$availableQuery = $db->query('SELECT recpresetid FROM recordPreset WHERE schemeid='.$objectInfo['scheme'].' AND name='.escape($name).' LIMIT 1');
		if ($availableQuery->num_rows > 0)
		{
			die('<div class="error">'.gettext('There is already a preset with that name').'.</div>');
		}
		
		// Make sure the KID is available
	    $availableQuery = $db->query('SELECT recpresetid FROM recordPreset WHERE kid='.escape($kid).' LIMIT 1');
	    if ($availableQuery->num_rows > 0)
	    {
	        die('<div class="error">'.gettext('This record is already a preset').'.</div>');
	    }
		
		// Add the kid to the recordPreset table
		$db->query('INSERT INTO recordPreset (schemeid, name, kid) VALUES ('.$objectInfo['scheme'].', '.escape($name).', '.escape($kid).')');
		
		//TODO: This isn't pushing to the page. Check record JS?
		echo gettext('Preset Added');
	}
	
	/**
	  * Prints out the table of record presets
	  *
	  * @param int $sid Scheme ID
	  * @param int $pid Project ID
	  *
	  * @return result string on error
	  */
	public static function showRecordPresetDialog($sid,$pid)
	{
		global $db;
		
		//requireScheme();
	    //requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');	
		
	    // Get the list of Presets for the current scheme
	    $presetQuery = $db->query('SELECT name, kid FROM recordPreset WHERE schemeid='.$sid.' ORDER BY name ASC');
	    
	    if ($presetQuery->num_rows > 0)
	    {
			?>
			<table class="table" id="PresetRecordTable">
			<tr>
			    <td><strong><?php echo gettext('Name');?></strong></td>
			    <td><strong><?php echo gettext('ID');?></strong></td>
			    <td><strong><?php echo gettext('Demote');?></strong></td>
			    <td><strong><?php echo gettext('Delete');?></strong></td>
			</tr>
			<?php 
	    	while($preset = $presetQuery->fetch_assoc())
	    	{
	    	   echo '<tr><td>'.htmlEscape($preset['name']).'</td><td>'.$preset['kid'].'</td>';
	    	   echo '<td><u><a class="preset_record_demote" name="'.$preset['kid'].'">X</a></u></td>';
	    	   echo '<td><a href="deleteObject.php?pid='.$pid.'&sid='.$sid.'&rid='.$preset['kid'].'">X</a></td></tr>';
	    	}
	    	
	    	echo '</table>';
	    }
	    else
	    {
	    	echo gettext('This scheme currently has no presets').'.';
	    }
	}
	
	/**
	  * Deletes a record preset from a scheme
	  *
	  * @param int $sid Scheme ID
	  * @param int $kid Record ID of the preset
	  *
	  * @return void
	  */
	public static function demoteRecordPreset($kid, $sid)
	{
		global $db;
		
	    //requireScheme();
	    //requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');
	
	    // Make sure the kid is valid and from the current scheme
	    $kidInfo = Record::ParseRecordID($kid);
	    if ($kid && $kidInfo['scheme'] == $sid)
	    {    
	        $db->query('DELETE FROM recordPreset WHERE kid='.escape($kid));
	    }
	}
	
	/**
	  * Rename a record preset
	  *
	  * @param int $sid Scheme ID
	  * @param int $kid Record ID of the preset
	  * @param string $name New name of the prest
	  *
	  * @return result on error
	  */
	public static function renameRecordPreset($kid, $name, $sid)
	{
		global $db;
		
	    //requireScheme();
	    //requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');
	    
	    // Make sure the kid is valid and from the current scheme
	    $kidInfo = Record::ParseRecordID($kid);
	    if ($kid && $kidInfo['scheme'] == $sid)
	    {
	    	// Make sure no other preset in this scheme already uses this name
	    	$nameQuery = $db->query('SELECT name FROM recordPreset WHERE schemeid='.$sid.' AND name='.escape($name).' LIMIT 1');
	    	if ($nameQuery->num_rows == 0)
	    	{
	            $db->query('UPDATE recordPreset SET name='.escape($name).' WHERE kid='.escape($kid));
	    	}
	    	else
	    	{
	    		echo '<div class="error">'.gettext('There is already a preset with that name').'!</div><br />';
	    	}
	    }
	}
	
	/**
	  * Populate the record preset table
	  *
	  * @param int $sid Scheme ID
	  *
	  * @return void
	  */
	public static function loadRecordPresetList($sid)
	{
	    global $db;
	    
	    //requireScheme();
	    //requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');
	
	    $presetQuery = $db->query('SELECT name, kid FROM recordPreset WHERE schemeid='.$sid.' ORDER BY name ASC');
	
	    while($preset = $presetQuery->fetch_assoc())
	    {
	    	echo '<option value="'.$preset['kid'].'">'.htmlEscape($preset['name']).'</option>';
	    }
	}
}

?>
