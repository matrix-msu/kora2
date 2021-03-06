<?php
use KORA\Manager;
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
Manager::AddJS('controls/control.js', Manager::JS_CLASS); 

/**
 * @class Control object
 *
 * This class respresents a Control in KORA
 */
abstract class Control {
	// Controls should have a constructor like the following:
	// abstract function Control($projectid='', $controlid='', $recordid='', $presetid='')
	// The recordid field must be optional.  There must be a default null constructor
	// for the control list updater to work, but if the projectid or controlid is left
	// null, the control instance should be left empty.  The presetid is a record ID from
	// which data will be copied if it is provided.
	
	/**
	 * A Control must be able to delete its data and any other information stored
	 * (such as local files).  If the recordid is blank, this function should delete data
	 * about the control for all objects in the scheme it is part of.  If the recordid is
	 * populated, only data for that object should be removed.  This function is responsible
	 * for ONLY THE DATA.  Removing the control's record in the database and updating other
	 * associated responsbilitiy is in the jurisdiction of the helper functions such as
	 * deleteControl.
	 */
	//DID YOU CALL deleteEmptyRecords IN YOUR CONTROL DELETE
	abstract function delete();
	
	/**
	 * Display the class input form.  HTML input objects should be named
	 * such that multiple controls can appear and be distinguished on the same
	 * page.  Recommended naming scheme:
	 * <input type="whatever" name="p#c#"> or
	 * <input type="whatever" name="p#c#_suffix">
	 */
	abstract function display($defaultValue);
	
	abstract function displayXML();
	
	/**
	 * Return string to enter into a Kora_Clause
	 */
	abstract function getSearchString($submitData);
	
	/**
	 * return the control's type (i.e. "List Control")
	 */
	abstract function getType();
	
	/**
	 * set an XML value to override $_REQUEST data to insert into the databases
	 */
	abstract function setXMLInputValue($value);
	
	/**
	 * Process submitted form information from $_REQUEST variables or a XML file values
	 * as created in the display() function.  This function requires that $recordid be
	 * not null and associated with the current project and scheme of the current control.
	 * It should be able to differentiate between new ingestion and updating existing records.
	 */
	abstract function ingest();
	
	/**
	 * isEmpty tells if the control has received submitted data from an ingestion.
	 */
	abstract function isEmpty();

	/**
	 * isXMLPacked returns a boolean as to whether the control stores its
	 * data packed in XML format.
	 */
	abstract function isXMLPacked();
	
	/**
	 * 	showData is used for formatting the value of a control in such a way
	 *  that it can be shown inside KORA (i.e. in search results or the view
	 *  object page, etc.)
	 */
	abstract function showData();
	
	/**
	 * The Validation Function is used to make sure the input is OK without actually
	 * ingesting anything.  This is used so that all controls can be tested in an ingestion
	 * action before anything is actually inserted into the database
	 *
	 * Displays any Error Messages and returns an error string - empty if it's OK
	 * (i.e. not required or has data) and the error otherwise
	 */
	abstract function validateIngestion();
	
	/**
	 * Returns the initial XML value that the options part of the control's settings
	 * should be set to.
	abstract function initialOptions();
	
	 * Takes as input the contents of the control in the database and
	 * returns the value that would be output if the display() function was called
	 * on the control.  Also accepts a project id and control id if the control's
	 * options really need to be accessed
	 */
	abstract function storedValueToDisplay($xml, $pid, $cid);
	
	/**
	* Takes as input the contents of the control in the database and
	* returns a value or associative array of values suitable for returning
	* in external search results.
	*/
	abstract function storedValueToSearchResult($xml);
    
	/**
	  * Checks to see if the control has an ID and PID assigned
	  *
	  * @return true on success
	  */
	protected function isOK() {
		return (intval($this->pid) > 0 && intval($this->cid) > 0);
	}
	
