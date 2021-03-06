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

// ADD JAVASCRIPT(S) FOR THIS CLASS IN THE GLOBAL CONTEXT LIKE SO..
Manager::AddJS('javascripts/project.js', Manager::JS_CLASS); 
Manager::AddJS('javascripts/colorbox/jquery.colorbox-min.js', Manager::JS_CLASS); 
Manager::AddCSS('javascripts/colorbox/colorbox.css', Manager::CSS_CLASS); 
/**
 * @class Project object
 *
 * This class respresents a Project in KORA
 */
class Project
{
	protected $pid;
	protected $name;
	protected $desc;
	protected $admingid;
	protected $defgid;
	protected $active;
	protected $quota;
	protected $currsize;
	protected $styleid;
	protected $styledesc;
	protected $stylepath;
	protected $schemes = null;
	
	/**
	  * Constructor for a project model
	  *
	  * @param int $pid Project ID
	  *
	  * @return void
	  */
	function __construct($pid_)
	{
		global $db;
		
		$results = $db->query("SELECT name, description, admingid, defaultgid, active, styleid, quota, currentsize FROM project WHERE pid=".escape($pid_)." LIMIT 1");
		if ($results->num_rows == 0) { throw new Exception(gettext('Invalid project requested, no project with pid ['.escape($pid_).']')); return false; }
		$results = $results->fetch_assoc();
		
		// LOOK-UP OK, SO START SETTING VALUES
		$this->pid = $pid_;
		$this->name = $results['name'];
		$this->desc = $results['description'];
		$this->admingid = $results['admingid'];
		$this->defgid = $results['defaultgid'];
		$this->active = $results['active'];
		$this->quota = $results['quota'];
		$this->currsize = $results['currentsize'];
		
		// SET THE STYLE, HAVE TO DO THIS IN 2ND QUERY BECAUSE IF STYLEID = 0, THEN IT'S NOT GOING TO BE FOUND IN TABLE SO IT WOULD BE A BAD JOIN
		if ($this->styleid == 0)
		{ $this->SetDefaultStyle(); }
		else
		{
			$style = $db->query("SELECT description, filepath FROM style WHERE styleid=".escape($this->styleid)." LIMIT 1");
			if ($results->num_rows == 0) 
			{ 
				Manager::PrintErrDiv("Warning: Invalid style set for project [{$this->name}], please inspect, using default."); 
				$this->SetDefaultStyle(); 
			}
			else
			{
				$style = $style->fetch_assoc();
				$this->styledesc = $style['description'];
				$this->stylepath = $style['filepath'];
			}
		}
		
		// THIS COULD BE DONE HERE BUT IS NOT, SO IT CAN BE LAZY-LOADED 
		// LATER THE FIRST TIME THE USER ACTUALLY REQUESTS THE PROJECTS SCHEMES 
		//$this->GetSchemes()
	}
	
	/**
	  * Set the default CSS theme for Kora Project
	  *
	  * @return void
	  */
	protected function SetDefaultStyle()
	{
		$this->styleid = 0;
		$this->styledesc = 'Default Style';
		$this->stylepath = 'css/default.css';
	}
	
	public function GetPID() { return $this->pid; }
	public function GetName() { return $this->name; }
	public function GetDesc() { return $this->desc; }
	public function GetQuota() { return $this->quota; }
	public function GetCurrSize() { return $this->currsize; }
	public function GetStyleDesc() { return $this->styledesc; }
	public function GetStylePath() { return $this->stylepath; }
	public function GetAdminGID() { return $this->admingid; }
	public function GetDefaultGID() { return $this->defgid; }
	public function IsActive() { return $this->active; }
	
	/**
	  * Get a scheme belonging to this Project
	  *
	  * @param int $sid_ Scheme ID we want to retreive
	  *
	  * @return requested scheme object
	  */
	public function GetScheme($sid_)
	{
		if (!is_numeric($sid_)) { return false; }
		foreach ($this->GetSchemes() as $s) {
			if ($s->GetSID() == $sid_) { return $s; }
		}
		// if we've made it here, we haven't found a scheme matching requested sid passed in, return false
		return false;
	}
	
	/**
	  * Gather a list of all schemes in the Project
	  *
	  * @return Array[scheme objects]
	  */
	public function GetSchemes()
	{
		// LAZY-LOAD
		if ($this->schemes) { return $this->schemes; }
	
		global $db;
		$this->schemes = array();
		
		$schemeList = $db->query('SELECT schemeid,sequence FROM scheme WHERE pid='.escape($this->GetPID()).' ORDER BY sequence');
		if (!$schemeList)
		{
			echo $db->error;
			throw new Exception(gettext('Error getting schemes for project ['.escape($pid_).']')); 
			return null;
		}
		else
		{
			while($scheme = $schemeList->fetch_assoc())
			{ $this->schemes[$scheme['sequence']] = new Scheme($this->GetPID(), $scheme['schemeid']); }
		}
		
		return $this->schemes;
	}

	/**
	  * Gets a list of all the control types of a passed in control list
	  *
	  * @param Array[ints] $cids The cids we wish to examine
	  *
	  * @return Array of the control types of the requested controls
	  */
	public function GetControlTypes($cids) {
		
		global $db;
		// get an array of the type of each control id so that we don't have to look it up each time
		$cTable = 'p'.$this->GetPID().'Control';
		$typeQuery  = "SELECT $cTable.cid AS cid, $cTable.name AS name, $cTable.type AS class, control.file AS file ";
		$typeQuery .= "FROM $cTable LEFT JOIN control ON ($cTable.type = control.class) ";
		$typeQuery .= "WHERE $cTable.cid IN (".implode(',', $cids).')';
		        
		$typeQuery = $db->query($typeQuery);
		$controlType = array();
		while ($ct = $typeQuery->fetch_assoc())
		{
		    // populate the array and ensure that the control class's file is included.
		    $controlType[$ct['cid']] = $ct;
		    if (!empty($ct['file']))
		    {
				require_once(basePath.CONTROL_DIR.$ct['file']);
		    }
		}
		return $controlType;
	}

	/**
	  * Get a list of all scheme IDs in this Project
	  *
	  * @return Array of Scheme IDs
	  */
	public function GetSchemeSIDs()
	{
		global $db;
		$schemeids = [];
		
		$schemeList = $db->query('SELECT schemeid,sequence FROM scheme WHERE pid='.escape($this->GetPID()).' ORDER BY sequence');
		if (!$schemeList)
		{
			echo $db->error;
			throw new Exception(gettext('Error getting schemes for project ['.escape($pid_).']')); 
			return null;
		}
		else
		{
			while($scheme = $schemeList->fetch_assoc())
			{ $schemeids[] = $scheme['schemeid']; }
		}
		
		return $schemeids;
	}	

