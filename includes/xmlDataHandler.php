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

// Initial Version: Meghan McNeil, 2009
include_once('controlDataFunctions.php');

class XMLDataHandler {

	protected $consistentDataSet = array();
	protected $specificDataSet = array();
	
	protected $keyfield = '';
	
	protected $existingTagnames = array();
	protected $controlMapping = array();
	protected $unmappedControls = array();

	protected $autoMappingArray = array( "All File Controls"=>array('ImageControl','FileControl') );
	public $associationArray = array();
	
	public function XMLDataHandler($pid,$sid,$uploadedFiles = false) {
		$this->uploadedFiles = $uploadedFiles;
		$this->pid = $pid;
		$this->sid = $sid;
	}
	
	/***************************************************
	 * 			  START PROTECTED FUNCTIONS			   *
	 ***************************************************/
	
	/**
	 * Adds a tag name to existingTagnames and if a control matches the tagname it's added to controlMapping
	 */
	protected function addTagName($tagname) { 
		global $db;
		
		if (!in_array($tagname,$this->existingTagnames)) {
			$query = "SELECT name FROM p".$this->pid.'Control WHERE name="'.$tagname.'" AND schemeid='.$this->sid." LIMIT 1";
			$result = $db->query($query);
			$data = $result->fetch_assoc();
			
			if ($data['name']) {
				$this->controlMapping[$tagname] = $data['name'];
			}
			
			array_push($this->existingTagnames,$tagname);
		}
	}
	
	/**
	 * Checks to see if a mapping was previously defined
	 * @param $tag 
	 * 		tagname string
	 * @return $defaultMapping 
	 * 		control name that if could be mapped too
	 */
	protected function checkPreviousMapping($tag) {
		$defaultMapping = false;
		
		//if the mapping was already selected or maps up with a control name, use that control as default
		if (isset($_SESSION['controlMapping_'.$this->sid][$tag]) /*&& $this->controlNameExists($_SESSION['controlMapping_'.$this->sid][$tag])*/) {
			$defaultMapping = $_SESSION['controlMapping_'.$this->sid][$tag];
		} else if (isset($this->controlMapping[$tag])) {
			$defaultMapping = $this->controlMapping[$tag];
		}
		
		if($defaultMapping) {
			$controlData = explode('->',getControlType($defaultMapping,$this->sid));
			
			if($controlData[1] == "AssociatorControl") {
				$allowedSchemes = getAllowedAssociations($controlData[0]);
				
				$schemeNames = getSchemeNames(array($allowedSchemes[0]));
				
				array_push($this->associationArray,array($this->sid,$tag,$allowedSchemes[0],implode('/',$schemeNames[$allowedSchemes[0]])));
			}
			
			$this->removeFromUnmappedControls($defaultMapping);
			
			//if the defaultmapping is in the autoMapping array, remove the other controls
			if (in_array( $defaultMapping,array_keys($this->autoMappingArray) )) {
				$this->removeControlsByControlType($defaultMapping);
			}
		}
		
		return $defaultMapping;
	}
	
	/**
	 * Get all the controls from the selected scheme
	 */
	protected function getAllControls() {
		global $db;
		
		$query = "SELECT cid,name FROM p".$this->pid."Control WHERE schemeid=".$this->sid;
		$result = $db->query($query);
		
		$returnValues = array();
		while ($data = $result->fetch_assoc()) {
			$returnValues[$data['cid']] = $data['name'];
		}
		return $returnValues;
	}
	
	/**
	 * Returns the control name based on control Id
	 */
	protected function getControlNameById($cid) {
		global $db;
		
		$query = "SELECT name FROM p".$this->pid."Control WHERE cid=".$cid." AND schemeid=".$this->sid." LIMIT 1";
		$result = $db->query($query);
		$data = $result->fetch_assoc();
		
		return $data['name'];
	}
	
	/**
	 * Decifers whether or not to save values as xml or a string
	 * Recognizes:
	 * <BaseTag>
	 * 		<page>page1.jpg</page>
	 * 		<page>page2.jpg</page>
	 * 		<page>page3.jpg</page>
	 * </BaseTag>
	 * <AnotherTag>a value</AnotherTag>
	 */
	protected function parseValues($value) {
		
//		if ($value->children()){
        //attributes are invisible to a foreach loop, but they still count as children.
        if (count($value->children()) > count($value->attributes())) {
			return $value->asXML();
		} else {
			return (string) $value;
		}
	}
	
	/**
	 * Remove control names from unmapped control based on a general mapping
	 * @param $key general mapping (ie. All File Controls)
	 */
	protected function removeControlsByControlType($key) {
		global $db;
		
		$query = "SELECT name FROM p".$this->pid."Control WHERE type IN ('".implode("','",$this->autoMappingArray[$key])."')";
		$result = $db->query($query);
		
		while($data = $result->fetch_assoc()) {
			$this->removeFromUnmappedControls($data['name']);
		}
	}
	
	/**
	 * Remove control names from unmappedControls array
	 * @param $value to remove from array
	 */
	protected function removeFromUnmappedControls($value) {
		//if defaultMapping is in unmappedControls, remove it
		$key = array_search($value,$this->unmappedControls);
		if ($key >= 0) {
			unset($this->unmappedControls[$key]);
		}
	}
	
