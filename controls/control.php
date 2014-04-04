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

if (defined('basePath')) {
	require_once(basePath.'includes/utilities.php');
	require_once(basePath.'includes/fixity.php');
	include_once(basePath.'includes/controlDataFunctions.php');
}
else {
   require_once('../includes/utilities.php');
   require_once('../includes/fixity.php');
   include_once('../includes/controlDataFunctions.php');
}

/**
 * @class Control Template class from which KORA controls inherit and derive their core interface
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
	abstract function delete();
	
	/**
	 * Display the class input form.  HTML input objects should be named
	 * such that multiple controls can appear and be distinguished on the same
	 * page.  Recommended naming scheme:
	 * <input type="whatever" name="p#c#"> or
	 * <input type="whatever" name="p#c#_suffix">
	 */
	abstract function display();
	
	abstract function displayXML();
	
	/**
	 * This function shows a dialog allowing the user to edit the options (list options,
	 * colors, whatever) for the control.  This must be completely self-contained (aka AJAX).
	 */
	abstract function displayOptionsDialog();
	
	/**
	 * Return a control's name
	 */
	abstract function getName();
	
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
    
	protected function isOK() {
		return (intval($this->pid) > 0 && intval($this->cid) > 0);
	}
	
	protected function validateControlID()
	{
        global $db;
		
		if (empty($pid) || empty($cid)) return false;
		
        $controlCheck = $db->query('SELECT cid FROM p'.$pid.'Control WHERE cid='.escape($cid).' LIMIT 1');
        return ($controlCheck->num_rows > 0);
	}
	
	protected function validateRecordID()
	{
		$ridInfo = parseRecordID($rid);
		
		// See if the format was invalid or the project doesn't match
		if (($ridInfo == false) || ($ridInfo['project'] != $pid)) return false;
		
		// otherwise check the control ID - if it's OK the RID is good
		else return $this->validateControlID();
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
}


?>