	/**
	  * Get a list of all scheme as objects in this Project
	  *
	  * @return Array of Scheme objects
	  */
	public static function GetAllSchemes()
	{
		global $db;
		// get all associator permissions from the project table
		// TODO: this should probably be only projects a user is a member of.
		$query = 'SELECT pid,schemeid FROM scheme ORDER BY pid,schemeid';
		$results = $db->query($query);
		
		$retary = array();
		while($result = $results->fetch_assoc()){
			$retary[$result['schemeid']] = new Scheme($result['pid'],$result['schemeid']);
		}
		
		return $retary;
	}
	
	/**
	  * Get a list of all projects in this KORA installation
	  *
	  * @return Array of project models
	  */
	public static function GetAllProjects()
	{
		global $db;
		// get all associator permissions from the project table
		// TODO: this should probably be only projects a user is a member of.
		$query = 'SELECT pid FROM project ORDER BY pid';
		$results = $db->query($query);
		
		$retary = array();
		while($result = $results->fetch_assoc()){
			$retary[$result['pid']] = new Project($result['pid']);
		}
		
		return $retary;
	}
	
	/**
	  * Get user permissions for this project
	  *
	  * @param int $uid_ User ID we want the permissions for
	  *
	  * @return Array of permissions
	  */
	function GetUserPermissions($uid_ = null) 
	{
		global $db;
		
		// if not logged in bail or set
		if (!$uid_) { if (!Manager::IsLoggedIn()) { return 0; } $uid_ = Manager::GetUser()->GetUID(); }
		// if sysadmin, just return is admin here
		if (Manager::IsSystemAdmin()) { return PROJECT_ADMIN; }
		

		// get the user's permissions
		$results = $db->query('SELECT permGroup.permissions AS permissions FROM member LEFT JOIN permGroup ON member.gid = permGroup.gid WHERE member.uid='.escape($uid_).' AND member.pid='.escape($this->GetPID()).' LIMIT 1');
		if (!$results || ($results->num_rows == 0)) return 0;
		else {
			$results = $results->fetch_assoc();
			return $results['permissions'];
		}
	}
	
	/**
	  * Creates a new scheme for this project from form
	  *
	  * @return void
	  */
   	public function HandleNewSchemeForm()
   	{
		global $db;
   		//check if checkbox was checked
   		$public = 0;
   		if(isset($_REQUEST['publicIngestion']))
   		{
   			$public = 1;
   		}
   		
   		if (empty($_REQUEST['schemeName'])) Manager::PrintErrDiv(gettext('You must provide a name.'));
   		else {
   			$query = "INSERT INTO scheme (pid, schemeName, sequence, publicIngestion, legal, description, nextid) ";
   			$query .= "SELECT ".escape($this->GetPID()).", ";
   			$query .= escape($_REQUEST['schemeName']).", COUNT(sequence) + 1, ";
   			$query .= $public.", ".escape($_REQUEST['legal']).", ";
   			$query .= escape($_REQUEST['description']).", 0 FROM scheme ";
   			$query .= "WHERE pid=".escape($this->GetPID());
   			$result = $db->query($query);
   			
   			
   			if(!$result) { Manager::PrintErrDiv($db->error); }
   			else {
   				$sid = $db->insert_id;
   				$newscheme = new Scheme($this->GetPID(), $sid);
   				
   				//Add timestamp TextControl to every scheme created
   				//timestamp is not editable and not displayed on ingest
   				//on ingestion or when a record is edited, timestamp stores the current time
   				$tempReq = $_REQUEST;
   				$_REQUEST['name'] = 'systimestamp';
   				$_REQUEST['type'] = 'TextControl';
   				$_REQUEST['description'] = '';
   				$_REQUEST['submit'] = true;
   				$_REQUEST['collectionid'] = 0;
   				$_REQUEST['publicentry'] = "on"; //Not used
   				$newscheme->CreateControl(true);
   				$_REQUEST = $tempReq;
   				//End add timestamp control
   				
   				//Add a record owner to every scheme created
   				//owner is not editable and not displayed on ingest
   				$tempReq = $_REQUEST;
   				$_REQUEST['name'] = 'recordowner';
   				$_REQUEST['type'] = 'TextControl';
   				$_REQUEST['description'] = '';
   				$_REQUEST['submit'] = true;
   				$_REQUEST['searchable'] = "on";
   				$_REQUEST['advanced'] = "on";
   				$_REQUEST['collectionid'] = 0;
   				$_REQUEST['publicentry'] = "on"; //Not used
   				$newscheme->CreateControl(true);
   				$_REQUEST = $tempReq;
   				//End add owner control
   				
   				if (!empty($_REQUEST['preset']))
   				{
   					// Make sure this is a valid preset
   					$presetInfo = $db->query('SELECT schemeid, pid FROM scheme WHERE schemeid='.escape($_REQUEST['preset']).' AND allowPreset=1 LIMIT 1');
   					if ($presetInfo->num_rows > 0)
   					{
   						$presetInfo = $presetInfo->fetch_assoc();
   						
   						// Recreate the Collections and Store the mapping of old->new
   						// collectionMap is an associative mapping of oldID => newID
   						$collectionMap = array();
   						$collQuery = $db->query('SELECT collid, schemeid, name, description, sequence FROM collection WHERE schemeid='.$presetInfo['schemeid']);
   						while($c = $collQuery->fetch_assoc())
   						{
   							$insertQuery = $db->query('INSERT INTO collection (schemeid, name, description, sequence) VALUES ('.$newscheme->GetSID().','.escape($c['name']).','.escape($c['description']).','.escape($c['sequence']).')');
   							$collectionMap[$c['collid']] = $db->insert_id;
   						}
   						
   						// Recreate the Controls
   						$controlQuery = $db->query('SELECT collid, type, name, description, required, searchable, advSearchable, showInResults, showInPublicResults, publicEntry, options, sequence FROM p'.$presetInfo['pid'].'Control WHERE schemeid='.$presetInfo['schemeid']);
   						while ($c = $controlQuery->fetch_assoc())
   						{					
   							// don't clone the systimestamp(it has a collection ID of 0)
   							if($c['collid'] != 0)
   							{
   								// Clone the row, correcting the collection ID.
   								$query  = 'INSERT INTO p'.$this->GetPID().'Control (schemeid, collid, type, name, description, required, searchable, advSearchable, showInResults, showInPublicResults, publicEntry, options, sequence) ';
   								$query .= 'VALUES ('.$newscheme->GetSID().','.$collectionMap[$c['collid']].','.escape($c['type']).',';
   								$query .= escape($c['name']).','.escape($c['description']).',';
   								$query .= escape($c['required']).','.escape($c['searchable']).','.escape($c['advSearchable']).',';
   								$query .= escape($c['showInResults']).','.escape($c['showInPublicResults']).','.escape($c['publicEntry']).',';
   								$query .= escape($c['options']).','.escape($c['sequence']).')';
   								
   								$db->query($query);
   								
   							}
   						}
   					}
   				}            
   			}
   		}
   	}
   	