	/**
	  * Base constructor for a Control model
	  *
	  * @param int $pid_ Project ID control belongs to
	  * @param int $cid_ Control ID for the control you want
	  * @param int $rid_ Record ID of the control you want
	  * @param bool $public_ Is the control in the public table
	  *
	  * @return void
	  */
	protected function Construct($pid_, $cid_, $rid_, $public_ = false)
	{
		global $db;
		
		$this->pid = $pid_;
		$this->cid = $cid_;
		$this->rid = $rid_;
		$this->cName = 'p'.$this->pid.'c'.$this->cid;
		$this->value = '';
		$this->existingData = false;  // needed just in case data is existing AND blank
		$this->inPublicTable = $public_;
        
		$controlCheck = $db->query('SELECT schemeid, name, description, required, searchable, advSearchable, showInResults, showInPublicResults, publicEntry, options, sequence, collid FROM p'.$this->pid.'Control WHERE cid='.escape($this->cid).' LIMIT 1');
		if ($controlCheck->num_rows > 0)
		{
		    $controlCheck = $controlCheck->fetch_assoc();
		    $this->sid = $controlCheck['schemeid'];
		    foreach(array('name', 'description', 'required', 'searchable', 'advSearchable', 'showInResults', 'showInPublicResults', 'publicEntry', 'options', 'sequence') as $field) {
			$this->$field = $controlCheck[$field];
		    }
		    $this->options = simplexml_load_string($this->options);
		    $this->cgroup = $controlCheck['collid'];
		}
		else
		{
		    $this->pid = $this->cid = $this->rid = $this->cName = '';
		    $this->options = simplexml_load_string($this->initialOptions());
		    return false;
		}
		
		$this->isRequiredValid = true;
		$this->isSearchableValid = true;
		$this->isAdvSearchableValid = true;
		$this->isShowInResultsValid = true;
		$this->isShowInPublicResultsValid = true;
		$this->isPublicEntryValid = true;
		$this->isSortValid = true;
		$this->hasFileStored = false;
	
		return true;
	}
	
	/**
	  * Starts off the display for a control in ingestion
	  *
	  * @param bool $prevalid Has the control already been validated for ingestion
	  *
	  * @return true on success
	  */
	protected function StartDisplay($prevalid=false)
	{
		if (!$this->isOK()) { return false; }
		
		$validtext = ($this->GetRequired()) ? 'invalid' : 'valid';
		
		if($this->HasData() | $prevalid | Manager::CheckRequestsAreSet(['preset'])){
			$validtext = 'valid';
		}
	
		echo '<div class="kora_control" kpid="'.$this->pid.'" ksid="'.$this->sid.'" kcid="'.$this->cid.'" kcclass="'.$this->GetClass().'" kcname="'.$this->GetName().'" kctype="'.$this->getType().'" kcvalid="'.$validtext.'" >';
		
		return true;
	}
	
	/**
	  * Ends display for control in ingestion form
	  *
	  * @return void
	  */
	protected function EndDisplay()
	{
		echo '<div class="ajaxerror"></div>';
		echo '</div>';
	}
	
	public function SetGroup($cgroup_) { $this->cgroup = $cgroup_; }
	public function GetPID() { return $this->pid; }
	public function GetSID() { return $this->sid; }
	public function GetCID() { return $this->cid; }
	public function GetName() { return $this->name; }
	public function GetGroup() { return $this->cgroup; }
	public function GetPreset() { return $this->preset; }
	public function GetDesc() { return $this->description; }
	public function GetClass() { return get_class($this); }
	public function GetRequired() { return $this->required; }
	public function GetSearchable() { return $this->searchable; }
	public function GetAdvSearchable() { return $this->advSearchable; }
	public function GetShowInResults() { return $this->showInResults; }
	public function GetShowInPublicResults() { return $this->showInPublicResults; }
	public function GetPublicEntry() { return $this->publicEntry; }
	public function GetSequence() { return $this->sequence; }
	public function GetValue() { return $this->value; }
	public function HasData() { return $this->existingData; }
	public function IsRequiredValid() { return $this->isRequiredValid; }
	public function IsSearchableValid() { return $this->isSearchableValid; }
	public function IsAdvSearchableValid() { return $this->isAdvSearchableValid; }
	public function IsShowInResultsValid() { return $this->isShowInResultsValid; }
	public function IsShowInPublicResultsValid() { return $this->isShowInPublicResultsValid; }
	public function IsShowInPublicEntryValid() { return $this->isPublicEntryValid; }
	public function IsSortValid() { return $this->isSortValid; }
	public function HasFileStored() { return $this->hasFileStored; }
	public function IsPublicIngest() { return $this->inPublicTable; }
	
