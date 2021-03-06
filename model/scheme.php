<?php
namespace KORA;

use KORA\Manager;
use KORA\ControlCollection;
use KORA\Importer;
use KORA\Project;
use KORA\Record;
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

// ADD JAVASCRIPT(S) FOR THIS CLASS IN THE GLOBAL CONTEXT LIKE SO..
Manager::AddJS('javascripts/scheme.js', Manager::JS_CLASS); 
Manager::AddJS('javascripts/colorbox/jquery.colorbox-min.js', Manager::JS_CLASS); 
Manager::AddCSS('javascripts/colorbox/colorbox.css', Manager::CSS_CLASS); 
 
/**
 * @class Scheme object
 *
 * This class respresents a Project Scheme in KORA
 */
class Scheme
{
	protected $pid = 0;
	protected $sid = 0;
	protected $name = null;
	protected $desc = null;
	protected $legal = null;
	protected $allowPreset = false;
	protected $dcFields = null;
	protected $dcOutOfDate = false;
	protected $nextid = 0;
	protected $sequence = 0;
	protected $crossProject = null;
	protected $controlcollections = null;
	protected $publicingestion = false;
	protected $projobj = null;
    
	/**
	  * Constructor for the Scheme model
	  *
	  * @param int $pid Project ID the scheme belongs to
	  * @param int $sid Scheme ID of the scheme
	  *
	  * @return void
	  */
	function __construct($pid_, $sid_)
	{
		global $db;
		
		$results = $db->query("SELECT schemeid,pid,schemeName,description,allowPreset,dublinCoreFields,dublinCoreOutOfDate,nextid,sequence,crossProjectAllowed,legal,publicIngestion FROM scheme WHERE schemeid=".escape($sid_)." LIMIT 1");
		if ($results->num_rows == 0) { 
			try{ 
				throw new Exception(gettext('Invalid scheme requested, no scheme found with sid ['.escape($sid_).']'));
			}catch(Exception $e){
				return $e->GetMessage();
			}
		}
		$results = $results->fetch_assoc();
		// JUST SANITY CHECK HERE
		if ($results['pid'] != $pid_) { 
			try{ 
				throw new Exception(gettext('Requested pid did not match pid found in database for scheme, please inspect.')); 
			}catch(Exception $e){
				return $e->GetMessage();
			}
		}
		
		// LOOK-UP OK, SO START SETTING VALUES
		$this->pid = $results['pid'];
		$this->sid = $results['schemeid'];
		$this->name = $results['schemeName'];
		$this->desc = $results['description'];
		$this->legal = $results['legal'];
		$this->allowPreset = $results['allowPreset'];
		$this->dcFields = $results['dublinCoreFields'];
		$this->dcOutOfDate = $results['dublinCoreOutOfDate'];
		$this->nextid = $results['nextid'];
		$this->sequence = $results['sequence'];
		$this->crossProject = $results['crossProjectAllowed'];
		$this->publicingestion = $results['publicIngestion'];
				
	}
	
	public function GetPID() { return $this->pid; }
	public function GetSID() { return $this->sid; }
	public function GetName() { return $this->name; }
	public function GetDesc() { return $this->desc; }
	public function GetLegal() { return $this->legal; }
	public function GetDCFields() { return $this->dcFields; }
	public function GetNextID() { return $this->nextid; }
	public function GetSequence() { return $this->sequence; }
	public function GetCrossProject() { return $this->crossProject; }
	
	public function IsPresetAllowed() { return $this->allowPreset; }
	public function IsDCOutOfDate() { return $this->dcOutOfDate; }
	public function IsPublicIngestAllowed() { return $this->publicingestion; }
	
	/**
	  * Gets the project the scheme belongs to
	  *
	  * @return project as an object
	  */
	public function GetProject()
	{
		if ($this->projobj) { return $this->projobj; }
		
		$this->projobj = new Project($this->GetPID());
		
		return $this->projobj;
	}
	
	/**
	  * Gets a list of all the controls belonging to the scheme
	  *
	  * @return list of control objects
	  */
	public function GetControls()
	{
		// LAZY-LOAD
		if ($this->controlcollections) { return $this->controlcollections; }
	
		global $db;
		$controls = array();
		// INIT THIS ARRAY WITH THE '0'/INTERNAL COLLID DEFINED
		$this->controlcollections = [ 0 => ['Internal', 'Kora Record Data', [] ] ];
		
		// get a list of the collections in the scheme
		$collectionQuery = $db->query('SELECT collid, name, description, sequence FROM collection WHERE schemeid='.escape($this->sid).' ORDER BY sequence');
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
		
		$controlQuery = $db->query($controlQuery);
		
		$controlList = array();
		while ($ctrl = $controlQuery->fetch_assoc()) {
			$cobj = Manager::GetControl($this->pid, $ctrl['cid']);
			$this->controlcollections[$ctrl['collid']]['controls'][] = $cobj;
			//$this->controls[] = $cobj;
		}
		
		return $this->controlcollections;
	}

	/**
	  * Get a specific control belonging to a scheme
	  *
	  * @param int $cid Control ID of the control you want
	  *
	  * @return control as an object
	  */
	public function GetControl($cid_)
	{
		if (!is_numeric($cid_)) { return false; }
		foreach ($this->GetControls() as $ctrlcoll) {
			foreach($ctrlcoll['controls'] as $ctrl)
			{ if ($ctrl->cid == $cid_) { return $ctrl; } }
		}
		// if we've made it here, we haven't found a control matching requested cid passed in, return false
		return false;
	}
	
	/**
	  * Prints out the html layout of the scheme and all its controls
	  *
	  * @return void
	  */
	public function PrintSchemeLayout()
	{
		global $db;
		
		if (!Manager::IsLoggedIn()) { return false; }
		
		// Show the "Available for Preset" checkbox
		if (Manager::IsProjectAdmin())
		{
			echo '<br /><input type="checkbox" id="schemePreset" name="schemePreset" ';
			if ($this->IsPresetAllowed()) { echo ' checked '; }
			echo 'class="ks_btn_schemePreset" /> '.gettext('Allow this scheme\'s layout to be used as a preset?').'<br />';
		}
		
		$ePerms = Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT);
		$firstMoveCtrlHidden = false;
		