   	/**
	  * Edit a scheme in this project
	  *
	  * @param int $sid_ Scheme ID we want to edit
	  *
	  * @return result string on error
	  */
   	public function HandleEditSchemeForm($sid_)
   	{
   		if (empty($_REQUEST['schemeName'])) { Manager::PrintErrDiv(gettext('You must provide a name.')); return false; }
   		if (empty($_REQUEST['description'])) { Manager::PrintErrDiv(gettext('You must provide a description.')); return false; }
   		
   		global $db;
   		
   		// Validate the scheme id
   		$existenceQuery = $db->query('SELECT schemeid, schemeName, description FROM scheme WHERE pid='.$this->GetPID().' AND schemeid='.$sid_.' LIMIT 1');
   		$nameConflictQuery = $db->query('SELECT schemeid FROM scheme WHERE pid='.$this->GetPID().' AND schemeName='.escape($_REQUEST['schemeName']).' AND schemeid != '.$sid_.' LIMIT 1');
   		if ($existenceQuery->num_rows == 0)
   		{
   			Manager::PrintErrDiv(gettext('Invalid Scheme ID'));
   			return false;
   		}
   		else if ($nameConflictQuery->num_rows > 0)
   		{
   			Manager::PrintErrDiv(gettext('A scheme with that name already exists'));
   			return false;
   		}
   		else
   		{
   			//check if checkbox was checked
   			$public = (isset($_REQUEST['publicIngestion'])) ? 1 : 0;
   			$query  = 'UPDATE scheme SET schemeName='.escape($_REQUEST['schemeName']);
   			$query .= ', description='.escape($_REQUEST['description']);
   			$query .= ', publicIngestion='.$public;
   			$query .= ', legal='.escape($_REQUEST['legal']);
   			$query .= ' WHERE pid='.$this->GetPID().' AND schemeid='.$sid_.' LIMIT 1';
   			$db->query($query);
   		}
   	}
  	