	/**
	  * Gathers the permissions for a control preset
	  *
	  * @param int $presetID ID of the preset
	  *
	  * @return Array of permissions for the preset
	  */
	public static function GetPermsForPreset($presetID)
	{
		global $db;
		
		if (Manager::IsSystemAdmin())
		{
			return PROJECT_ADMIN;
		}
		$query = 'SELECT permGroup.permissions AS permissions FROM permGroup RIGHT JOIN member USING (gid) WHERE member.uid = '.$_SESSION['uid'].' AND member.pid IN (SELECT project FROM controlPreset WHERE presetid='.escape($presetID).') LIMIT 1';
		$query = $db->query($query);
		if ($query->num_rows == 0)
		{
			return 0;
		}
		else
		{
			$perms = $query->fetch_assoc();
			return $perms['permissions'];
		}
	}
	
	public function SetValue($val_)
	{ $this->value = $val_; }
	
	public function SetNewRID($rid_)
	{ $this->rid = $rid_; }

	public function SetRequired($val_)
	{ if ($this->SetCommonOption('required', $val_)) { $this->required = $val_; } }
	public function SetSearchable($val_)
	{ if ($this->SetCommonOption('searchable', $val_)) { $this->searchable = $val_; } }
	public function SetAdvSearchable($val_)
	{ if ($this->SetCommonOption('advSearchable', $val_)) { $this->advSearchable = $val_; } }
	public function SetShowInResults($val_)
	{ if ($this->SetCommonOption('showInResults', $val_)) { $this->showInResults = $val_; } }
	public function SetShowInPublicResults($val_)
	{ if ($this->SetCommonOption('showInPublicResults', $val_)) { $this->showInPublicResults = $val_; } }
	
	/**
	  * Set the name of the control
	  *
	  * @param string $name_ Name to change to
	  *
	  * @return true on success
	  */
	public function SetName($name_)
	{
		global $db;
		global $invalidControlNames;
		
		// Verify that the new name is valid
		if (empty($name_))
		{
			// Make sure it's not blank
			$errorMessage = gettext('You must provide a name for the control.');
		}
		else if (in_array(strtoupper($name_), $invalidControlNames))
		{
			// Make sure it's not on the list of censored keywords
			$errorMessage = '"'.$name_.'" '.gettext('is not a valid control name').'.';
		}
		else
		{
			$nameQuery  = "SELECT cid FROM p".$this->pid."Control ";
			$nameQuery .= "WHERE schemeid=".$this->sid;
			$nameQuery .= " AND name='".trim($name_)."' AND cid != ".escape($this->cid);
			$nameQuery .= " LIMIT 1";
			$nameQuery = $db->query($nameQuery);
			if ($nameQuery->num_rows != 0) {
				$errorMessage = gettext('That name is already used by another control in this scheme.');
			}
		}
		
		if (empty($errorMessage))
		{
			$db->query("UPDATE p".$this->pid."Control set name='".trim($name_)."' WHERE cid=".escape($this->cid));
		}

		if (!empty($errorMessage))
		{ Manager::PrintErrDiv('<div class="error">'.gettext($errorMessage).'</div>'); return false; }
	
		$this->name = $name_;
		return true;
	}

	/**
	  * Set the description of a control
	  *
	  * @param string $val_ Description of control
	  *
	  * @return true on success
	  */
	public function SetDesc($val_)
	{
		global $db;
		
		$result = $db->query("UPDATE p".$this->pid."Control SET description=\"".$val_."\" WHERE cid=".$this->cid);
		
		if(!$result) {
			Manager::PrintErrDiv(gettext("Error setting description for cid").' ['.$this->cid.'] '.gettext("to value").' ['.$val_.']');
			echo $db->error;
			return false;
		}
		
		$this->description = $val_;
		return true;
	}	
	