		// get a list of the collections in the project
 		foreach ($this->GetControls() as $collid => $coll)
 		{
 			if ($collid == 0) { continue; }
 			
 			$objcoll = new ControlCollection($this->pid, $this->sid, $collid);
			// TODO: Have someone look at this stupid nested-table layout and clean it?
			?>
			<br />
			
			<?php  if($ePerms && $firstMoveCtrlHidden) { ?>
				<div class="kcg-move-collection move_collection clearfix" title="Re-order control groups">&nbsp;
				<a class="move_collection_down kcg-move-collection-down" >&#x21c5;</a>
				</div>
			<?php } elseif (!$firstMoveCtrlHidden) {
				$firstMoveCtrlHidden = true;
			} ?>

			<div class="scheme_collection kora-control-group clearfix" kcollid="<?php echo $collid?>">
			<div class="kcg-collection-container clearfix">
			<div class="clearfix" >
			<div class="kcg-collection-properties">
			<div class="kcg-collection-name">
			<?php if ($ePerms) {?>
				<a class="link update_collection"><?php echo htmlEscape($objcoll->GetName())?></a>
			<?php } else{
				echo htmlEscape($objcoll->GetName());
			}?>	
			</div>
			<div class="kcg-collection-desc"><?php echo htmlEscape($objcoll->GetDesc())?></div>
			</div>
			<?php if($collid>0&&$ePerms){?>
				<div class="kcg-collection-settings-button" title="Show/Hide control options"><span><a class="link showhide_permissions" ><img src="<?php echo baseURI; ?>images/icon-preferences.png" alt='show_settings' /></a></span></div>
			<?php } ?>	
			</div>
			<?php if (sizeof($coll['controls']) == 0)
			{
				echo '<div class="kcgcl-col kcgcl-col-nocontrols"><span>';
				echo gettext('There are currently no controls in this control group');
				echo '</span></div>';
			}
			else
			{ ?>				
				<div class="kora-control-group-control-list">
				<div class="kcgcl-row kcgcl-header clearfix">
				<?php if ($ePerms) { ?><div class="kcgcl-col kcgcl-col-pos"><span>&nbsp;</span></div> <?php  } ?>
				<div class="kcgcl-col kcgcl-col-name"><span><?php echo gettext('Name');?></span></div>
				<div class="kcgcl-col kcgcl-col-type"><span><?php echo gettext('Type');?></span></div>
				<div class="kcgcl-col kcgcl-col-adv"><span><?php echo gettext('Required');?></span></div>
				<div class="kcgcl-col kcgcl-col-adv"><span><?php echo gettext('Search');?></span></div>
				<div class="kcgcl-col kcgcl-col-adv"><span><?php echo gettext('Adv.').'<br/>'.gettext('Search');?></span></div>
				<div class="kcgcl-col kcgcl-col-adv"><span><?php echo gettext('Show');?></span></div>
				<div class="kcgcl-col kcgcl-col-adv"><span><?php echo gettext('Public').'<br/>'.gettext('Ingest')?></span></div>
				<div class="kcgcl-col kcgcl-col-desc"><span><?php echo gettext('Description');?></span></div>
				<?php if ($ePerms) { ?>
					<div class="kcgcl-col kcgcl-col-del"></div>
				<?php  } ?>
				</div>
				
				<?php foreach ($coll['controls'] as $ctrl)
				{
					$ctrl->PrintControlRow($ePerms);
				} ?>
				</div>
			<?php } ?>
			<?php  if($ePerms){ ?>
				<div class="kcgcl-row kcg-actions clearfix">
				<div class="kgc-action-add" title="Add control to this control group"><a class="link add_control">&#43;</div>
				<div class="kgc-action-delete" title="Delete this control group"><a class="link delete_collection" >&#215;</a></div>
				</div>

				<!-- <tr>
				<td><a class="link add_control"><?php echo gettext('Add a Control');?></a> -
				<a class="link delete_collection" ><?php echo gettext('Remove this Control Collection');?></a>
				</td>
				</tr> -->
			<?php  } ?>
			</div>
			</div>
			<?php
 		}
 		if(Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT, $this->GetPID())){
			echo '<a class="link add_collection">'.gettext('Add a Control Collection').'</a>';
 		}
	}
	
	/**
	  * Prints html form for adding a new control to a scheme collection
	  *
	  * @param int $collid_ ID of the collection the control will belong to
	  *
	  * @return void
	  */
	public function PrintAddControl($collid_)
	{
		?>
		<h2><?php echo gettext('Add a Control');?></h2>
		<div id='cbox_error'></div>
		<div id="scheme_add_control">
		<table class="table_noborder">
	        <tr><td align="right"><?php echo gettext('Control to Add');?>:</td><td><select class="ks_addControl_type">
	        <?php
	        $controlList = getControlList();
	        $names = array();
	        $classes = array();
	        //This section assures that values are unique. Prevents control type duplication
	        foreach($controlList as $c){
	        	array_push($names,$c['name']);
	        	array_push($classes,$c['class']);
	        }
	        $names = array_unique($names);
	        $classes = array_unique($classes);
	        foreach(array_combine($names, $classes) as $name => $class) {
	        	echo '<option value="'.$class.'">'.gettext($name).'</option>';
	        }
	        ?>
	        </select></td></tr>
	        <tr><td align="right"><?php echo gettext('Name');?>:</td><td><input type="text" class="ks_addControl_name" /></td></tr>
	        <tr><td align="right"><?php echo gettext('Description');?>:</td><td align="right"><textarea class="ks_addControl_desc" cols="20" rows="3"></textarea></td></tr>
	        <tr><td align="right"><?php echo gettext('Required');?>?</td><td><input type="checkbox" class="ks_addControl_req"/></td></tr>
	        <tr><td align="right"><?php echo gettext('Searchable');?>?</td><td><input type="checkbox" class="ks_addControl_search"/></td></tr>
	        <tr><td align="right"><?php echo gettext('Advanced Search');?>?</td><td><input type="checkbox" class="ks_addControl_adv"/></td></tr>
	        <tr><td align="right"><?php echo gettext('Show in results');?>?</td><td><input type="checkbox" class="ks_addControl_showRes"/></td></tr>
	        <?php  /* THERE ARE COMLICATIONS WITH SHOWING THESE AT THE POINT OF CREATING A NEW CONTROL, SO COMMENTED OUT FOR NOW
		        <tr><td align="right">Show in public results?</td><td><input type="checkbox" name="showinpublicresults" /></td></tr>
		        <tr><td align="right">Public entry?</td><td><input type="checkbox" name="publicentry" /></td></tr>
	        */ ?>
	        <tr><td colspan="2"><input type="button" class="scheme_addControl_submit" value="<?php echo gettext('Add Control');?>" /></td></tr>
	        </table>
	        <input type="hidden" class="ks_addControl_collid" value=<?php echo $collid_;?> />
	        </div>
	        <?php
	}

	/**
	  * Print the html form for adding a collection to a scheme
	  *
	  * @return void
	  */
	public function PrintAddCollection()
	{
		echo '<h2>'.gettext('Add New Collection to ').$this->GetName().'</h2>'; ?>
		<div id='cbox_error'></div>
		<div id="scheme_add_collection">
		<table class="table_noborder">
		<tr><td><?php echo gettext('Name');?>:</td><td><input type="text" class="scheme_addColl_name" <?php  if(isset($_REQUEST['collName'])) echo ' value="'.htmlEscape($_REQUEST['collName']).'" ';?> /></td></tr>
		<tr><td><?php echo gettext('Description');?>:</td><td><textarea class="scheme_addColl_desc"><?php  if(isset($_REQUEST['description'])) echo htmlEscape($_REQUEST['description']);?></textarea></td></tr>
		<tr><td colspan="2"><input type="button" class="scheme_addColl_submit" value="<?php echo gettext('Create New Collection');?>" /></td></tr>
		</table>        
		</div>    
		<?php 
	}
	
	/**
	  * Prints the html form to update an existing collection
	  *
	  * @param string $collid ID of the collection to be editted
	  *
	  * @return void
	  */
	public function PrintUpdateCollection($collid)
	{
		$coll = new ControlCollection($this->GetPID(), $this->GetSID(), $collid);
		echo '<h2>'.gettext('Edit Collection Properties for').' '.htmlEscape($coll->GetName()).'</h2>'; ?>
		<div id='cbox_error'></div>
		
		<div id="scheme_edit_collection">
		<input type="hidden" class="scheme_updateColl_id" value="<?php echo htmlEscape($coll->GetCollID())?>" />
		<table class="table_noborder">
		<tr><td align="right"><?php echo gettext('Name');?>:</td><td><input type="text" class="scheme_updateColl_name" value="<?php echo htmlEscape($coll->GetName())?>" /></td></tr>
		<tr><td align="right"><?php echo gettext('Description');?>:</td><td><textarea class="scheme_updateColl_desc" cols="40" rows="5"><?php echo htmlEscape($coll->GetDesc())?></textarea></td></tr>
		<tr><td colspan="2" align="right"><input type="button" class='scheme_updateColl_submit' value="<?php echo gettext('Submit Changes');?>" /></td></tr>
		</table>
		</div>
		<?php 
	}
	
	/**
	  * Print out the html form for adding other schemes to associated to this scheme
	  *
	  * @return void
	  */
	public function PrintAssocSetAllowedSchemes()
	{
		$availassoc = Project::GetAllSchemes();
		$availprojs = [];
		$availschemes = [];
		
		foreach ($availassoc as $onescheme)
		{
			if ($onescheme->GetProject()->GetUserPermissions(Manager::GetUser()->GetUID()) > 0)
			{
				if (!key_exists($onescheme->GetPID(), $availprojs))
				{
					$selected = ($onescheme->GetPID() == Manager::GetProject()->GetPID()) ? 'selected="selected"' : '';
					$availprojs[$onescheme->GetPID()] = "<option value=\"{$onescheme->GetPID()}\" $selected>{$onescheme->GetProject()->GetName()}</option>";
				}
				
				$availschemes[$onescheme->GetSID()] = '<option class="add_allowed_assoc_scheme_option proj'.$onescheme->GetPID().'" pid="'.$onescheme->GetPID().'" value="'.$onescheme->GetSID().'">'.$onescheme->GetName()."</option>\n";
			}
			//else { NOTHING TO DO }
		}
		print '<select class="add_allowed_assoc_proj" name="projectid"><option value="all">'.gettext("All Projects").'</option>';
		foreach ($availprojs as $aproj) { print $aproj; }
		print '</select>';
		
		print '<select class="add_allowed_assoc_scheme" name="schemeid"><option>'.gettext("(select)").'</option><option class="add_allowed_assoc_scheme_option showall" value="all">'.gettext('Add All Schemes').'</option>';
		foreach ($availschemes as $ascheme) { print $ascheme; }
		print '</select>';
		
		print '&nbsp;<a class="link add_allowed_assoc">'.gettext('Add').'</a>';
	}

	/**
	  * Print out the list of all the schemes currently associated with this scheme
	  *
	  * @return void
	  */
	public function PrintAssocAllowedSchemes()
	{
		global $db;
		
		$query = 'SELECT crossProjectAllowed FROM scheme WHERE crossProjectAllowed IS NOT NULL AND schemeid='.$this->GetSID();
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
			
			$s = new Scheme((string)$entry->project, (string)$entry->scheme);
			if($s->GetPID()!=0){
				$schemes[] = $s;
			}
		}
		
		if (empty($xml->to) || empty($xml->to->entry)){
			echo '<br /> '.gettext('No schemes are currently allowed to associate to this project').'. <br/>';
			return;
		}
		
		echo gettext('The following schemes are currently allowed to associate to records in this scheme').':';
		?>
		<br /><br />
		<table class="table">
		<tr><td>
		<strong><?php echo gettext('Project');?></strong>
		</td><td>
		<strong><?php echo gettext('Scheme');?></strong>
		</td><td>
		<strong><?php echo gettext('Remove');?></strong>
		</td></tr>
		<?php
		foreach($schemes as $scheme){ ?>
			<tr class='scheme_allowed_assoc' pid='<?php echo $scheme->GetPID(); ?>' sid='<?php echo $scheme->GetSID(); ?>' ><td>
			<?php echo htmlEscape($scheme->GetProject()->GetName());?>
			</td><td>
			<?php echo htmlEscape($scheme->GetName());?>
			</td><td>
			<a class="link delete_allowed_assoc">X</a>
			</td></tr>
			<?php
		}
		?>
		</table>
		<?php
	}
	
	/**
	  * Prints the numerated navigation links for a set of search results
	  *
	  * @param int $numPages Number of pages of search results
	  * @param int $currPage Current page number being shown
	  *
	  * @return void
	  */
	public function PrintNavigationLinks($numPages, $currPage)
	{
		echo Manager::GetBreadCrumbsHTML($numPages, $currPage, ADJACENT_PAGES_SHOWN, 'onclick="setPage(%d);"');
	}
	
	/**
	  * Print out links for actions to take on a record in a search result
	  * -KID- View the record
	  * -edit- Edit the record
	  * -delete- Delete the record
	  *
	  * @return void
	  */
	public function PrintRecordActions()
	{
		if (!Manager::GetUser()) { return false; }
		print "<a class='link ks_result_view' href='viewObject.php?rid=%s' >%s</a>";
		if (Manager::GetUser()->HasProjectPermissions(INGEST_RECORD, $this->GetPID()))
		{ print " <a href='editObject.php?pid={$this->GetPID()}&sid={$this->GetSID()}&rid=%s' class='link ks_result_edit'>edit</a>"; }
		if (Manager::GetUser()->HasProjectPermissions(DELETE_RECORD, $this->GetPID()))
		{ print "&nbsp;|&nbsp;<a href='deleteObject.php?pid={$this->GetPID()}&sid={$this->GetSID()}&rid=%s' class='link ks_result_delete'>delete</a>"; }
	}
	
	/**
	  * Prints out the html form to perform an XML upload of a scheme layout
	  *
	  * @return void
	  */
	public static function PrintXmlUploadForm() {
		?>
			<div id="xmlUploadForm">
				<p>
			    <label for="xmlFileName"><?php echo gettext('XML File to Load: ');?></label><input class="scheme_uploadXML_file" type="file" /><br/>
			    </p>
			    <p><input type="button" class="scheme_uploadXML_submit" value="<?php echo gettext('Create Scheme From File');?>"/></p>
			</div>
		<?php 
	}
	
	/**
	  * Prints out the form for importing records via XML
	  *
	  * @return void
	  */
	public static function PrintImportRecordsForm(){
		// remove any previous control mappings.
		unset($_SESSION['controlMapping_'.$_REQUEST['sid']]);
		?>
			<div id="xmlUploadForm">
				<p><?php echo gettext('Recommended upload maximum of 5000 records');?></p><br>
				<p>
			    <label for="xmlFileName"><?php echo gettext('XML File to Load: ');?></label><input id="xmlFileName" name="xmlFileName" type="file" /><br/>
			    <label for="zipFolder"><?php echo gettext('Zip Folder: ');?></label><input id='zipFolder' name="zipFolder" type="file" />
			    </p>
			    <p><input type="button" class="records_uploadXML_submit" value="<?php echo gettext('Upload Records');?>"/></p>
			</div>
		<?php
	}
	
	/**
	  * Print scheme quick jump form for Bread Crumbs
	  *
	  * @return void
	  */
	public function PrintQuickJump() { 
		if (count($this->GetProject()->GetSchemeSIDs()) > 1) {
			//multiple schemes = drop down menu
			?>
			<select class='ksquickjump' size="1" pid="<?php print $this->GetPID(); ?>">
			<?php
			foreach($this->GetProject()->GetSchemeSIDs() as $asid) {
				$ascheme = new Scheme($this->GetPID(), $asid);
				$selected = ($this->GetSID() == $asid) ? " selected='selected' " : '';  
				echo '<option value="'.$ascheme->GetSID().'"'.$selected.'>'.htmlEscape($ascheme->GetName()).'</option>';
			}
			?>
			</select>
			<?php
		} else {
			//single scheme = link
			$sids = $this->GetProject()->GetSchemeSIDs();
			$ascheme = new Scheme($this->GetPID(), $sids[0]);
			echo '<a href="'.baseURI.'schemeLayout.php?pid='.$this->GetPID().'&sid='.$this->GetSID().'">'.htmlEscape($ascheme->GetName()).'</a>';
		}
	}
	
	/**
	  * Exports the layout of the scheme to an XML file
	  *
	  * @param string $schemesimplexml The XML object that will be printed on the XML file
	  *
	  * @return the XML object
	  */
	public function ExportSchemeToXML(&$schemesimplexml)
	{
		//********************Export scheme structure****************************
		
		$colls = $this->GetControls();
		// IF SIZEOF == 1 THEN THE ONLY CONTROL COLLECTION SHOULD BE CONTROL GROUP 0, MEANING NO DATA YET ADDED BY USER
		if (sizeof($colls) <= 1)
		{
			Manager::PrintErrDiv(gettext('There is no structure yet defined for this scheme'));
			return false;
		}
		
		$ctrls = [];
		foreach ($colls as $coll)
		{ $ctrls = array_merge($ctrls, $coll['controls']); }
	
		//Export scheme data
		$node = $schemesimplexml->addChild('SchemeDesc');
		$node->addChild('Name', $this->GetName());
		$node->addChild('Description', xmlEscape($this->GetDesc()));
		$node->addChild('NextId', $this->GetNextID());
		
		//Export collection info
		$schemesimplexml->addChild('Collections');
		foreach (array_keys($colls) as $collid)
		{
			// DON'T EXPORT COLLID 0
			if ($collid == 0) { continue; }
			$objcoll = new ControlCollection($this->pid, $this->sid, $collid);
			$node = $schemesimplexml->Collections->addChild('Collection');
			$node->addChild('id',$objcoll->GetCollID());
			$node->addChild('Name',xmlEscape($objcoll->GetName()));
			$node->addChild('Description',xmlEscape($objcoll->GetDesc()));
			$node->addChild('Sequence',xmlEscape($objcoll->GetSequence()));
		}
		
		//Export the scheme controls
		$schemesimplexml->addChild('Controls');
		foreach($ctrls as $ctrl){
			// TODO:  BUG HERE, WE SHOULD REALLY PREFIX THE tag_name WITH SOMETHING BECAUSE
			//        CONTROLS WITH NAMES STARTING WITH NUMBERS OR OTHER SPECIAL CHARS ARE INVALID
			//        XML, BUT TO PROPERLY DO THIS WE REALLY NEED TO VERSION OUR XML EXPORTS SO 
			//        WE CAN THEN DIFFERENTIATE ON IMPORT WHEN TO STRIP THAT PREFIX BACK OFF
			//        TRY IT:  CREATE A CONTROL STARTING WITH A NUMBER, EXPORT IT, THEN TRY TO IMPORT
			$tag_name = $_cName = str_replace(" ","_",$ctrl->GetName());
			$tag_name = str_replace("/","_",$tag_name);
			$node = $schemesimplexml->Controls->addChild($tag_name);
			$node->addChild('CollId',$ctrl->GetGroup());
			$node->addChild('Type',$ctrl->GetClass());
			$node->addChild('Description',xmlEscape($ctrl->GetDesc()));
			$node->addChild('Required',$ctrl->GetRequired());
			$node->addChild('Searchable',$ctrl->GetSearchable());
			$node->addChild('advSearchable',$ctrl->GetAdvSearchable());
			$node->addChild('showInResults',$ctrl->GetShowInResults());
			$node->addChild('showInPublicResults',$ctrl->GetShowInPublicResults());
			$node->addChild('publicEntry',$ctrl->GetPublicEntry());
			$node->addChild('options',xmlEscape($ctrl->GetControlOptions()->asXML()));
			$node->addChild('sequence',$ctrl->GetSequence());
		}
		
		return $schemesimplexml;
	}

	/**
	  * Adds a foreign scheme to the list of schemes that are allowed to associate to this scheme
	  *
	  * @param int $pid Project ID the foreign scheme belongs to
	  * @param int $sid Scheme ID of the foreign scheme
	  *
	  * @return void
	  */
	public function AddAllowedAssociation($pid,$sid) {
		global $db;
		
		if (empty($pid) || empty($sid)) {
			Manager::PrintErrDiv(gettext('Invalid pid/sid combination passed in to AddAllowedAssociation'));
			return;
		}
		
		$sid = escape($sid,false);
		
		// we are granting permissions TO other schemes to search this scheme
		$query = 'SELECT crossProjectAllowed FROM scheme WHERE schemeid='.$this->GetSID().' LIMIT 1';
		$results = $db->query($query);
		$result = $results->fetch_assoc();
		
		if($result['crossProjectAllowed'] == '') {
			//there is nothing in the field, use this as defualt xml
			$result['crossProjectAllowed'] = '<crossProjectAllowed><from></from><to></to></crossProjectAllowed>';
		}
		$toScheme =simpleXML_load_string($result['crossProjectAllowed']);
		
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
		
		// we are not recording permissions granted FROM other schemes to this
		// scheme right now, so update the data in the db
		$db->query('UPDATE scheme SET crossProjectAllowed='.escape($toScheme->asXML()).' WHERE schemeid='.$this->GetSID());
		
		
		// get all schemes we want to grant permissions FROM this scheme
		// the current scheme may be included, so we do this after updating the db
		$fromSchemes = array();
		$query = 'SELECT schemeid,crossProjectAllowed FROM scheme WHERE schemeid='.$sid.' LIMIT 1';
		$results = $db->query($query);
		
		while($result = $results->fetch_assoc()) {
			if($result['crossProjectAllowed'] == '') {
				//there is nothing in the field, use this as defualt xml
				$result['crossProjectAllowed'] = '<crossProjectAllowed><from></from><to></to></crossProjectAllowed>';
			}
			$fromSchemes[$result['schemeid']] = simplexml_load_string($result['crossProjectAllowed']);
		}
		
		
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
		$node->addChild('project',xmlEscape($this->GetPID()));
		$node->addChild('scheme',xmlEscape($this->GetSID()));
		
		$querys = array();
		foreach($fromSchemes as $sid=>$fromScheme){
			$querys[] = 'UPDATE scheme SET crossProjectAllowed='.escape($fromScheme->asXML()).' WHERE schemeid='.escape($sid);
		}
		foreach($querys as $query){
			$db->query($query);
		}
	}
	
	/**
	  * Removes a foreign scheme from the list of allowed associated schemes
	  *
	  * @param int $pid Project ID the foreign scheme belongs to
	  * @param int $sid Scheme ID of the foreign scheme
	  *
	  * @return void
	  */
	public function DeleteAllowedAssociation($pid,$sid) {
		global $db;
		
		if (empty($pid) || empty($sid)) {
			Manager::PrintErrDiv(gettext('Invalid pid/sid combination passed in to DeleteAllowedAssociation'));
			return;
		}
		
		// the scheme we are removing permissions to
		$query = 'SELECT crossProjectAllowed FROM scheme WHERE schemeid='.$this->GetSID().' LIMIT 1';
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
		
		$db->query('UPDATE scheme SET crossProjectAllowed='.escape($toScheme->asXML()).' WHERE schemeid='.$this->GetSID());
		
		
		
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
	}

	// TODO: THIS FUNCTION COULD STAND TO BE RE-WRITTEN, POSSIBLY W/OUT THE $_REQUEST STUFF, BUT BE SURE
	// TO DEAL WITH HANDLEADDNEWSCHEME FUNCTION IN PROJECT.PHP CLASS AS IT TRIGGERS THIS ALSO USING $_REQUEST
	//Turn control creation into a function so the xml importer can share functionality
	/**
	  * Creates a control to be added to the scheme
	  *
	  * @param string $name Name of control
	  * @param string $type Control tyope
	  * @param string $schemeid Scheme ID the control is being added to
	  * @param string $collid ID of collection control will belong to
	  * @param string $description Description of the control
	  * @param int $required Is the control required in a record
	  * @param int $searchable Is the control searchable
	  * @param int $advanced Is the control searchable in advanced search
	  * @param int $showinresults Is the control viewable in search results
	  * @param int $showinpublic Is the control publically viewable
	  * @param int $publicentry Is the control ingestable in public ingestion
	  * @param string $options Options for the control
	  * @param int $sequence TODO WHAT DOES THIS DO?
	  *
	  * @return result string on error
	  */
	public function CreateControl($fromRequest,
		$name='',
		$type='',
		$schemeid='',
		$collid='',
		$description='',
		$required = 0,
		$searchable = 0,
		$advanced = 0,
		$showinresults = 0,
		$showinpublic = 0,
		$publicentry = 0,
		$options = '',
		$sequence = 0)
	{
		global $invalidControlNames;
		global $db;
		
		//For regular control creation, get arguments from Request array
		if($fromRequest){
			$name = $_REQUEST['name'];
			$type = $_REQUEST['type'];
			$collid = $_REQUEST['collectionid'];
			$description = $_REQUEST['description'];
			$required = (isset($_REQUEST['required']) && $_REQUEST['required'] == "true") ? 1 : 0;
			$searchable = (isset($_REQUEST['searchable']) && $_REQUEST['searchable'] == "true") ? 1 : 0;
			$advanced = (isset($_REQUEST['advanced']) && $_REQUEST['advanced'] == "true") ? 1 : 0;
			$showinresults = (isset($_REQUEST['showinresults']) && $_REQUEST['showinresults'] == "true") ? 1 : 0;
			$showinpublic = (isset($_REQUEST['showinpublicresults']) && $_REQUEST['showinpublicresults'] == "on") ? 1 : 0;
			$publicentry = (isset($_REQUEST['publicentry']) && $_REQUEST['publicentry'] == "on") ? 1 : 0;
	
			$schemeid = $this->GetSID();
		}
		
		// first make sure the control name isn't blank
		if ((empty($name) || empty($type) || empty($schemeid) || $collid === '') && $fromRequest) {
			Manager::PrintErrDiv(gettext('You must provide a name for the new control.'));
			return false;
		}
		else if(empty($name) ||	empty($type) ||	empty($schemeid) || $collid === ''){
			Manager::PrintErrDiv(gettext("Missing required field."));
			return false;
		}
		if (in_array(strtoupper($name), $invalidControlNames))
		{
			Manager::PrintErrDiv($name." ".gettext('is not a valid control name'));
			return false;
		}
		$xmlInvalids = array('&', '<', '>', '"', "'");
		if (count(array_intersect($xmlInvalids, str_split($name))) != 0) {
			Manager::PrintErrDiv(gettext('You should not use invalid<br> XML characters (&, <, >, ", '."'".') for the name.'));
			return false;
		}
		$nameQuery  = "SELECT cid FROM p".$this->pid."Control ";
		$nameQuery .= "WHERE schemeid=".$this->sid;
		$nameQuery .= " AND name='".trim($name)."'";
		$nameQuery .= " LIMIT 1";
		$nameQuery = $db->query($nameQuery);
		if ($nameQuery->num_rows != 0) {
			Manager::PrintErrDiv(gettext('That name is already used by another control in this scheme.'));
			return false;
		}
		// make sure the control type is valid; if it's not, return; if it is, get the filename
		// to include.
		$typeQuery = $db->query('SELECT file, class FROM control WHERE class='.escape($type).' LIMIT 1');
		if($typeQuery->num_rows == 0)
		{
			Manager::PrintErrDiv(gettext('Please select a valid control type.'));
			return false;
		}
		else
		{
			$controlData = $typeQuery->fetch_assoc();
			$emptyControl = new $controlData['class'];
		}
		
		// Build the Query to create the control
		$sqlquery = "INSERT INTO p".$this->GetPID()."Control (schemeid,collid,type,name,description,required,searchable,advSearchable,showInResults,showInPublicResults,publicEntry,options,sequence)";
		if ($fromRequest)$sqlquery .= " SELECT ";
		else $sqlquery .= " VALUES (";
		$sqlquery .= "$schemeid,$collid,".escape($type).",".escape(trim($name)).",".escape($description).",";
		$sqlquery .= "$required,$searchable,$advanced,$showinresults,$showinpublic,$publicentry,";
		if($fromRequest)$sqlquery .= escape($emptyControl->initialOptions()).", COUNT(sequence)+1 FROM p".$this->GetPID()."Control where collid=".escape($collid);
		else $sqlquery .= escape($options).", $sequence".")";
		$db->query($sqlquery);
		
		// THIS USED TO RE-DIRECT IF FROM REQUEST, ELSE RETURN TRUE/FALSE IN OTHER CASES, CHANGED NOW
		// ASSUMING WE WILL USE AJAX TO ADD NORMALLY, AND RETURN TRUE IF SUCCESSFUL OR FALSE IF DB->QUERY ERR
		
		if($db->errno != 0) { Manager::PrintErrDiv($db->error); return false; }
		
		return true;

	}

	/**
	  * Creates a collection in a scheme
	  *
	  * @return void
	  */
	public function CreateCollection()
	{
		global $db;

		if (empty($_REQUEST['collName'])) {
			Manager::PrintErrDiv(gettext('You must provide a name.'));
			return false;
		}
		else {
			$query = "INSERT INTO collection (schemeid, name, sequence, description) ";
			$query .= "SELECT ".escape($this->GetSID()).", ";
			$query .= escape($_REQUEST['collName']).", COUNT(sequence) + 1, ";
			$query .= escape($_REQUEST['description'])." FROM collection ";
			$query .= "WHERE schemeid=".escape($this->GetSID());
			$result = $db->query($query);
			
			if(!$result) { Manager::PrintErrDiv($db->error); }
		}
	}

	/**
	  * Updates information about a collection
	  *
	  * @param int $collid_ ID of the collection
	  * @param string $collname_ Name of the collection
	  * @param string $colldesc_ Description of the collection
	  *
	  * @return result string on error
	  */
	public function UpdateCollection($collid_, $collname_, $colldesc_)
	{
		global $db;

		if (empty($collname_)) {
			Manager::PrintErrDiv(gettext('You must provide a name.'));
			return false;
		}
		else {
			$result = $db->query("UPDATE collection SET name=".escape($collname_)." WHERE schemeid=".$this->GetSID()." AND collid=".escape($collid_));		
			if(!$result) { Manager::PrintErrDiv($db->error); }
			$result = $db->query("UPDATE collection SET description=".escape($colldesc_)." WHERE schemeid=".$this->GetSID()." AND collid=".escape($collid_));		
			if(!$result) { Manager::PrintErrDiv($db->error); }
		}
	}

	/**
	  * Modifies the order of controls within a collection
	  *
	  * @param int $cid Control ID
	  * @param string $direction Which direction to move the control in the list
	  *
	  * @return void
	  */
	public function MoveControl($cid, $direction)
	{
		global $db;
		
   		if (!Manager::IsLoggedIn()) { return false; }
   		
   		if(Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT, $this->GetPID()))
		{
			$cTable = 'p'.$this->GetPID().'Control';
			
			// MAKE SURE CONTROL IS IN CURRENT SCHEME
			$check = $db->query("SELECT $cTable.sequence AS conSeq, collection.sequence AS colSeq, $cTable.collid AS collid FROM $cTable LEFT JOIN collection USING (collid, schemeid) WHERE $cTable.cid=".escape($cid)." AND $cTable.schemeid=".escape($this->GetSID()).' LIMIT 1');
			if ($check->num_rows > 0)
			{
				$check = $check->fetch_assoc();
				$conSeq = $check['conSeq'];
				$colSeq = $check['colSeq'];
				$origCol = $check['collid'];
				
				if ($direction == 'up')
				{
					// if the control sequence is > 1 we don't need to move between
					// collections
					if ($conSeq > 1) {
						$db->query("UPDATE $cTable SET sequence='$conSeq' WHERE sequence='".($conSeq-1)."' AND collid=".escape($origCol));
						$db->query("UPDATE $cTable SET sequence='".($conSeq-1)."' WHERE cid=".escape($cid));
					}
					// if the collection sequence is = 1 it's at the top and we do nothing
					elseif ($colSeq > 1) {
						$check = $db->query('SELECT collid FROM collection WHERE schemeid='.escape($this->GetSID()).' AND sequence='.escape($colSeq-1));
						$check = $check->fetch_assoc();
						$newCol = $check['collid'];
						$check = $db->query("SELECT MAX(sequence)+1 AS sequence FROM $cTable WHERE collid=".escape($newCol));
						$check = $check->fetch_assoc();
						$newSeq = $check['sequence'];
						// this check is needed for moving into an empty group
						if ($newSeq < 1) $newSeq = 1;
						
						$db->query("UPDATE $cTable SET collid='$newCol', sequence='$newSeq' WHERE cid=".escape($cid));
						$db->query("UPDATE $cTable SET sequence=(sequence-1) WHERE collid='$origCol'");
					}
				}
				elseif ($direction == 'down')
				{
					// get the maximum control and collection sequence values
					$check = $db->query("SELECT MAX(sequence) AS sequence FROM $cTable WHERE collid='$origCol'");
					$check = $check->fetch_assoc();
					$maxConSeq = $check['sequence'];
					$check = $db->query("SELECT MAX(sequence) AS sequence FROM collection WHERE schemeid=".escape($this->GetSID()));
					$check = $check->fetch_assoc();
					$maxColSeq = $check['sequence'];
					
					// if the control sequence is < conMax we don't need to move between
					// collections
					if ($conSeq < $maxConSeq) {
						$db->query("UPDATE $cTable SET sequence='$conSeq' WHERE sequence='".($conSeq+1)."' AND collid=".escape($origCol));
						$db->query("UPDATE $cTable SET sequence='".($conSeq+1)."' WHERE cid=".escape($cid));
					}
					// if the collection sequence is = colMax it's at the bottom and we
					// do nothing
					elseif ($colSeq < $maxColSeq) {
						$check = $db->query('SELECT collid FROM collection WHERE schemeid='.escape($this->GetSID()).' AND sequence='.escape($colSeq+1));
						$check = $check->fetch_assoc();
						$newCol = $check['collid'];
						
						$db->query("UPDATE $cTable SET sequence=(sequence+1) WHERE collid='$newCol'");
						$db->query("UPDATE $cTable SET collid='$newCol', sequence='1' WHERE cid=".escape($cid));
					}
				}
			}
		}
	}
	
	/**
	  * Modifies the order of collections within a scheme
	  *
	  * @param int $cid Collection ID
	  * @param string $direction Which direction to move the collection in the list
	  *
	  * @return void
	  */
	public function MoveCollection($cid, $direction)
	{
		global $db;
		
   		if (!Manager::IsLoggedIn()) { return false; }
   		
   		if(Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT, $this->GetPID()))
		{
			// MAKE SURE COLLECTION IS IN CURRENT SCHEME
			$check = $db->query('SELECT sequence FROM collection WHERE collid='.escape($cid).' AND schemeid='.escape($this->GetSID()).' LIMIT 1');
			if ($check->num_rows > 0)
			{
				$check = $check->fetch_assoc();
				$seq = $check['sequence'];
				
				if (($direction == 'up') && ($seq > 1))
				{
					$query = $db->query("UPDATE collection SET sequence = '$seq' WHERE sequence = '".($seq-1)."' AND schemeid=".escape($this->GetSID()));
					$query = $db->query("UPDATE collection SET sequence = '".($seq-1)."' WHERE collid=".escape($cid));
				} else if ($direction == 'down')
				{
					$result = $db->query('SELECT MAX(sequence) as sequence FROM collection WHERE schemeid='.escape($this->GetSID()));
					$result = $result->fetch_assoc();
					$m = $result['sequence'];
					
					if ($seq < $m) {
						$query = $db->query("UPDATE collection SET sequence = '$seq' WHERE sequence = '".($seq+1)."' AND schemeid=".escape($this->GetSID()));
						$query = $db->query("UPDATE collection SET sequence = '".($seq+1)."' WHERE collid=".escape($cid));
					}
				}
			}
		}
	}
	
	/**
	  * Delete a control from a collection
	  *
	  * @param int $cid_ Control ID to be deleted
	  *
	  * @return true on success
	  */
	public function DeleteControl($cid_)
	{
		global $db;
		
		$dTable = 'p'.$this->GetPID().'Data';
		$cTable = 'p'.$this->GetPID().'Control';
		
		$ctrl = $this->GetControl($cid_);
		
		// BAIL NOW IF WE CAN'T FIND CONTROL TO DELETE
		if (!$ctrl) { return false; }
	
		$ctrl->delete();

		// NOT SURE WHAT TO DO ABOUT THIS YET?		
		// instantiate a version for the public table, so any data associated with
		// this control gets deleted in the PublicData table aswell.

		// rebuild Dublin Core data if necessary
		$dcQuery = $db->query('SELECT dublinCoreFields FROM scheme WHERE schemeid='.escape($this->GetSID()).' LIMIT 1');
		$dcQuery = $dcQuery->fetch_assoc();
		if (!empty($dcQuery['dublinCoreFields']))
		{
			$oldXML = simplexml_load_string($dcQuery['dublinCoreFields']);
			$newXML = simplexml_load_string('<dublinCore />');
			// copy all fields that don't match the cid
			$somethingChanged = false;
			foreach($oldXML->children() as $dcType)
			{
				$idsToAdd = array();
				if (isset($dcType->id))
				{
					foreach($dcType->id as $id)
					{
						if ((string)$id != $cid_)
						{
							$idsToAdd[] = (string) $id;
						}
						else
						{
							$somethingChanged= true;
						}
					}
				}
				// If there are any control left in this field, add it
				if (!empty($idsToAdd))
				{
					$field = $newXML->addChild($dcType->getName());
					foreach($idsToAdd as $id)
					{
						$field->addChild('id', $id);
					}
				}
			}
			
			if ($somethingChanged)
			{
				$db->query('UPDATE scheme SET dublinCoreFields='.escape($newXML->asXML()).',dublinCoreOutOfDate=1 WHERE schemeid='.escape($sid).' LIMIT 1');
			}
		}
		
		// update sequence of other controls and delete record from collection table
		$db->query("UPDATE $cTable SET sequence=(sequence-1) WHERE collid=".$ctrl->GetGroup()." AND sequence > ".$ctrl->GetSequence());
		$db->query("DELETE FROM $cTable WHERE cid=$cid_");
		
		// SHOULD BE ALL CLEANED UP, NOW DROP CONTROL FROM THE CURRENT OBJECT INSTANCE
		foreach ($this->controlcollections as $ctrlcoll) {
			foreach($ctrlcoll['controls'] as $delctrl){
				if ($delctrl->cid == $cid_) {
					unset($delctrl);
				} 
			}                      
		}
		
		return true;
	}

	/**
	  * Delete a collection from a scheme
	  *
	  * @param int $collid_ ID of collection to be deleted
	  *
	  * @return true on success
	  */
	public function DeleteCollection($collid_)
	{
		global $db;
		
		$objcoll = null;
		$ctrls = null;
		
		// JUST PREVENT DELETION OF CONTROL GROUP 0 TO BE SAFE
		if ($collid_ == 0) { return false; }
		
		// FIND OUR TARGET TO DELETE
		foreach ($this->GetControls() as $thiscollid => $ctrlgrp)
		{
			if ($thiscollid == $collid_)
			{
				$objcoll = new ControlCollection($this->pid, $this->sid, $thiscollid);
				$ctrls = $ctrlgrp['controls'];
				break;
			}
		}
		
		// RETURN IF WE DIDN'T FIND THE COLLECTION
		if (!$objcoll) { return false; }
		
		foreach ($ctrls as $ctrl)
		{ 
			if (!$this->DeleteControl($ctrl->cid)) 
			{ 
				PrintErrDiv(gettext('Error deleting control collection, failed to delete contained control ').$ctrl->GetName());
				return false;
			}
		}
				
		// update sequence of other collections and delete record from collection table
		$db->query("UPDATE collection SET sequence=(sequence-1) WHERE schemeid=".$this->GetSID()." AND sequence > ".$objcoll->GetSequence());
		$db->query("DELETE FROM collection WHERE collid=$collid_");
		// HANDLE DB QUERY ERRORS HERE?
		
		// FINALLY, UNSET OUR ACTUAL OBJECT
		foreach ($this->controlcollections as $thiscollid => &$ctrlgrp)
		{
			if ($thiscollid == $collid_) { unset($ctrlgrp); break; }
		}
		
		return true;

	}
	
	/**
	  * Removes all data from a scheme (Records,Collections,Controls)
	  *
	  * @return void
	  */
	public function DeleteAllSchemeData(){
		global $db;
		
		$assocQuery = $db->query('SELECT id, value FROM p'.$this->GetPID().'Data WHERE schemeid='.escape($this->GetSID()).' AND cid=0');
   		while($a = $assocQuery->fetch_assoc())
   		{
   			$xml = simplexml_load_string($a['value']);
   			if (isset($xml->kid))
   			{
   				foreach($xml->kid as $kid)
   				{
   					// remove the association from $kid to $a['id']
   					\AssociatorControl::RemoveAllAssociations($kid, $a['id']);
   				}
   			}
   		}
   		
   		foreach($this->GetControls() as $collid => $coll)
   		{ if ($collid != 0) { $this->DeleteCollection($collid); } }
   	
   		foreach($this->GetControls()[0]['controls'] as $ctrl)
   		{ $this->DeleteControl($ctrl->cid); }   	
	}

	/**
	  * Updates whether a scheme layout can be used as a preset
	  *
	  * @param string $preset Determines if setting or unsetting
	  *
	  * @return void
	  */
	public function UpdateSchemePreset($preset)
	{
		global $db;
		
		// Make sure the user is a project or system admin to edit this variable
		if(Manager::IsProjectAdmin() && Manager::GetScheme())
		{
			// The Database needs 1 or 0, not "true" or "false")
			if($preset=='true'){
				$id = 1;
			}
			else{
				$id = 0;
			} 
			
			$db->query('UPDATE scheme SET allowPreset='.$id.' WHERE schemeid='.$this->sid.' LIMIT 1');
		}
	}
	
	/**
	  * Processes the importing of a scheme layout via XML
	  *
	  * @return result string
	  */
	public static function SubmitSchemeImport(){
		global $db;
		
		//Extract dataz from the xml file
		$xmlObject = simplexml_load_file($_FILES['xmlFileName']['tmp_name']);
		if (!$xmlObject) {
			echo gettext("Failed to parse XML file.");
			die();
			
		}
		if($xmlObject->getName() != 'Scheme'){
			Scheme::badXML(gettext("Incorrect document root."));
		}
		
		//Continue with extracting the SchemeDesc data
		if (!$xmlObject->SchemeDesc) {
			Scheme::badXML("No Scheme Description");
		}
		$schemeDesc = $xmlObject->SchemeDesc;
		$schName = (string)$schemeDesc->Name;
		$schDesc = (string)$schemeDesc->Description;
		//$schNextId = (string)$schemeDesc->NextId; //Probably shouldn't be used
		
		//Create the scheme itself
		echo gettext("Creating scheme ...");
		$query = "INSERT INTO scheme (pid, schemeName, sequence, description, nextid) ";
		$query .= "SELECT ".escape(Manager::GetProject()->GetPID()).", '";
		$query .= $schName."', COUNT(sequence) + 1, '";
		$query .= $schDesc."', 0 FROM scheme ";
		$query .= "WHERE pid=".escape(Manager::GetProject()->GetPID());
		$result = $db->query($query);
		
		if($result !== false){
			echo gettext("Succeeded!")."<br/>";
			$sid = $db->insert_id;
		}
		else{
			echo gettext("Failed - scheme could not be created.")."<br/>";
			Manager::PrintFooter();
			die();
		}
		//Creating collections and collection mapping
		if (!$xmlObject->Collections) {
			Scheme::badXML(gettext("No collections"));
		}
		echo "Creating Collections ... <br/>";
		$collections = $xmlObject->Collections;
		$colMapping = array(0=>0); //Collection 0 is special, just set the mapping for it here
		foreach($collections->children() as $col){
			$colName = escape((string)$col->Name, false);
			$colDesc = escape((string)$col->Description, false);
			$colSeq = (string)$col->Sequence;
			echo gettext("Creating Collection ")."$colName ... ";
			$colQuery = "INSERT INTO collection (schemeid, name, description,sequence) ";
			$colQuery .= "VALUES ($sid, '$colName','$colDesc',$colSeq)";
			//echo $colQuery;
			$success = $db->query($colQuery);
			if(!$success){
				echo gettext("Failed - collection ").$colName.gettext(" could not be created.");
				Manager::GetProject()->DeleteScheme($sid);
				Manager::PrintFooter();
				die();
			}
			echo gettext("Succeeded!")."<br/>";
			$colMapping[(int)$col->id] = $db->insert_id;
		}
		echo gettext("All Collections successfully created")."<br/>";
		//Creating scheme controls
		$conTable = "p".Manager::GetProject()->GetPID()."Control";
		if (!$xmlObject->Controls) {
			Scheme::badXML(gettext("No collections"));
		}
		echo gettext("Creating Controls")."... "."<br/>";
		$s = new Scheme(Manager::GetProject()->GetPID(), $sid);
		foreach($xmlObject->Controls->children() as $con){
			$conName = str_replace('_',' ',(string)$con->getName());
			$conDesc = (string)$con->Description;
			$conCollId = $colMapping[(int)$con->CollId];
			$conType = (string)$con->Type;
			$required = (int)$con->Required;
			$searchable = (int)$con->Searchable;
			$advSearchable = (int)$con->advSearchable;
			$showRes = (int)$con->showInResults;
			$showPub = (int)$con->showInPublicResults;
			$pubEntry = (int)$con->publicEntry;
			$options = (string)$con->options;
			$sequence = (int)$con->sequence;
			echo gettext("Creating Control ")."$conName ... ";
			$result = $s->CreateControl(false,$conName, $conType, $sid, $conCollId, $conDesc, $required, $searchable, $advSearchable, $showRes, $showPub, $pubEntry, $options, $sequence);
			if ($result !== true){
				echo gettext("Failed - ").$result."<br/>";
				Manager::GetProject()->DeleteScheme($sid);
				Manager::PrintFooter();
				die();
			}
			else echo gettext("Succeeded.")."<br/>";
		}
		echo gettext("All controls created successfully.")."<br/>";
		echo gettext("Structure for scheme ").$schName.gettext(" fully imported!")."<br/>";
	}
	
	/**
	  * Prints out an error for bad XML input and what caused the error
	  *
	  * @param string $details Details about the XML error
	  *
	  * @return void
	  */
	public static function badXML($details) {
		echo gettext("Bad XML Format - ").$details;
		die();
	}
	
	/**
	  * Processes the import of multiple records through XML. Visual displays progress
	  *
	  * @return void
	  */
	public function SubmitMultiRecordImport(){
		if (isset($_FILES['xmlFileName']) && $_FILES['xmlFileName']['type'] == 'text/xml') {
			
			//unset session variables that will be used
			if (isset($_SESSION['xmlRecordData'])) {
				unset($_SESSION['xmlRecordData']);
			}
			
			$uploadedFiles = false;
			$zipFiles = true;
			//if a zip folder was uploaded (ie error field != 4), extract files
			if (isset($_FILES['zipFolder']) && $_FILES['zipFolder']['error'] != 4) {
				$uploadedFiles = true;
				$zipFiles = extractZipFolder($_FILES['zipFolder']['tmp_name'],$_FILES['zipFolder']['name']);
				if(!$zipFiles){
					// error already printed in function
					Record::PrintImportRecordsForm();
					print '</div>';
					Manager::PrintFooter();
					die;
				}
			}
			
			//load record and build mapping table
			

		
	
			
			libxml_use_internal_errors(true);//suppresses php errors to allow user error handling
			if(simplexml_load_file($_FILES['xmlFileName']['tmp_name'])===false) //if there's an error loading the xml file
			{
			//REPORT ERRORS WITH INFO
				print '<div class="error">'.gettext('**ERROR: Could not open xml file').'</div>';	
					foreach(libxml_get_errors() as $error) {//user error handling
					
						echo "\t", "ERROR: ",$error->message, "<br>";//error that occured
						echo "\t", "FILE: ",$_FILES['xmlFileName']['name'], "<br>";//file that it occured in
						echo "\t", "LINE: ", $error->line, "<br>";//line number of file where error occured
						echo "\t", "COLUMN: ",$error->column, "<br>","<br>";//column of line in file where error occured
					}
					
			}
			else{//if there wasn't an error loading the file
				$xmlObject = simplexml_load_file($_FILES['xmlFileName']['tmp_name']);
				
				//create xml data handler
				$importer = new Importer($_REQUEST['pid'],$_REQUEST['sid'],$uploadedFiles);
				if ($importer->ValidateXML($_FILES['xmlFileName']['tmp_name'])) {
					//Record data is contained within the Data tag only - other parts of the file
					//are used for scheme import
					//If statement is for backwards compatibility - previously Data was the document root
					if($xmlObject->getName() == 'Scheme')$xmlObject = $xmlObject->Data;
					
					//load data from XML
					if ($xmlObject->ConsistentData) {
						$importer->loadConsistentData($xmlObject->ConsistentData);
					}
		
					$recordArray = array();
					for ($i=0; $i<count($xmlObject->Record); $i++) {
						$importer->loadSpecificData($xmlObject->Record[$i]);
						$recordArray[] = $importer->getRecordData();
					}
					
					//draw control mapping table
					echo '<div id="mainTable">';
					$importer->drawControlMappingTable();
					echo '</div>';
					
					Importer::PrintExportOptions();
					
					echo '<script type="text/javascript">';
					echo 'var pid = ' . $this->GetPID() . ';';
					echo 'var sid = ' . $this->GetSID() . ';';
					echo 'var importdata = ' . json_encode($recordArray) . ';';
					echo '</script>';
				} else {
					print '<div class="error">'.gettext('**ERROR: XML file could not be validated.').'</div>';
				}
			
			}
			
		} else { 
			print '<div class="error">'.gettext('**ERROR: Please upload an xml file').'</div>';
			Scheme::PrintImportRecordsForm();
		}
	}
}

?>