	/**
	  * Creates a new project from form
	  *
	  * @return void
	  */
   	public static function HandleNewProjectForm()
   	{
   		global $db;
   		// syntax checks
   		$err = false;
   		
   		if (empty($_REQUEST['name'])) { $err = true; echo gettext('You must provide a project name.<br>'); }
   		if (empty($_REQUEST['description'])) { $err = true; echo gettext('You must provide a project description.<br>'); }
   		if (!is_numeric($_REQUEST['quota'])) { $err = true; echo gettext('Quota must be a number, use 0 for unlimited.<br>'); }
   		if (!isset($_REQUEST['active'])) { $err = true; echo gettext('Connection error, try again.<br>'); }
   		if (mb_strlen($_REQUEST['description']) > 255) { $err = true; echo gettext('Description too long.<br>'); }
   		if (mb_strlen($_REQUEST['name']) > 255) { $err = true; echo gettext('Name too long.<br>'); }
   		if (!$err){
   			// truncate field lengths
   			$name = mb_substr($_REQUEST['name'], 0, 255);
   			$description = mb_substr($_REQUEST['description'], 0, 255);
   			//grab quota
   			$quota = $_REQUEST['quota'];
   			// check for the active flag.  1 = enabled, anything else = inactive
   			$active = $_REQUEST['active'];
   			if ($active != 1) $active = 0;
   			$styleid = (isset($_REQUEST['style']) ? (int)$_REQUEST['style'] : 0);
   			
   			// Insert the initial information
   			$query  = 'INSERT INTO project (name, description, active, styleid, quota) VALUES (';
   			$query .= escape($name).', ';
   			$query .= escape($description).', ';
   			$query .= escape($active).',';
   			$query .= $styleid.',';
   			$query .= escape($quota).')';
   			
   			$result = $db->query($query);
   			$pid    = $db->insert_id;
   			
   			// create project control and data tables
   			$db->query('CREATE TABLE p'.$pid.'Control(
   				cid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
   				schemeid INTEGER UNSIGNED NOT NULL,
   				collid INTEGER UNSIGNED NOT NULL,
   				type VARCHAR(30) NOT NULL,
   				name VARCHAR(255) NOT NULL,
   				description VARCHAR(255),
   				required TINYINT(1) NOT NULL,
   				searchable TINYINT(1) NOT NULL,
   				advSearchable TINYINT(1) UNSIGNED NOT NULL,
   				showInResults TINYINT(1) NOT NULL,
   				showInPublicResults TINYINT(1) NOT NULL,
   				publicEntry TINYINT(1) NOT NULL,
   				options LONGTEXT NOT NULL,
   				sequence INTEGER UNSIGNED NOT NULL,
   				PRIMARY KEY(cid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
   			
   			$db->query('CREATE TABLE p'.$pid.'Data(
   				id VARCHAR(30) NOT NULL,
   				cid INTEGER UNSIGNED NOT NULL,
   				schemeid INTEGER UNSIGNED NOT NULL,
   				value LONGTEXT,
   				PRIMARY KEY(id,cid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
   			
   			
   			// create project data table for *PUBLIC* ingestion
   			$db->query('CREATE TABLE IF NOT EXISTS p'.$pid.'PublicData(
   				id VARCHAR(30) NOT NULL,
   				cid INTEGER UNSIGNED NOT NULL,
   				schemeid INTEGER UNSIGNED NOT NULL,
   				value LONGTEXT,
   				PRIMARY KEY(id,cid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
   			
   			
   			
   			// create the initial groups and insert the default admin into the admin group
   			$query  = 'INSERT INTO permGroup (pid, name, permissions) VALUES (';
   			$query .= "'$pid', 'Administrators', '".PROJECT_ADMIN."')";
   			
   			$result = $db->query($query);
   			$adminid= $db->insert_id;        
   			
   			// create the initial groups and insert the default admin into the admin group
   			$query  = 'INSERT INTO permGroup (pid, name, permissions) VALUES (';
   			$query .= "'$pid', 'Default', '0')";
   			
   			$result = $db->query($query);
   			$defid  = $db->insert_id;
   			
   			// Insert the Initial Admin User
   			$query  = 'INSERT INTO member (uid, pid, gid) VALUES (';
   			$query .= escape($_REQUEST['admin']).", '$pid', '$adminid')";
   			$result = $db->query($query);
   			
   			// update the project table to reflect the group ids
   			$query  = "UPDATE project SET admingid='$adminid', defaultgid='$defid' WHERE pid='$pid'";
   			$result = $db->query($query);
   		}
   	}
   	
   	/**
	  * Edit a project
	  *
	  * @param int $pid Project ID we want to edit
	  *
	  * @return result string on error
	  */
   	public static function HandleEditProjectForm($pid)
   	{
   		global $db;
   		
   		$err = false;
   		
   		if (empty($_REQUEST['name'])) { $err = true; echo gettext('You must provide a project name.<br>'); }
   		if (empty($_REQUEST['description'])) { $err = true; echo gettext('You must provide a project description.<br>'); }
   		if (!is_numeric($_REQUEST['quota'])) { $err = true; echo gettext('Quota must be a number, use 0 for unlimited.<br>'); }
   		if (!isset($_REQUEST['active'])) { $err = true; echo gettext('Connection error, try again.<br>'); }
   		if (mb_strlen($_REQUEST['description']) > 255) { $err = true; echo gettext('Description too long.<br>'); }
   		if (mb_strlen($_REQUEST['name']) > 255) { $err = true; echo gettext('Name too long.<br>'); }
   		
   		if (!$err)
   		{
   			$name = mb_substr($_REQUEST['name'], 0, 255);
   			$description = mb_substr($_REQUEST['description'], 0, 255);
   			$styleid = (isset($_REQUEST['style']) ? (int)$_REQUEST['style'] : 0);
   			$quota = $_REQUEST['quota'];
   			
   			$query  = 'UPDATE project SET name='.escape($name).', description='.escape($description);
   			$query .= ', active='.escape($_REQUEST['active']).", styleid=".$styleid.", quota=".$quota;
   			$query .= ' WHERE pid='.escape($_REQUEST['pid']);
   			$updateResults = $db->query($query);
   		}
   	}
   	
   	/**
	  * Prints html form for creating a new scheme
	  *
	  * @return void
	  */
   	public function PrintNewScheme()
	{
		global $db;
		?>
		<h2><?php echo gettext('Add New Scheme to ');?><?php echo htmlEscape($this->GetName())?></h2>
		<div id='cbox_error'></div>
		<div class="project_addScheme_form">
		<input type="hidden" name="schemeSubmit" value="true" />
		<table class="table_noborder">
		<tr><td align="right"><?php echo gettext('Name');?>:</td><td><input type="text" class="project_addScheme_name" <?php  if(isset($_REQUEST['schemeName'])) echo ' value="'.$_REQUEST['schemeName'].'" ';?> /></td></tr>
		<tr><td align="right"><?php echo gettext('Description');?>:</td><td><textarea class="project_addScheme_desc"><?php  if(isset($_REQUEST['description'])) echo $_REQUEST['description'];?></textarea></td></tr>
		<tr><td align="right"><?php echo gettext('Load Controls From');?>:</td><td>
		<?php $presetSchemes = Manager::GetPresetSchemes(); ?>
		<select class="project_addScheme_preset"><option value="0"><?php echo gettext('None (Empty Layout)');?></option>
		<?php 
		foreach ($presetSchemes as $id => $p)
		{ echo '<option value="'.$id.'">'.htmlEscape($p).'</option>'; }
		?>		
		</select></td></tr>
		<tr><td align="right"><?php echo gettext('Public Ingestible?');?>:</td><td><input type="checkbox" class="project_addScheme_publicIngest" <?php if(isset($_REQUEST['publicIngestion'])) echo 'checked="checked"';?>/></td></tr>
		<tr><td align="right"><?php echo gettext('Legal Notice');?>:</td><td><textarea class="project_addScheme_legal" ><?php if(isset($_REQUEST['legal'])) echo $_REQUEST['legal'];?></textarea></td></tr>
		<tr><td colspan="2" align="right"><input type="button" class="project_addScheme_submit" value="<?php echo gettext('Create New Scheme');?>" /></td></tr>
		</table>		
		</div>    
		<?php 
	}
	
	/**
	  * Prints out a list of schemes in the project
	  *
	  * @return void
	  */
	public function PrintSchemes()
   	{
   		if (!Manager::GetUser()) { echo gettext('Permission denied').'.'; return false; }
   		
   		$schemes = $this->GetSchemes();
   		
		if (sizeof($schemes) == 0)
		{
			//echo gettext('This project currently has no schemes').'.';
			echo '<table class="table"';
			echo '<tr><td>'.gettext('This project currently has no schemes').'</td></tr>';
		}
		else    // schemes exist
		{
			// get permissions now to avoid DB calls inside the loop
			$eperm = Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT);
			$dperm = Manager::GetUser()->HasProjectPermissions(DELETE_SCHEME);
			// Editing Scheme Layout is a DELETE_SCHEME permission because if you can delete
			// it you might as well be able to change it
			?>
			<table class="table">
			<tr>
			<?php  if ($eperm) { ?> <th></th> <?php  } ?>
			<th align="left" id="schemeNames"><?php echo gettext('Name');?></th>
			<th align="left" id="schemeDesc"><?php echo gettext('Description');?></th>
			<?php  if ($dperm) { ?> <th align="left"><?php echo gettext('Edit');?></th> <?php  } ?>
			<?php  if ($dperm) { ?><th align="left" id="schemeDelete"><?php echo gettext('Delete').'</th> '; } ?>
			<th align="left" id="schemeNames"><?php echo gettext('SID');?></th>
			</tr>
			<?php
			foreach ($schemes as $scheme)
			{
				// MAYBE THIS SHOULD BE FUNCTION IN SCHEME?
				echo '<tr class="project_scheme" sid="'.$scheme->GetSID().'">';
				if ($eperm) echo '<td><a class="up move_scheme_up" > /\ </a><a class="down move_scheme_down" > \/ </a></td>';
				echo '<td><div style="text-align: right; overflow: hidden;">';
				echo '<a href="schemeLayout.php?pid='.$scheme->GetPID().'&sid='.$scheme->GetSID().'" class="button">'.htmlEscape($scheme->GetName()).'</a></div></td>';
				echo '<td><div style="word-wrap: break-word; overflow: auto;">'.htmlEscape($scheme->GetDesc()).'</div></td>';
				if ($dperm) echo '<td><div style="text-align:center;"><a class="link edit_scheme">'.gettext('Edit');'</a></div></td>';
				if ($dperm) echo '<td><div class="delete"><a class="link delete_scheme" >X</a></div></td>';
				echo "<td>{$scheme->GetSID()}</td>";
				echo '</tr>'."\n";
			}
		}
		
		echo '<tr><td colspan=6>';
		if(Manager::GetUser()->HasProjectPermissions(CREATE_SCHEME, $this->GetPID())){
			echo '<a class="link add_scheme">'.gettext('Add A New Scheme').'</a>&nbsp;&nbsp;&nbsp;&nbsp;';
			echo '<a href="importScheme.php?pid='.$this->GetPID().'" class="link import_scheme">'.gettext('Import Scheme From XML').'</a>';
		}
		echo '</td></tr>';
		echo '</table>';
		
		return true;
   	}

   	/**
	  * Prints html form for editing a scheme
	  *
	  * @param int $sid_ Scheme ID to edit
	  *
	  * @return void
	  */
	public function PrintEditScheme($sid_)
	{
		global $db;
		$s = $this->GetScheme($sid_);
		if (!$s) { Manager::PrintErrDiv(gettext('Scheme with requested id not found.')); return false; }
		?>
		<h2><?php echo gettext('Edit Scheme ');?><?php echo htmlEscape($this->GetName())?></h2>
		<div id='cbox_error'></div>
		<div id="project_editScheme_form">
		<input type="hidden" name="schemeSubmit" value="true" />
		<table class="table_noborder">
		<tr><td align="right"><?php echo gettext('Name');?>:</td><td><input type="text" class="project_editScheme_name" <?php echo ' value="'.$s->GetName().'" ';?> /></td></tr>
		<tr><td align="right"><?php echo gettext('Description');?>:</td><td><textarea class="project_editScheme_desc"><?php echo $s->GetDesc();?></textarea></td></tr>
		<tr><td align="right"><?php echo gettext('Public Ingestible?');?>:</td><td><input type="checkbox" class="project_editScheme_publicIngest" <?php if($s->IsPublicIngestAllowed()) echo 'checked="checked"';?>/></td></tr>
		<tr><td align="right"><?php echo gettext('Legal Notice');?>:</td><td><textarea class="project_editScheme_legal" ><?php echo $s->GetLegal();?></textarea></td></tr>
		<tr><td colspan="2" align="right"><input type="button" class="project_editScheme_submit" value="<?php echo gettext('Edit Scheme');?>" /></td></tr>
		</table>		
		</div>    
		<?php 
	}

	/**
	  * Prints html form for creating new project
	  *
	  * @return void
	  */
	public static function PrintNewProjectForm()
	{
		global $db;
		echo '<h2>'.gettext('Create New Project').'</h2>'; ?>
		<div id='cbox_error' style="color:red"></div>
		<table class="kp_newProject_form">
   		<tr><td><?php echo gettext('Name')?>:</td><td><input type='text' class='kp_newProject_name' <?php  if(isset($_REQUEST['name'])) echo "value='".$_REQUEST['name']."'"; ?> /></td></tr>
   		<tr><td><?php echo gettext('Description')?>:</td><td><textarea class='kp_newProject_desc'><?php  if(isset($_REQUEST['description'])) echo $_REQUEST['description']; ?></textarea></td></tr>
   		<tr><td><?php echo gettext('Initial Administrator')?>:</td><td><select class='kp_newProject_admin'>
   		<?php 
	        // output all confirmed users here
	        $results = $db->query("SELECT uid, username, realName FROM user WHERE confirmed='1' AND searchAccount='0' ORDER BY username");
	        if (!$results) error_log($db->error);
	        
	        while($r = $results->fetch_assoc())
	        {
	        	echo '<option value="'.$r['uid'].'"';
	        	if (isset($_REQUEST['admin']) && ($_REQUEST['admin'] == $r['uid'])) echo ' selected="selected" ';
	        	echo '>'.htmlEscape($r['username']).' ('.htmlEscape($r['realName']).')</option>';  
	        }    
	        
	        ?>
   		</select>
   		</td></tr>
   		<tr>
   		<td><?php echo gettext('Active')?>:</td>
   		<td><select class='kp_newProject_active'>
   		<option value='1' <?php  if (isset($_REQUEST['active']) && ($_REQUEST['active'] == '1')) echo 'selected="selected"'; ?> ><?php echo gettext('Yes')?></option>
   		<option value='-1' <?php  if (isset($_REQUEST['active']) && ($_REQUEST['active'] == '-1')) echo 'selected="selected"'; ?> ><?php echo gettext('No')?></option>
   		</select></td>
   		</tr>
   		<tr><td><?php echo gettext('Style')?>:</td>
   		<td><select class='kp_newProject_style'>
   		<option value='0'><?php echo gettext('Default KORA Appearance')?></option>
   		<?php 
   		$styleQuery = $db->query('SELECT styleid, description FROM style ORDER BY description');
   		while ($s = $styleQuery->fetch_assoc())
   		{
   			echo "<option value='".$s['styleid']."'>".htmlEscape($s['description']).'</option>';
   		}
   		?>
                </select></td>
                </tr>   			
                <tr><td><?php echo gettext('Quota');?>:</td><td><input type="text" class="kp_newProject_quota" <?php if(isset($_REQUEST['quota'])) echo "value='".$_REQUEST['quota']."'";?> /><?php echo gettext(' MB (0 for default)');?></td></tr>
                <tr><br></tr><tr><td colspan='2'><input type='button' class="kp_newProject_submit" value='<?php echo gettext('Create New Project')?>' /></td></tr>
   		</table><?php
   	}
   	
   	/**
	  * Prints html form for editing a project
	  *
	  * @param int $pid Project ID we want to edit
	  *
	  * @return void
	  */
   	public static function PrintEditProjectForm($pid)
   	{
   		global $db;
   		
   		// Get Project Information
   		$results = $db->query("SELECT name, description, defaultgid, active, styleid, quota FROM project WHERE pid=".escape($pid)." LIMIT 1");
   		$results = $results->fetch_assoc();
   		//TODO:FORM
   		echo '<h2>'.gettext('Edit Project Information').'</h2>'; ?>
   		<div id='cbox_error' style="color:red"></div>
		<table class="kp_editProject_form">
   		<tr><td><?php echo gettext('Name')?>:</td><td><input type='text' class='kp_editProject_name' value='<?php echo htmlEscape($results['name'])?>' /></td></tr>
   		<tr><td><?php echo gettext('Description')?>:</td><td><textarea class='kp_editProject_desc'><?php echo htmlEscape($results['description'])?></textarea></td></tr>
   		<tr>
   		<td><?php echo gettext('Active')?>:</td>
   		<td><select class='kp_editProject_active'>
   		<option value='1' <?php  if ($results['active'] == '1') echo 'selected="selected"'; ?> ><?php echo gettext('Yes')?></option>
   		<option value='0' <?php  if ($results['active'] == '0') echo 'selected="selected"'; ?> ><?php echo gettext('No')?></option>
   		</select></td>
   		</tr>
   		<tr><td><?php echo gettext('Style')?>:</td>
                <td><select class='kp_editProject_style'>
                <option value='0' <?php  if ($results['styleid'] == 0) echo 'selected="selected" '; ?> ><?php echo gettext('Default KORA Appearance')?></option>
                <?php 
                $styleQuery = $db->query('SELECT styleid, description FROM style ORDER BY description');
                while ($s = $styleQuery->fetch_assoc())
                {
                	echo "<option value='".$s['styleid']."' ";
                	if ($results['styleid'] == $s['styleid']) echo 'selected="selected" ';
                	echo '>'.htmlEscape($s['description']).'</option>';
                }
                ?>
                </select></td>
                </tr>
                <tr><td><?php echo gettext('Quota');?>:</td><td><input type="text" class="kp_editProject_quota" value='<?php echo htmlEscape($results['quota']);?>' /> MB</td></tr>			
                <tr><td colspan='2'><input type='button' class="kp_editProject_submit" value='<?php echo gettext('Edit Project Details')?>' /></td></tr>
   		</table>
   		</div><?php
   	}
   	
   	/**
	  * Print outline html form for the manage projects page
	  *
	  * @return void
	  */
   	public static function PrintManageForm()
	{
		echo '<h2>'.gettext('Manage Projects').'</h2>';
		?>
		<table class="table" id="projectmanagerform">
		<?php 
			Project::PrintManageActiveProjects();
			Project::PrintManageInactiveProjects();
			Project::PrintManageSubmit();
		?>
		</table>
		<?php 		
	}
	
	/**
	  * Print html list of active projects
	  *
	  * @return void
	  */
	public static function PrintManageActiveProjects(){
		global $db;
	
		?><tr><th><?php echo gettext('Active Projects')?></th><th></th><th><?php echo gettext('Inactive Projects')?></th></tr>
		<tr><td><select class="textarea_select" id="kp_manage_active" multiple="multiple">
		<?php 
		// output all selected projects here
		$results = $db->query("SELECT pid, name FROM project WHERE active='1' ORDER BY name");
		if (!$results) error_log($db->error);
		
		while($r = $results->fetch_assoc())
		{
			echo '<option value="'.$r['pid'].'">'.htmlEscape($r['name']).'</option>';  
		}
		?>
		</select></td><?php
	}
	
	/**
	  * Print html list of inactive projects
	  *
	  * @return void
	  */
	public static function PrintManageInactiveProjects(){
		global $db;
		
		//TODO:FORM?><td valign="middle" style="text-align: center;">
		<input type="button" class="kp_manage_activateProj" value="<--" />
		<input type="button" class="kp_manage_deactivateProj" value="-->" />
		</td><td><select class="textarea_select" id="kp_manage_inactive" multiple="multiple">
		<?php     
		// output all inactive projects here
		$results = $db->query("SELECT pid, name FROM project WHERE active='0' ORDER BY name");
		if (!$results) error_log($db->error);
		
		while($r = $results->fetch_assoc())
		{
			echo '<option value="'.$r['pid'].'">'.htmlEscape($r['name']).'</option>';  
		}
		?>
		</select></td></tr><?php
	}
	
	/**
	  * Print html buttons for the manage projects form
	  *
	  * @return void
	  */
	public static function PrintManageSubmit(){
		?><tr><td colspan="3">
		<input type="button" class="kp_manage_newProjBtn" value="<?php echo gettext('New Project')?>" />
		<input type="button" class="kp_manage_editProjBtn" value="<?php echo gettext('Edit Project Information')?>" />
		<input type="button" class="kp_manage_delProjBtn" value="<?php echo gettext('Delete Project')?>" />
		</td></tr><?php
	}
	
	/**
	  * Print html table of user groups in a project
	  *
	  * @return void
	  */
	public function PrintGroups() {
		global $db;
		$results = $db->query('SELECT * FROM permGroup WHERE pid='.escape($this->GetPID()));
		echo '<table class="table">
		<th>'.gettext('Name').'</th>
		<th>'.gettext('Admin').'</th>
		<th>'.gettext('Ingest Obj').'</th>
		<th>'.gettext('Delete Obj').'</th>
		<th>'.gettext('Edit Layout').'</th>
		<th>'.gettext('Create Scheme').'</th>
		<th>'.gettext('Delete Scheme').'</th>
		<th>'.gettext('Export Scheme').'</th>
		<th>'.gettext('Moderate Public Ingestion').'</th>
		<th>'.gettext('View Search').'</th>
		<th>'.gettext('Action').'</th>';
		while($array = $results->fetch_assoc()) {
			echo '<tr><td><div style="width: 200px; overflow: hidden;">'.htmlEscape($array['name']).'</td>
			<td><input type="checkbox" name="gpadmin" gid="'.$array['gid'].'" perm="'.PROJECT_ADMIN.'"';
			if($array['permissions'] & PROJECT_ADMIN )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td><td><input type="checkbox" name="gpingest" gid="'.$array['gid'].'" perm="'.INGEST_RECORD.'"';
			if($array['permissions'] & INGEST_RECORD )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td><td><input type="checkbox"  name="gpdelete" gid="'.$array['gid'].'" perm="'.DELETE_RECORD.'"';
			if($array['permissions'] & DELETE_RECORD )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td><td><input type="checkbox"  name="gpedit" gid="'.$array['gid'].'" perm="'.EDIT_LAYOUT.'"';
			if($array['permissions'] & EDIT_LAYOUT )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td><td><input type="checkbox"  name="gpcreate" gid="'.$array['gid'].'" perm="'.CREATE_SCHEME.'"';
			if($array['permissions'] & CREATE_SCHEME )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td><td><input type="checkbox"  name="gpdeletescheme" gid="'.$array['gid'].'" perm="'.DELETE_SCHEME.'"';
			if($array['permissions'] & DELETE_SCHEME )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td><td><input type="checkbox"  name="gpexportscheme" gid="'.$array['gid'].'" perm="'.EXPORT_SCHEME.'"';
			if($array['permissions'] & EXPORT_SCHEME )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td><td><input type="checkbox"  name="gpmoderator" gid="'.$array['gid'].'" perm="'.MODERATOR.'"';
			if($array['permissions'] & MODERATOR )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td><td><input type="checkbox"  name="gpviewsearch" gid="'.$array['gid'].'" perm="'.VIEW_SEARCH.'"';
			if($array['permissions'] & VIEW_SEARCH )
				echo ' checked="true" ';
			if($array['gid'] == $this->GetAdminGID())
				echo ' disabled="true" ';
			echo ' /></td>';
			if($array['gid'] != $this->GetDefaultGID() && $array['gid'] != $this->GetAdminGID())
				echo '<td><a class="link delgroup"  gid="'.$array['gid'].'">X</a></td>';
			echo '</tr>';
		}
		echo '<br /><th>'.gettext('Add New Group').'</th>
		<tr><td><input type="textbox" name="groupname" id="groupname" /></td>
		<td><input type="checkbox" name="newadmin" id="newadmin" /></td>
		<td><input type="checkbox" name="newingestobj" id="newingestobj" /></td>
		<td><input type="checkbox" name="newdelobj" id="newdelobj" /></td>
		<td><input type="checkbox" name="newedit" id="newedit" /></td>
		<td><input type="checkbox" name="newcreate" id="newcreate" /></td>
		<td><input type="checkbox" name="newdelscheme" id="newdelscheme" /></td>
		<td><input type="checkbox" name="newexport" id="newexport"/></td>
		<td><input type="checkbox" name="newmoderator" id="newmoderator"/></td>
		<td><input type="checkbox" name="newviewsearch" id="newviewsearch"/></td>
		<td><a class="link addgroup" onclick="addGroup()">'.gettext('Add').'</a></tr>
		</table>';
	}
	
	/**
	  * Print list of users belonging to project
	  *
	  * @return void
	  */
	public function PrintProjectUsers() {
		global $db;
		$result = $db->query("select member.uid,permGroup.gid, permGroup.name,user.username FROM member JOIN user ON (member.uid = user.uid)
			JOIN permGroup ON (member.gid = permGroup.gid) WHERE member.gid != 0 AND user.searchAccount=0 AND member.pid = {$this->GetPID()}");
		if($result) {
			echo '<table class="table"><th>'.gettext('Username').'</th><th>'.gettext('Group').'</th><th>'.gettext('Action').'</th>';       
			while($array = $result->fetch_assoc()) {
				if($array['username']!='koraadmin')
					echo '<tr><td>'.htmlEscape($array['username']).'</td><td>'.htmlEscape($array['name']).'</td><td><a uid="'.$array['uid'].'" class="delprojectuser">X</a></td></tr>';
			}
			$result = $db->query("SELECT user.username,user.uid FROM user 
				WHERE uid NOT IN (SELECT member.uid from member,permGroup WHERE member.pid = {$this->GetPID()} 
				AND member.gid != permGroup.gid) AND user.searchAccount=0 ORDER BY user.username ASC");
			echo $db->error;
			echo '<tr><td><select name="useradd" id="useradd">';
			while($array = $result->fetch_assoc()) {
				//print_r($array);
				echo '<option value="'.$array['uid'].'">'.htmlEscape($array['username']).'</option>';
			}
			echo '</select></td><td><select name="groupadd" id="groupadd">';
			$result = $db->query("SELECT name,gid FROM permGroup WHERE pid={$this->GetPID()}");
			while($array = $result->fetch_assoc()) {
				echo '<option value="'.$array['gid'].'">'.htmlEscape($array['name']).'</option>';
			}
			echo '</select></td><td><a class="link addprojectuser">'.gettext('Add').'</a></td></tr>';
			echo '</table>';
		}
	}
	
	/**
	  * Print project quick jump form for Bread Crumbs
	  *
	  * @return void
	  */
	public function PrintQuickJump()
	{ 
		$authProjs = Manager::GetUser()->GetAuthorizedProjects();
		if (count($authProjs) > 1) {
			//multiple projects = drop down menu
			?>
			<select class='kpquickjump' size="1">
			<?php
			foreach($authProjs as $apid) {
				$aproj = new Project($apid);
				$selected = ($this->GetPID() == $apid) ? " selected='selected' " : '';  
				echo '<option value="'.$aproj->GetPID().'"'.$selected.'>'.htmlEscape($aproj->GetName()).'</option>';
			}
			?>
			</select>
			<?php
		} else {
			//single project = link
			$aproj = new Project($authProjs[0]);
			echo '<a href="'.baseURI.'selectProject.php?pid='.$this->GetPID().'">'.htmlEscape($aproj->GetName()).'</a>';
		}
	}
	
	/**
	  * Add a user group to a project
	  *
	  * @param string $name Name of group
	  * THESE PARAMETERS TELL WHAT PERMISSIONS GROUP MEMBERS HAVE FOR THIS PROJECT
	  * @param string $admin 
	  * @param string $ingestobj
	  * @param string $delobj
	  * @param string $edit
	  * @param string $create
	  * @param string $delscheme
	  * @param string $export
	  * @param string $moderator
	  * @param string $viewsearch
	  *
	  * @return result string on success
	  */
	public function addGroup($name,$admin,$ingestobj,$delobj,$edit,$create,$delscheme,$export,$moderator,$viewsearch) {
		global $db;
		$perms = 0;
		if(!empty($name)) {
			if($admin == "true")
				$perms += PROJECT_ADMIN;
			if($ingestobj == "true")
				$perms += INGEST_RECORD;
			if($delobj == "true")
				$perms += DELETE_RECORD;
			if($edit == "true")
				$perms += EDIT_LAYOUT;
			if($create == "true")
				$perms += CREATE_SCHEME;
			if($delscheme == "true")
				$perms += DELETE_SCHEME;
			if($export == "true")
				$perms += EXPORT_SCHEME;
			if($moderator == "true")
				$perms += MODERATOR;
			if($viewsearch == "true")
				$perms += VIEW_SEARCH;
			
			print DoQueryPrintError("INSERT INTO permGroup (pid,name,permissions) VALUES ({$this->GetPID()},".escape($name).",$perms)",
				gettext('Group added to project'),
				gettext('Problem adding new group')
				);
		}
	}
	
	/**
	  * Updates the permissions of a user group
	  *
	  * @param int $permission Permissions to modify
	  * @param bool $checked Activate permissions or remove permissions
	  * @param int $gid Group ID
	  *
	  * @return result string on success
	  */
	public function updateGroupPerms($permission, $checked, $gid) {
		global $db;
		if($checked) {
			print DoQueryPrintError("UPDATE permGroup SET permissions=(permissions+$permission) WHERE gid=".escape($gid)." LIMIT 1",
				gettext('Permissions updated'),
				gettext('Problem updating permissions')
				);
		}
		else {
			print DoQueryPrintError("UPDATE permGroup SET permissions=(permissions-$permission) WHERE gid=".escape($gid)." LIMIT 1",
				gettext('Permissions updated'),
				gettext('Problem updating permissions')
				);
		}
	}
	
	/**
	  * Delete a user group
	  *
	  * @param int $gid Group ID to delete
	  *
	  * @return result string on success
	  */
	public function deleteGroup($gid){
		global $db;
		$result = $db->query("SELECT defaultgid FROM project where pid={$gid} LIMIT 1");
		$array = $result->fetch_assoc();
		print DoQueryPrintError("DELETE FROM permGroup where gid=".escape($gid)." LIMIT 1",
			gettext('Group removed'),
			gettext('Problem removing group')
			);
		print DoQueryPrintError("UPDATE member SET gid={$this->GetDefaultGID()} WHERE gid={$gid}",
			gettext('Users returned to default group'),
			gettext('Problem returning users to default group')
			);
	}
	
	/**
	  * Add user to a project group
	  *
	  * @param int $user User ID to add
	  * @param int $group Group ID to add user to
	  *
	  * @return result string on success
	  */
	public function addProjectUser($user,$group) {
		print DoQueryPrintError("INSERT INTO member (uid,pid,gid) VALUES ($user,{$this->GetPID()},$group)",
			gettext('User added to project'),
			gettext('Problem adding user to project')
			);
	}
	
	/**
	  * Remove user from a project
	  *
	  * @param int $user User ID to remove
	  *
	  * @return result string on success
	  */
	public function deleteProjectUser($user) {
		print DoQueryPrintError("DELETE FROM member WHERE uid=$user AND pid={$this->GetPID()} LIMIT 1",
			gettext('User removed from project'),
			gettext('Problem removing user from project')
			);
	}
	
	/**
	  * Delete a project and it's contents from Kora
	  *
	  * @return void
	  */
	public function DeleteProject()
   	{
   		global $db;
   		
   		// TODO, THIS SHOULD BE A FUNCTION IN THIS CLASS TO GET PROJECT SCHEMES AS OBJECTS AND
   		// THEN PROBABLY CALL DELETE ON THEM BY LEVERAGING A FUNCTION IN THE SCHEME CLASS
   		// get schemes in project
   		$schemeList = $db->query("SELECT schemeid FROM scheme WHERE pid=".$this->pid);
   		
   		// call deleteScheme on each
   		while ($s = $schemeList->fetch_assoc()) {
   			$this->DeleteScheme($s['schemeid']);
   		}
   		
   		// delete project tables
   		$db->query('DROP TABLE IF EXISTS p'.$this->pid.'Control');
   		$db->query('DROP TABLE IF EXISTS p'.$this->pid.'Data');
   		$db->query('DROP TABLE IF EXISTS p'.$this->pid.'PublicData');
   		
   		// final cleanup
   		$deleteResults = $db->query('DELETE FROM project WHERE pid='.escape($this->pid));
   		$deleteResults = $db->query('DELETE FROM permGroup WHERE pid='.escape($this->pid));
   		$deleteResults = $db->query('DELETE FROM member WHERE pid='.escape($this->pid));
   		$deleteResults = $db->query('DELETE FROM dublinCore WHERE pid='.escape($this->pid));
   	}
   	
   	/**
	  * Set a project to inactive or active
	  *
	  * @param bool $on Active or inactive
	  *
	  * @return void
	  */
	public function SetProjectActive($on=true){
		global $db;
		
		if($on){
			$db->query("UPDATE project SET active='1' WHERE pid='".$this->pid."'");
		}else if(!$on){
			$db->query("UPDATE project SET active='0' WHERE pid='".$this->pid."'");
		}
	}

   	/**
	  * Change the order of a scheme list in a project
	  *
	  * @param string $sid Scheme ID to reorder
	  * @param string $direction Direction to move the scheme in the list
	  *
	  * @return void
	  */
   	public function MoveScheme($sid, $direction)
   	{
   		global $db;
   		
   		if (!Manager::IsLoggedIn()) { return false; }
   		
   		if(Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT, $this->GetPID()))
   		{
   			$result = $db->query('SELECT sequence FROM scheme WHERE schemeid='.escape($sid));
   			if ($result->num_rows == 0) return;
   			
   			$result = $result->fetch_assoc();
   			$r = $result['sequence'];
   			
   			if (($direction == 'up') && ($r > 1)) {
   				$query = $db->query("UPDATE scheme SET sequence = '$r' WHERE sequence = '".($r-1)."' AND pid=".escape($this->GetPID()));
   				$query = $db->query("UPDATE scheme SET sequence = '".($r-1)."' WHERE schemeid=".escape($sid));
   			} else if ($direction == 'down') {
   				$result = $db->query('SELECT MAX(sequence) as sequence FROM scheme WHERE pid='.escape($this->GetPID()));
   				$result = $result->fetch_assoc();
   				$m = $result['sequence'];
   				
   				if ($r < $m) {
   					$query = $db->query("UPDATE scheme SET sequence = '$r' WHERE sequence = '".($r+1)."' AND pid=".escape($this->GetPID()));
   					$query = $db->query("UPDATE scheme SET sequence = '".($r+1)."' WHERE schemeid=".escape($sid));
   				}
   			}
   		}
   	}
	
	/**
	  * Delete a scheme from a Project
	  *
	  * @param int $schemeid Scheme ID to be deleted
	  *
	  * @return void
	  */
   	public function DeleteScheme($schemeid)
   	{
   		global $db;
   		
   		$scheme = $this->GetScheme($schemeid);
   		if (!$scheme) { Manager::PrintErrDiv(gettext('Failed to find scheme with this id to delete')." ($schemeid)"); return false; }
   		
   		$scheme->DeleteAllSchemeData();	
   		
   		//delete remainder of scheme info
   		$query = $db->query("UPDATE scheme SET sequence = (sequence-1) WHERE sequence > {$scheme->GetSequence()} AND pid={$this->GetPID()}");
   		$query = $db->query("DELETE FROM scheme WHERE schemeid=$schemeid");
   		$query = $db->query("DELETE FROM recordPreset WHERE schemeid=$schemeid");
   		$query = $db->query("DROP TABLE IF EXISTS premisScheme".$scheme->GetSID().";");
   	}	
}

?>