	/**
	 * Returns an array of all the controls that were not mapped to a tag name
	 */
	protected function setUnmappedControls() {
		global $db;
		$query = "SELECT cid,name FROM p".$this->pid."Control WHERE schemeid=".$this->sid;
		$sqlRestrictions = array();
		foreach ($this->controlMapping as $mappedControl) {
			array_push($sqlRestrictions, "name != '$mappedControl' ");
		}
		if (!empty($sqlRestrictions)) {
			$query .= " AND ".implode(' AND ',$sqlRestrictions);
		}
		$query .= " ORDER BY name";
		$result = $db->query($query);
		
		$this->unmappedControls[-1] = ' -- Ignore -- ';
		while ($data = $result->fetch_assoc()) {
			$this->unmappedControls[$data['cid']] = $data['name'];
		}
		
		// this removed to prevent "All File Controls" from showing up in 
		// the mapping table.  The "All File Controls" functionality breaks 
		// when there is more than one file/image control in a scheme.
//	    if ($this->uploadedFiles) {
//            array_push($this->unmappedControls,"All File Controls");
//        }
	}
	
	
	/***************************************************
	 * 			   START PUBLIC FUNTIONS			   *
	 ***************************************************/
	
	/**
	 * Create mapping table for user to deside how to map the tagnames to control names
	 */
	public function drawControlMappingTable($drawButtons = true) {
		//initilize data to create mapping table
		$this->setUnmappedControls();
		$disableContinue = "";
		

		 
		//start drawing mapping table
		print "Please match each XML tag name with the corresponding control in your scheme. <br/>";
//		print "<strong>File and image controls should map to \"All File Controls\"</strong>, not the actual control name.<br/><br/>";
		print "If the XML was exported from KORA, the \"id\" tag should be set to \"Ignore\".<br/><br/>";  
		
		//if there is a keyfield, print it out
		if (!empty($this->keyfield))
			print "Keyfield:".$this->keyfield."</br></br>";
		
		print '<table border=1>';
		print "<tr><td>XML Tag Name</td><td>Scheme Control Name</td><td></td></tr>";
		for ($i=0 ; $i<sizeof($this->existingTagnames) ; ++$i) {
			$tag = $this->existingTagnames[$i];
			print "<tr>";
			print '<td id="tagCell_'.$this->sid.'_'.$i.'" class="tagname">'.$tag.'</td><td id="controlCell_'.$this->sid.'_'.$i.'">';
			
			$defaultMapping = $this->checkPreviousMapping($tag);
			
			//print defaultMapping otherwise display select box with unmapped Controls
			if ($defaultMapping) {
				print $defaultMapping.'</td>';
				print '<td id="action_'.$this->sid.'_'.$i.'"><a onclick="MappingManager.setUnmappedControls(\''.implode("///",$this->unmappedControls).'\');MappingManager.removeMapping('.$this->sid.','.$i.');">Edit</a>';
			}
			else {
				//if a select box is needed for the control mapping, disable the continue button
				$disableContinue = 'disabled="true"';
				
				print '<select id="tagnameSelect_'.$this->sid.'_'.$i.'" name="'.$tag.'" class="tagnameSelect" onselect="alert(\'changed\');">';
				foreach ($this->unmappedControls as $control) {
					print '<option value="'.$control.'">'.$control.'</option>';
				}
				print '</select></td><td id="action_'.$this->sid.'_'.$i.'"><a onclick="MappingManager.addMapping('.$this->sid.','.$i.');">OK</a></td>';
				
			}
			//unset defaultMapping so it doesn't display for the next tag
			unset($defaultMapping);
			print "</td></tr>";
		}
		print "</table>";
		
		
		
		if ($drawButtons) {
			print '<br/><input type="button" id="continueButton" onclick="MappingManager.submit();" value="Import" '.$disableContinue.'/>';
			print '<input type="button" id="cancelButton" value="Cancel" onclick="cancelIngestion();" />';
			print '<img src="images/indicator.gif" id="indicator" alt="Loading..." style="border:none;display:none;"/>';
		}
		
		
	}
	
	/**
	 * Returns data from the XML file
	 */
	public function getRecordData() {
		// specific data should ALWAYS be first so that the specific 
		// data will override consistent data
		return $this->specificDataSet+$this->consistentDataSet;
	}
	
	/**
	 * Loads data from XML that is consistant across all records
	 */
	public function loadConsistentData($data) {
		if (!empty($data)) {
			foreach ($data->children() as $tagName=>$value) {
				$tagName = str_replace('_',' ',$tagName);
    			//  $value->attributes() returns a SimpleXMLElement object with one element, an array of attributes 
                foreach((array)$value->attributes() as $attributeArray){
                    $this->specificDataSet[$tagName]['_attributes']=$attributeArray;                        
                }
				
				$this->consistentDataSet[$tagName][] = $this->parseValues($value);
				$this->addTagName($tagName);
			}
		}
	}
	
	/**
	 * Load data from XML that is specific to a single record
	 */
	public function loadSpecificData($data) {
		$this->specificDataSet = array();
		if (!empty($data)) {
			foreach ($data->children() as $tagName=>$value) {
				$tagName = str_replace('_',' ',$tagName);
	       		//  $value->attributes() returns a SimpleXMLElement object with one element, an array of attributes 
                foreach((array)$value->attributes() as $attributeArray){
                    $this->specificDataSet[$tagName]['_attributes']=$attributeArray;                        
                }					
				$this->specificDataSet[$tagName][] = $this->parseValues($value);
				$this->addTagName($tagName);
			}
		}
	}
	
	/**
	 * Set keyfield
	 */
	public function setKeyfield($key)
	{
		$this->keyfield = $key;
	}
}
?>