	/**
	  * Set one of the common options for a control
	  *
	  * @param string $opt_ Option to be changed
	  * @param string $val_ Value of the option
	  *
	  * @return true on success
	  */
	protected function SetCommonOption($opt_, $val_)
	{
		global $db;
		
		// JUST HANDLE COMMON CASE WHERE VAL IS PASSED IN AS STRING READING 'TRUE/FALSE', ANYTHING ELSE WE BAIL..
		if (!is_bool($val_))
		{ if (strtolower($val_) === 'true') { $val_ = true; } elseif (strtolower($val_) === 'false') { $val_ = false; } }
		if (!is_bool($val_)) { return false; }
		// NOW IT SHOULD BE EITHER TRUE OR FALSE (BOOLEAN) HAVE TO SET THAT TO ZERO/ONE FOR MYSQL HAPPINESS
		$val_ = ($val_) ? 1 : 0;
		
		$result = $db->query("UPDATE p".$this->pid."Control SET $opt_=".$val_." WHERE cid=".$this->cid);
		
		if(!$result) {
			Manager::PrintErrDiv("Error setting option [$opt_] for cid [".$this->cid."] to value [$val_]");
			echo $db->error;
			return false;
		}
		
		return true;
	}
	
	/**
	  * Set an extended option on a control
	  *
	  * @param string $opt_ Option to be changed
	  * @param string $val_ Value of the option
	  *
	  * @return true on success
	  */
	public function SetExtendedOption($opt_, $val_)
	{	
		$xml = $this->GetControlOptions();
		if(!$xml) return false;
		
		// JUST TRUST ME WE HAVE TO DO THIS TO DELETE EXISTING ELEMENTS, READ SIMPLEXMLELEMENT DOCS ALL YOU WANT =(
		$delme = $xml->xpath("/options/$opt_");
		while (is_array($delme) && (sizeof($delme) > 0))
		{
			unset($delme[0][0]);
			$delme = $xml->xpath("/options/$opt_");
		}
		
		// IF SOMEONE PASSED IN ARRAY TO SET, WE HAVE TO HANDLE MULTIPLE NODES
		if (is_array($val_))
		{ foreach ($val_ as $aval) { Control::AppendExtendedXMLOption($xml, $opt_, $aval);	}	}
		// ELSE THEY PASS IN STRING
		else
		{ Control::AppendExtendedXMLOption($xml, $opt_, $val_);	}
		
		$this->SetControlOptions($xml);
		
		return true;
	}
	
	/**
	  * Add extended option to the control XML
	  *
	  * @param string $xml_ XML to add option to
	  * @param string $opt_ Option to be added
	  * @param string $val_ Value of the option
	  *
	  * @return void
	  */
	protected static function AppendExtendedXMLOption(&$xml_, $opt_, $val_)
	{
		// TRUST ME AGAIN, WE HAVE TO DO THIS TO HANDLE THE POSSIBLITY FOR A CALLER TO 
		// PASS IN A PROPERLY FORMATTED XML STRING AND HAVE THAT INJECTED AS AN ACTUAL OBJECT
		// AS OPPOSED TO IT BEING XML ESCAPED INTO &lt; ETC,ETC, W/OUT THIS WE CANNOT PASS
		// NESTED XML ITEMS INTO THIS FUNCTION FOR COMPLEX OPTIONS SETTINGS =\
		// WE HAVE TO USE/ABUSE DOMDocument OBJECT AND IT'S INTEROPARABILITY W/ SIMPLEXML ELEMENT
		// BEFORE ALL OF THIS CODE, TO JUST SET TEXT IT WAS AS EASY AS THESE 2 LINES
		//if ($escape_) { $val_ = xmlEscape($val_); }
		//$xml->{$opt_} = $val_;
		
		$dnew = new DOMDocument;
		$dnew->loadXML("<$opt_>$val_</$opt_>");
		
		$dold = new DOMDocument;
		$dold->loadXML($xml_->asXML());
		$dold->getElementsByTagName('options')->item(0)->appendChild($dold->importNode($dnew->getElementsByTagName($opt_)->item(0), true));
		
		$dold->formatOutput = true;
		
		$xml_ = simplexml_import_dom($dold);
	}
	
	/**
	  * Load the data for this control from the DB to the control model
	  *
	  * @return void
	  */
	public function LoadValue()
	{
		global $db;
		$tableName = ($this->inPublicTable) ? "PublicData" : "Data";

		$valueCheck = $db->query('SELECT value FROM p'.$this->pid.$tableName.' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1');
		if ($valueCheck->num_rows > 0) {
			$this->existingData = true;
			$valueCheck = $valueCheck->fetch_assoc();
			$this->value = ($this->isXMLPacked()) ? simplexml_load_string($valueCheck['value']) : $valueCheck['value'];
		}
	}
	
	/**
	  * Export value of control to an XML
	  *
	  * @param string $simplexml XML to export to
	  *
	  * @return XML object
	  */
	public function ExportToSimpleXML(&$simplexml) 
	{
		if ($this->isXMLPacked())
		{
			foreach($this->value as $val){
				$simplexml->addChild(str_replace(' ', '_', $this->GetName()), xmlEscape($val));
			}
		}
		else
		{
			$simplexml->addChild(str_replace(' ', '_', $this->GetName()), xmlEscape($this->value));
		}
		
		return $simplexml;
		
	}
	
	/**
	  * Get the options of a control in XML form
	  *
	  * @param string $cid Control ID to get options from
	  *
	  * @return XML object
	  */
	public function GetControlOptions($cid=0){
		global $db;
		
		if($cid==0){$cid=$this->cid;}
		
		// Get control options
		$results = $db->query('SELECT options FROM p'.$this->pid.'Control WHERE cid='.escape($cid).' LIMIT 1');
		if (!is_object($results) || $results->num_rows != 1){
			echo gettext('Improper Control ID or Project ID Specified').'.';
			return false;
		}
		$result = $results->fetch_assoc();
		$xml = simplexml_load_string($result['options']);
		return $xml;
	}
	
	/**
	  * Set the options of a control using an XML form
	  *
	  * @param string $xml XML object with the options
	  * @param string $cid Control ID to get options from
	  *
	  * @return void
	  */
	public function SetControlOptions($xml,$cid=0){
		global $db;
		
		if($cid==0){$cid=$this->cid;}
	
		$query = 'UPDATE p'.$this->pid.'Control SET options='.escape($xml->asXML());
		$query .= ' WHERE cid='.escape($cid).' LIMIT 1';
		$db->query($query);
	}
	
	/**
	  * Initialize function for control options
	  *
	  * @return void
	  */
	public static function initialOptions()
	{
		return '<options></options>';
	}
	
	/**
	  * Deletes any Records that have no controls beyond a record owner or time stamp
	  *
	  * @return void
	  */
	public function deleteEmptyRecords(){
		global $db;
		//get pid/sid
		$pid = $this->pid;
		$sid = $this->sid;
		//get records
		$ridQuery = "SELECT id, count(id) AS count FROM p".$pid."Data where schemeid=".$sid." GROUP BY id";
		$results = $db->query($ridQuery);
		//deletes any records that remain after the last control in a scheme is deleted
		while($row = $results->fetch_assoc()){
			if((int)$row['count']<3){
				$rid = $row['id'];
				$delQ = "DELETE FROM p".$pid."Data WHERE id='".$rid."'";
				$db->query($delQ);
			}
		}
	}
	
	/**
	  * Prints out the html for a control in the scheme page
	  *
	  * @param string $ePerms User permissions that effect what elements are viewable
	  *
	  * @return void
	  */
	public function PrintControlRow($ePerms){
		?>
		<div class="kcgcl-row scheme_control clearfix" id="dsc_1" kcid="<?php echo $this->cid;?>" >
			<?php  if ($ePerms) { ?>
				<div class="kcgcl-col kcgcl-col-pos"><span>
				<a class="up move_control_up" > /\ </a>
				<a class="down move_control_down" > \/ </a>
				</span></div>
			<?php  } ?>
		
		
			<div class="kcgcl-col kcgcl-col-name"><span>
			<?php if ($ePerms) { ?>
				<a class="link edit_control" >
				<?php echo htmlEscape($this->GetName());?>
				</a></span>
			<?php  } 
			else {
				echo htmlEscape($this->GetName());
			}
			?>
			</div>
			<div class="kcgcl-col kcgcl-col-type"><span><?php echo gettext($this->GetType())?></span></div>
			<div class="kcgcl-col kcgcl-col-adv"><span>
			<?php if ($this->IsRequiredValid()) {?>
				<input type="checkbox" class="required" id="<?php echo "required".$this->cid;?>" name="required" <?php if ($this->GetRequired()) { echo 'checked="checked"'; } ?> />
			<?php } else { ?>
				<?php echo gettext('N/A');?>
			<?php } ?>
			</span></div>
			<div class="kcgcl-col kcgcl-col-adv"><span>
			<?php if ($this->IsSearchableValid()) {?>
				<input type="checkbox"  class="searchable" id="<?php echo "searchable".$this->cid;?>" name="searchable" <?php if ($this->GetSearchable()) { echo 'checked="checked"'; } ?> />
			<?php } else { ?>
				<?php echo gettext('N/A');?>
			<?php } ?>
			</span></div>
			<div class="kcgcl-col kcgcl-col-adv"><span>
			<?php if ($this->IsAdvSearchableValid()) {?>
				<input type="checkbox" class="advsearchable" id="<?php echo "advSearchable".$this->cid;?>" name="advSearchable" <?php if ($this->GetAdvSearchable()) { echo 'checked="checked"'; } ?> />
			<?php } else { ?>
				<?php echo gettext('N/A');?>
			<?php } ?>
			</span></div>
			<div class="kcgcl-col kcgcl-col-adv"><span>
			<?php if ($this->IsShowInResultsValid()) {?>
				<input type="checkbox" class="showinresults" id="<?php echo "showinresults".$this->cid;?>" name="showinresults" <?php if ($this->GetShowInResults()) { echo 'checked="checked"'; } ?> />
			<?php } else { ?>
				<?php echo gettext('N/A');?>
			<?php } ?>
			</span></div>
			<div class="kcgcl-col kcgcl-col-adv"><span>
			<?php if ($this->IsShowInPublicEntryValid()) {?>
				<input type="checkbox" class=publicentry id="<?php echo "publicEntry".$this->cid;?>" name="publicEntry" <?php if ($this->GetShowInPublicResults()) { echo 'checked="checked"'; } ?> />
			<?php } else { ?>
				<?php echo gettext('N/A');?>
			<?php } ?>
			</span></div>
			<div class="kcgcl-col kcgcl-col-desc"><span><?php echo htmlEscape($this->GetDesc())?></span></div>
			<?php  if ($ePerms) { ?>
				<div class="kcgcl-col kcgcl-col-del" title="Delete control"><span><a class="link delete_control"><img src="<?php echo baseURI; ?>images/icon-trash.png" alt='delete_control' /></a></span></div>
			<?php } ?>
		</div>
	<?php 
	
	}
	
	/**
	  * Prints out the general control options for a control
	  *
	  * @return void
	  */
	protected function PrintControlOptions()
	{
		?>
		<div class="kora_control kora_control_opts" pid="<?php echo $this->pid; ?>" cid="<?php echo $this->cid; ?>">
		<table class="table kcopts_style">
		<tr><td width="70%" class="kcopt_label"><strong><?php echo gettext('Type');?>:</strong></td><td><?php echo $this->GetType(); ?></td></tr>
		<tr><td width="70%" class="kcopt_label"><strong><?php echo gettext('Name');?>:</strong><br /><?php echo '('.gettext('Updating this can break front-end programming that has been completed.').')'; ?></td><td><input type="text" name="name"  class="ctrlopt_name" value="<?php echo htmlEscape($this->GetName());?>"/> <input type="button" class="ctrlopt_setname" value="<?php echo gettext('Update');?>" /></td></tr>
		<tr><td width="70%" class="kcopt_label"><strong><?php echo gettext('Description');?>:</strong></td><td><textarea name="description"  class="ctrlopt_desc" cols="20" rows="3"><?php echo htmlspecialchars($this->GetDesc());?></textarea></td></tr>
		<?php if($this->isRequiredValid) {?>
			<tr><td width="70%" class="kcopt_label"><strong><?php echo gettext('Required');?>:</strong> <?php echo gettext('Is it required to fill in this control before record can be ingested').'?';?></td><td><input type="checkbox" name="required"  class="required" <?php if($this->GetRequired()) echo 'checked="yes"';?> /></td></tr>
		<?php }?>
		<?php if($this->isSearchableValid) {?>
			<tr><td width="70%" class="kcopt_label"><strong><?php echo gettext('Searchable');?>:</strong> <?php echo gettext('Will this control be searched when doing basic searches in Kora').'?';?></td><td><input type="checkbox" name="searchable"  class="searchable" <?php if($this->GetSearchable()) echo 'checked="yes"';?> /></td></tr>
		<?php }?>
		<?php if($this->isAdvSearchableValid) {?>
			<tr><td width="70%" class="kcopt_label"><strong><?php echo gettext('Advanced Searchable');?>:</strong> <?php echo gettext('Will advanced search show an option to search this control').'?';?></td><td><input type="checkbox" name="advSearchable"  class="advsearchable" <?php if($this->GetAdvSearchable()) echo 'checked="yes"';?> /></td></tr>
		<?php }?>
		<?php if($this->isShowInResultsValid) {?>
			<tr><td width="70%" class="kcopt_label"><strong><?php echo gettext('Show in results');?>:</strong> <?php echo gettext('Will this control show up in the summary of search results').'?';?></td><td><input type="checkbox" name="showinresults"  class="showinresults" <?php if($this->GetShowInResults()) echo 'checked="yes"';?> /></td></tr>
		<?php }?>
		<?php if($this->isShowInPublicResultsValid) {?>
			<tr><td width="70%" class="kcopt_label"><strong><?php echo gettext('Public Ingest');?>:</strong> <?php echo gettext('Will this control show up on the public ingestion form').'?';?></td><td><input type="checkbox" name="publicEntry"  class="publicentry" <?php if($this->GetShowInPublicResults()) echo 'checked="yes"';?> <?php if($this->GetRequired()) echo 'disabled="disabled"';?> /><?php if($this->GetRequired()) echo '&nbsp;(Required)'; ?></td></tr>
		<?php }?>
		</table>
		</div>
		<?php
	}
	
	protected $pid;    // project id
	public    $cid;    // control id
	protected $rid;    // record id
	protected $sid;    // scheme id
	protected $preset;
	protected $description;
	protected $required;
	protected $options;
	protected $cName;
	protected $XMLInputValue;
	protected $XMLAttributes;
	protected $inPublicTable;
	protected $cgroup = null; // control group
	protected $value; // holder for the actual data
	protected $name;
	protected $existingData;
	protected $searchable;
	protected $advSearchable;
	protected $showInResults;
	protected $showInPublicResults;
	protected $publicEntry;
	protected $sequence;
	// these are set to false for controls where common options aren't valid
	protected $isRequiredValid;
	protected $isSearchableValid;
	protected $isAdvSearchableValid;
	protected $isShowInResultsValid;
	protected $isShowInPublicResultsValid;
	protected $isPublicEntryValid;
	protected $isSortValid;
	// does this control contain a file in the files dir? (so yes for file,image,etc)
	protected $hasFileStored;
	
	/////////////////////////////////
	///These functions handle presets
	/////////////////////////////////
	
	/**
	  * Update the name of a control preset
	  *
	  * @param int $presetID ID of the preset
	  * @param string $newName New name for the preset
	  *
	  * @return result string on error
	  */
	public static function updateControlPresetName($presetID, $newName)
	{
		global $db;
		
		if (Control::GetPermsForPreset($presetID) & PROJECT_ADMIN)
	    {
	    	$nameQuery = $db->query('SELECT name FROM controlPreset WHERE name='.escape($newName).' AND class IN (SELECT class FROM controlPreset WHERE presetid='.escape($presetID).') LIMIT 1');
	        if ($nameQuery->num_rows > 0)
	        {
	        	echo gettext('There is already a control of that type with that name').'.';    	
	        }
	        else
	        {
	        	$db->query('UPDATE controlPreset SET name='.escape($newName).' WHERE presetid='.escape($presetID).' LIMIT 1');
	        }
	    }
	}
	
	/**
	  * Update the global status of a control preset
	  *
	  * @param int $presetID ID of the preset
	  * @param int $global Binary boolean of whether the preset is global
	  *
	  * @return void
	  */
	public static function updateControlPresetGlobal($presetID, $global)
	{
		global $db;
		
		// Make sure the user has admin rights to the project and that the new value for global
		// is valid
		if (Control::GetPermsForPreset($presetID) & PROJECT_ADMIN && in_array($global, array(0, 1)))
		{
			$db->query("UPDATE controlPreset SET global=$global WHERE presetid=".escape($presetID).' LIMIT 1');
		}
		
	}
	
	/**
	  * Deletes a control preset from Kora
	  *
	  * @param string $presetID 
	  *
	  * @return void
	  */
	public static function deleteControlPreset($presetID)
	{
		global $db;
		
	    if (Control::GetPermsForPreset($presetID) & PROJECT_ADMIN)
	    {
	    	$db->query('DELETE FROM controlPreset WHERE presetid='.escape($presetID).' LIMIT 1');	
	    }

	}
	
	/**
	  * Prints the html for managing control presets
	  *
	  * @return void
	  */
	public static function showControlPresetDialog()
	{
		
		echo '<h2>'.gettext('Manage Control Presets').'</h2>';
		
		global $db;
	
		// call isSystemAdmin once and store it so we don't query the database
		// a billion times inside the loop
		$isSysAdmin = Manager::IsSystemAdmin();
		
		// Get the list of presets which this user has control over.
		if ($isSysAdmin)
		{
			$presetQuery = 'SELECT controlPreset.presetid AS id, controlPreset.name AS name, control.name AS class, controlPreset.global AS global, project.name AS project, '.PROJECT_ADMIN.' AS permissions FROM controlPreset LEFT JOIN project ON (controlPreset.project = project.pid) LEFT JOIN control ON (controlPreset.class = control.class) ORDER BY class, project, name';
		}
		else
		{
		   $projectQuery = 'SELECT member.pid FROM member RIGHT JOIN permGroup USING (gid) WHERE ( member.uid = '.Manager::GetUser()->GetUID().' AND (permGroup.permissions & '.PROJECT_ADMIN.') > 0)';
		   $presetQuery = 'SELECT
		                       controlPreset.presetid AS id,
		                       controlPreset.name AS name,
		                       control.name AS class,
		                       controlPreset.global AS global,
		                       project.name AS project,
		                       permGroup.permissions AS permissions
		                   FROM controlPreset
		                       LEFT JOIN project ON (controlPreset.project = project.pid)
		                       LEFT JOIN control ON (controlPreset.class = control.class)
		                       LEFT JOIN member ON (member.uid='.Manager::GetUser()->GetUID().' AND member.pid = controlPreset.project)
		                       LEFT JOIN permGroup ON (permGroup.gid = member.gid)
		                   WHERE controlPreset.project IN ('.$projectQuery.')
		                   ORDER BY class, project, name';
		}
	
		$presetQuery = $db->query($presetQuery);
		if ($presetQuery->num_rows == 0)
		{
			echo gettext('There are no presets which you have control over').'.';
		}
		else
		{
			echo '<table class="table" id="PresetControlTable">';
			echo '<tr><td><b>'.gettext('Type').'</b></td><td><b>'.gettext('Preset Name').'</b></td><td><b>'.gettext('Original Project').'</b></td><td><b>'.gettext('Global').'</b></td><td><b>'.gettext('New Name').'</b></td><td><b>'.gettext('Delete').'</b></td></tr>';
			while($preset = $presetQuery->fetch_assoc())
			{
	            echo '<tr>';
	            echo '<td>'.$preset['class'].'</td>';
	            echo '<td>'.htmlEscape($preset['name']).'</td>';
	            echo '<td>'.(empty($preset['project']) ? gettext('Stock Preset') : $preset['project']).'</td>';
	            // If the user is a project admin, we give them a checkbox to make a control
	            // global or not.  If not, we just display "yes" or "no"
	          	echo '<td><input type="checkbox" name="global'.$preset['id'].'" id='.$preset['id'].' ';
	          	if ($preset['global']) echo ' checked="checked" ';
	           	echo 'class="preset_control_global" /></td>';
	            echo '<td><input type="text" name="newName'.$preset['id'].'" id="newName'.$preset['id'].'" /><input type="button" id='.$preset['id'].' value="'.gettext('Rename').'" class="preset_control_rename" /></td>';
	            echo '<td><a class="preset_control_delete" id='.$preset['id'].'>X</a></td>';
	            echo '</tr>';
			}
			echo '</table>';
		}
		
		echo "<div id='ajax'></div>";
	}
	
}

?>
