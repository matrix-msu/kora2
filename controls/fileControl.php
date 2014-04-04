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
if (defined('basePath') && @$solr_enabled)
{
	require_once(basePath."includes/solrUtilities.php");
}

/**
 * @class FileControl
 * @extends Control
 */
class FileControl extends Control {
	protected $name = "File Control";
	protected $ExistingData;
	protected $value;
	
	/**
	 * Class to control a file in database and file directory.
	 * 
	 * @param int $projectid ID number of the project the file is in.
	 * @param int $controlid ID number of the control.
	 * @param int $recordid ID number of the file's record.
	 * @param int $presetid A record ID from which data will be copied if it is provided.
	 * @param boolean $inPublicTable If true, handles control in table 'PublicData'. If false, uses table 'Data'.
	 */
	public function FileControl($projectid='', $controlid='', $recordid='', $presetid='', $inPublicTable=false)
    {
    	if (empty($projectid) || empty($controlid)) return;
    	global $db;
    	
        $this->pid = $projectid;
        $this->cid = $controlid;
        $this->rid = $recordid;
        $this->cName = 'p'.$projectid.'c'.$controlid;
        $this->ExistingData = false;
        $this->inPublicTable = $inPublicTable;

        $controlCheck = $db->query('SELECT schemeid, name, description, required, options FROM p'.$projectid.'Control WHERE cid='.escape($controlid).' LIMIT 1');
        if ($controlCheck->num_rows > 0) {
            $controlCheck = $controlCheck->fetch_assoc();
            $this->sid = $controlCheck['schemeid'];
            foreach(array('name', 'description', 'required', 'options') as $field)
            {
                $this->$field = $controlCheck[$field];
            }
            $this->options = simplexml_load_string($this->options);
        }
        else
        {
            $this->pid = $this->cid = $this->rid = $this->cName = '';
        }
        if($this->inPublicTable) $tableName = "PublicData";
        else $tableName = "Data";
        
        // If data exists for this control, get it
        if (!empty($recordid))
        {
            $valueCheck = $db->query('SELECT value FROM p'.$projectid.$tableName.' WHERE id='.escape($recordid).' AND cid='.escape($controlid).' LIMIT 1');
            if ($valueCheck->num_rows > 0)
            {
                $this->ExistingData = true;
                $valueCheck = $valueCheck->fetch_assoc();
                $this->value = simplexml_load_string($valueCheck['value']);
            }
        }
        // If a preset ID is specified, store the fact that it was provided.  The data is
        // loaded, but it's important to store the preset so that it can be stored as a
        // hidden field in the ingestion form.
        if (!empty($presetid))
        {
            $valueCheck = $db->query('SELECT value FROM p'.$projectid.$tableName.' WHERE id='.escape($presetid).' AND cid='.escape($controlid).' LIMIT 1');
            if ($valueCheck->num_rows > 0)
            {
            	$this->ExistingData = true;
            	$valueCheck = $valueCheck->fetch_assoc();
            	$this->value = simplexml_load_string($valueCheck['value']);
            	$this->preset = $presetid;
            }
        }
    }
	
    /**
     * Delete the file's data from the database
     */
	public function delete() {
        global $db;
        
        if (!$this->isOK()) return;
        
        if (!empty($this->rid)) {
            // Get the information about the file and delete it
            $filePath = getFilenameFromRecordID($this->rid, $this->cid);
            if (file_exists($filePath))
            {
            	$quotaQuery = 'SELECT quota,currentsize FROM project WHERE pid = "'.$this->pid.'"';
				$results = $db->query($quotaQuery);
				$result = $results->fetch_assoc();
                $fileSize = ($this->value->size)/1024.0/1024;
                $sizeUpdate = 'UPDATE project SET currentsize='.($result['currentsize']-($fileSize)).' WHERE pid="'.$this->pid.'"';
    			$query = $db->query($sizeUpdate);
                unlink($filePath);
                // REMOVE FROM INDEX //
            	if (@$solr_enabled) deleteFromSolrIndexByRID($this->rid, $this->cid);
            }
            
            //remove item from fixity table
            if((string)$this->options->archival == 'Yes') removeFixityItem($this->rid,$this->cid);
            // Remove the record from the database
            $deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1');
        }
        else {
            // Remove all the files
            ///TODO: Add in fixity removal for control removal.
            $fileList = $db->query('SELECT id FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid));
            while($fileInfo = $fileList->fetch_assoc())
            {
                $filePath = getFilenameFromRecordID($fileInfo['id'], $this->cid);
                if (!empty($filePath) && file_exists($filePath))
                {
                	$quotaQuery = 'SELECT quota,currentsize FROM project WHERE pid = "'.$this->pid.'"';
					$results = $db->query($quotaQuery);
					$result = $results->fetch_assoc();
                	$fileSize = ($this->value->size)/1024.0/1024;
                	$sizeUpdate = 'UPDATE project SET currentsize='.($result['currentsize']-($fileSize)).' WHERE pid="'.$this->pid.'"';
    				$query = $db->query($sizeUpdate);
                    unlink($filePath);
                    // REMOVE FROM INDEX //
                	if (@$solr_enabled) deleteFromSolrIndexByRID($fileInfo['id'], $this->cid);
                }

            }
            
            // also do this for public table, but check if there is a public table first.
            $pubFileList = $db->query('SELECT id FROM p'.$this->pid.'PublicData WHERE cid='.escape($this->cid));
            if (!$db->error){
	            while($fileInfo = $pubFileList->fetch_assoc())
	            {
	                $filePath = publicGetFilenameFromRecordID($this->pid, $this->sid, $this->cid, $fileInfo['id']);
	                if (!empty($filePath) && file_exists($filePath)) unlink($filePath);
	            }
            }
            
            //kill the fixity information for these non-existant files.
            if((string)$this->options->archival == 'Yes') {
               $query = "DELETE FROM fixity WHERE kid LIKE '".dechex($this->pid)."-%' AND cid=".$this->cid;
               $db->query($query);
            }
        	
            // Remove all the records from the database
            $deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid));
            $publicDeleteCall = $db->query('DELETE FROM p'.$this->pid.'PublicData WHERE cid='.escape($this->cid));
        }
	}
	
    public function display() {
        global $db;
?>
<script type="text/javascript">
//<![CDATA[
function deleteFile<?php echo $this->cName?>()
{
	
    var answer = confirm("<?php echo gettext('Are you sure you want to delete this file?  This takes effect immediately and cannot be undone.')?>");

    if (answer)
    {
		$.post('<?php echo baseURI.'controls/fileControl.php'?>', {action:'deleteFile',source:'FileControl',kid:'<?php echo $this->rid?>',cid:'<?php echo $this->cid?>' }, function(resp){$("#existing<?php echo $this->cName?>").html(resp);}, 'html');
    }
}
//]]>
</script>
<script src="<?php echo baseURI?>javascripts/DragAndDrop/jquery.fileupload-ui.js"></script>
<script src="<?php echo baseURI?>javascripts/DragAndDrop/jquery.fileupload.js"></script>
<script src="<?php echo baseURI?>javascripts/DragAndDrop/jquery.iframe-transport.js"></script>
<script src="<?php echo baseURI?>javascripts/DragAndDrop/example/application.js"></script>
<script src="//ajax.aspnetcdn.com/ajax/jquery.templates/beta1/jquery.tmpl.min.js"></script>
<link rel="stylesheet" href="<? echo baseURI?>javascripts/DragAndDrop/jquery.fileupload-ui.css">
<?php
        //echo '<table><tr><td>';
		echo '<div class="kora_control">';
     	echo '<input type="hidden" name="preset'.$this->cName.'" id="preset'.$this->cName.'" value="'.$this->preset.'" />
       		 		<span><i>To upload a file, click below or drag from desktop to gray area <br/> (javascript must be enabled for drag-and-drop to work)</i></span>
        				<div>
          	    		<input type="file" name="'.$this->cName.'" id="'.$this->cName.'" class="filespace"></div>';
        if($this->ExistingData) {  // there is data associated with this control in this record
        	echo '<div id="existing'.$this->cName.'"><br />'.gettext('Existing File').': <a href="';
            if (empty($this->preset))
            {
                echo getURLFromRecordID($this->rid, $this->cid);
            }
            else
            {
            	echo getURLFromRecordID($this->preset, $this->cid);
            }
            echo '"><strong>'.$this->value->originalName.'</strong></a><br/><strong>'.gettext('Size').':</strong> '.$this->value->size;
            echo '<br /><br /><a class="link" onclick="deleteFile'.$this->cName.'();">'.gettext('Delete this File').'</a>';
            echo '</div>';
        }
        echo '</div>';
        //echo '</table>';
    }
    
    public function displayXML() {
       if (!$this->isOK()) return;
       
       $xmlString = '<file>';
       
       $xmlString .= '</file>';
       
       return $xmlString;
    }

    public function displayOptionsDialog()
   	{
        $controlPageURL = baseURI . 'controls/fileControl.php';
?><!-- Javascript Code below for list add/remove/up/down buttons -->


<script type="text/javascript">
//<![CDATA[
    function removeOption() {
        var answer = confirm("<?php echo gettext('Really delete option?  This will delete all data about this option from any records which currently have selected it.')?>");
        var value = $('#allowedTypes').val();
        if (answer == true) {
			$.post('<?php echo $controlPageURL?>', {action:'removeOption',source:'FileControl',cid:<?php echo $this->cid?>,option:value }, function(resp){$("#ajax").html(resp);}, 'html');
        }
    }
    
    function addOption() {
        var value = $('#newOption').val();
		$.post('<?php echo $controlPageURL?>', {action:'addOption',source:'FileControl',cid:<?php echo $this->cid?>,label:value }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function setArchival() {
		var value;
		$('input[type="radio"]').each(function(){
			if(this.name == 'archival' && this.checked){
				value = this.value;
			}
		});
		$.post('<?php echo $controlPageURL?>', {action:'setArchival',source:'FileControl',cid:<?php echo $this->cid?>, archive:value }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function moveOption(vardirection) {
        var value = $('#allowedTypes').val();
		$.post('<?php echo $controlPageURL?>', {action:'moveOption',source:'FileControl',cid:<?php echo $this->cid?>,option:value,direction:vardirection }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function usePreset(varpreset) {
        var answer = confirm("<?php echo gettext('Really select preset?  This will delete all existing options and cannot be undone!')?>");
        var value = $('#filePreset').val();
        if (answer == true) {
			$.post('<?php echo $controlPageURL?>', {action:'usePreset',source:'FileControl',cid:<?php echo $this->cid?>,preset:value }, function(resp){$("#ajax").html(resp);}, 'html');
        }
    }
    
    function updateFileSize()
    {
        var varSize = $('#fileSize').val();
		$.post('<?php echo $controlPageURL?>', {action:'updateFileSize',source:'FileControl',cid:<?php echo $this->cid?>,size:varSize}, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function updateFileRestrictions()
    {
		var varRestriction;
		$('input[type="radio"]').each(function(){
			if(this.name == 'restrictTypes' && this.checked){
				varRestriction = this.value;
			}
		});
		$.post('<?php echo $controlPageURL?>', {action:'updateFileRestrictions',source:'FileControl',cid:<?php echo $this->cid?>,restrictions:varRestriction }, function(resp){$("#ajax").html(resp);}, 'html');
        
    }
    
    function savePreset()
    {
        var newName = $('#presetName').val();
		$.post('<?php echo $controlPageURL?>', {action:'savePreset',source:'FileControl',cid:<?php echo $this->cid?>,pid:<?php echo $this->pid?>,name:newName }, function(resp){$("#ajax").html(resp);}, 'html');
    }

	$.post('<?php echo $controlPageURL?>', {action:'showDialog',source:'FileControl',cid:<?php echo $this->cid?> }, function(resp){$("#ajax").html(resp);}, 'html');
// ]]>
</script>

<div id="ajax"></div>

<?php
   	}
    
	public function getName() { return $this->name; }
	
	public function getSearchString($submitData) { return false; }
	
    public function getType() { return "File"; }
	
    public function setXMLInputValue($value) {
    	$this->XMLInputValue = extractFileDir.$value[0];
    	if (isset($value['_attributes'])){
    	   $this->XMLAttributes =$value['_attributes'];
    	}
    }
    
    /**
     * Writes a given file to the filesystem and specified database.
     * 
     * @param boolean $publicIngest If true, handles control in table 'PublicData'. If false, uses table 'Data'.
     */
    public function ingest($publicIngest = false) {
    	global $db;
        if (empty($this->rid)) {
            echo '<div class="error">'.gettext('No Record ID Specified').'.</div>';
            return;
        }
        //determine whether to insert into public ingestion table or not
        if($publicIngest)
       	{
       		$tableName = 'PublicData';
       	}
       	else $tableName = 'Data';
            if (isset($_REQUEST['preset'.$this->cName])&&!empty($_REQUEST['preset'.$this->cName]))
            {
            	$rid = parseRecordID($_REQUEST['preset'.$this->cName]);
            	
            	// if the rid is value, see if it constitutes a valid data object
            	if ($rid && $rid['project'] == $this->pid && $rid['scheme'] == $this->sid)
            	{
            		// If it contains a valid data object, copy its file and set our data
            		// to match the preset's data
            	    $valueCheck = $db->query('SELECT value FROM p'.$this->pid.$tableName.' WHERE id='.escape($rid['rid']).' AND cid='.escape($this->cid).' LIMIT 1');
		            if ($valueCheck->num_rows > 0)
		            {
		                $valueCheck = $valueCheck->fetch_assoc();
		                $this->value = simplexml_load_string($valueCheck['value']);

		                // Calculate the new name (without any absolute path) for storing
		                // in the database
		                $newFileName = $this->rid.'-'.$this->cid.'-'.(string) $this->value->originalName;
                        $this->value->localName = xmlEscape($newFileName);

                        // Store the new information in the database so that we can use
                        // getFilenameFromRecordID later
                        
                        if ($this->ExistingData)
                        {
                        	// Remove any old file if it exists
                        	$filePath = getFilenameFromRecordID($this->rid, $this->cid);
                        	if(file_exists($filePath)) unlink($filePath);
                        	$db->query('UPDATE p'.$this->pid.$tableName.' SET value='.escape($this->value->asXML().' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid)));
                        }
                        else
                        {
                            $db->query('INSERT INTO p'.$this->pid.$tableName.' (id, cid, schemeid, value) VALUES ('.escape($this->rid).', '.escape($this->cid).', '.escape($this->sid).', '.escape($this->value->asXML()).')');
                        }
		                
		                // oldFileName and newFileName are absolute paths
		                $oldFileName = getFilenameFromRecordID($rid['rid'], $this->cid);
                        $newFileName = getFilenameFromRecordID($this->rid, $this->cid);
		                if((string)$this->options->archival == 'Yes') {
                            addFixityItem($this->rid,$this->cid,$newFileName);
		                }
		                // copy the file
		                copy($oldFileName, $newFileName);
                        
                        // ADD TO INDEX //
		        		if (!$publicIngest && @$solr_enabled)
		       			{
		        			addToSolrIndexByRID($this->rid, $this->cid);
		        		}
		            }
            	}
            }
        elseif (!$this->isEmpty())
        {
            if ( (isset($_FILES[$this->cName]) && $_FILES[$this->cName]['error'] != UPLOAD_ERR_NO_FILE) || file_exists($this->XMLInputValue) )
            {
	        	// Is there an existing file?  If so, delete it
	        	if ($this->ExistingData)
	        	{
	        		$filePath = getFilenameFromRecordID($this->rid, $this->cid);
	        		if(file_exists($filePath)) unlink($filePath);
	        		if((string)$this->options->archival == 'Yes') removeFixityItem($this->rid,$this->cid);
	        	}
	        	
		        // make sure directory exists
            	if($publicIngest)
		        {
		        	//temporary storage for publically ingested files to be approved
		        	$fileDirectory = awaitingApprovalFileDir;
		        }
		        else
		        {
		        	//default file directory, ingested from within KORA
		        	$fileDirectory = fileDir;
		        }
		        		        
		        
		        $parentDir = basePath.$fileDirectory;
		        $fileDir = basePath.$fileDirectory.$this->pid.'/';
		        $oldumask = umask(0);
		        if (!is_dir($fileDir)) { 
		        	if (is_writable($parentDir))
		        		mkdir($fileDir, 02775);
		        	else {
		        		echo '<div class="error">'.gettext('File directory not writable').'.</div>';
		        		return;
		        	}
		        }
		        
		        $parentDir = $fileDir;
		        $fileDir .= $this->sid.'/';
            	if (!is_dir($fileDir)) { 
		        	if (is_writable($parentDir))
		        		mkdir($fileDir, 02775);
		        	else {
		        		echo '<div class="error">'.gettext('Project file directory not writable').'.</div>';
		        		return;
		        	}
		        }
		        umask($oldumask);
		        
		        // build the new filename
		        //$newName = $this->rid . '-' . $this->cid . '-' . $_FILES[$this->cName]['name'];
		        
		        // copy file over
		        if (!empty($_FILES[$this->cName])) {
		        	// & and ' are allowed in filenames on win7 but are not allowed in xml. remove them.
		        	$origName = str_replace(array('&',"'"), array('', ''), $_FILES[$this->cName]['name']);
		        	$type = $_FILES[$this->cName]['type'];
		        	
		        	$newName = $this->rid . '-' . $this->cid . '-' . $origName;
		        	$success = @move_uploaded_file($_FILES[$this->cName]['tmp_name'], $fileDir.$newName);
		        	if (!$success) {
		        		echo '<div class="error">'.gettext('Unable to upload file').'.</div>';
		        		return;
		        	}
		        }
		        else if (isset($this->XMLInputValue)) {
		        	$pathInfo = pathinfo($this->XMLInputValue);
		        	$origName = $pathInfo['basename'];
		        	if (isset($this->XMLAttributes)){
		        		$origName = $this->XMLAttributes['originalName'];
		        	}
                    $finfo = finfo_open(FILEINFO_MIME); // return mime type ala mimetype extension
                    $type = finfo_file($finfo, $this->XMLInputValue);
                    finfo_close($finfo);
		        		
		        	$newName = $this->rid . '-' . $this->cid . '-' . $origName;
					copy($this->XMLInputValue,$fileDir.$newName);
		        }
		        
		
		        // create XML record
		        $xml  = '<file><originalName>'.$origName.'</originalName>';
		        $xml .= '<localName>'.$newName.'</localName>';
		        $xml .= '<size>'.filesize($fileDir.$newName).'</size>';
		        $xml .= '<type>'.$type.'</type>';
		        $xml .= '</file>';
		        $this->value = simplexml_load_string($xml);
		        

		        // insert into the table
		        if ($this->ExistingData)
		        {
		        	$db->query('UPDATE p'.$this->pid.$tableName.' SET value='.escape($xml).' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid));
		        }
		        else
		        {
	                $db->query('INSERT INTO p'.$this->pid.$tableName.' (id, cid, schemeid, value) VALUES ('.escape($this->rid).', '.escape($this->cid).', '.escape($this->sid).', '.escape($xml).')');
		        }
		        if((string)$this->options->archival == 'Yes') {
                       addFixityItem($this->rid,$this->cid,$fileDir.$newName);
		        }
                
                // ADD TO INDEX //
		        if (!$publicIngest && @$solr_enabled)
		        {
		        	addToSolrIndexByRID($this->rid, $this->cid);
		        }
            }
        }
    }

    public static function initialOptions()
    {
        return '<options><maxSize>0</maxSize><restrictTypes>No</restrictTypes><allowedMIME></allowedMIME><archival>No</archival></options>';
    }
    
    public function isEmpty() {
    	//!( !empty($_REQUEST[$this->cName]) || isset($this->XMLInputValue))
    	return !( !((empty($_FILES[$this->cName]) || $_FILES[$this->cName]['error'] == 4) && !$this->ExistingData) || isset($this->XMLInputValue) );
    }

    public function isXMLPacked() { return true; }
    
    public function showData()
    {
    	if (!empty($this->rid) && is_object($this->value))
        {
            $returnString = '';
    	    if($this->inPublicTable) {
				$returnString .= "<div class='kc_file_name'>".gettext('Name').': <a href="'.publicGetURLFromRecordID($this->pid, $this->sid, $this->cid, $this->rid).'">'.$this->value->originalName.'</a></div>';
    	    }
    	    else {
				$returnString .= "<div class='kc_file_name'>".gettext('Name').': <a href="'.getURLFromRecordID($this->rid, $this->cid).'">'.$this->value->originalName.'</a></div>';
    	    }

    	    $returnString .= "<div class='kc_file_size'>".gettext('Size').': '.$this->value->size.' bytes</div>';
    	    $returnString .= "<div class='kc_file_type'>".gettext('Type').': '.$this->value->type.'</div>';
    	    return $returnString;
	}
    }

    public function storedValueToDisplay($xml,$pid,$cid)
    {
    	$xml = simplexml_load_string($xml);
    	
    	$returnVal = '';
    	if (isset($xml->originalName))
    	{
    	   $returnVal .= "<div class='kc_file_name'>".gettext('Name').': '.$xml->originalName.'</div>';;
    	}
    	if (isset($xml->size))
    	{
    		$returnVal .= "<div class='kc_file_size'>".gettext('Size').': '.$xml->size.' '.gettext('bytes').'</div>';
    	}
    	if (isset($xml->type))
    	{
    		$returnVal .= "<div class='kc_file_type'>".gettext('Type').': '.$xml->type.'</div>';
    	}
    	
        return $returnVal;
    }
    
    public function storedValueToSearchResult($xml)
    {
    	$xml = simplexml_load_string($xml);
    	
    	$returnVal = array();
    	$returnVal['originalName'] = (string) $xml->originalName;
    	$returnVal['localName'] = (string) $xml->localName;
    	$returnVal['size'] = (string) $xml->size;
    	$returnVal['type'] = (string) $xml->type;
    	
        return $returnVal;
    }
    
    public function validateIngestion($publicIngest = false) {
    	global $db;
    	$type = '';
    	$fileName = '';
    	$fileExists = false;
    	$fileSize = 0;
    	if (!empty($this->XMLInputValue)) {
    		// file ingesting through xml importer
            $finfo = finfo_open(FILEINFO_MIME); // return mime type ala mimetype extension
            $type = finfo_file($finfo, $this->XMLInputValue);
            finfo_close($finfo);
            
            // finfo returns strings like: image/jpeg; charset=binary
            // we just want the first part.
            $type = explode(";",$type);
            $type = $type[0];
            
			$fileExists = file_exists($this->XMLInputValue);
			if($fileExists) $fileSize = filesize($this->XMLInputValue);
			$fileName = $this->XMLInputValue;

		}else if(!empty($_FILES[$this->cName])){
			// file ingesting through web ingestion form
			$type = $_FILES[$this->cName]['type'];
			$fileExists = ($_FILES[$this->cName]['error']==UPLOAD_ERR_OK);
			$fileSize = (int)$_FILES[$this->cName]['size'];
			$fileName = $_FILES[$this->cName]['tmp_name'];
		}
    	
        // First, see if it's required and no value was supplied.
		if ($fileName == ''){
	        if ($this->required && !$this->ExistingData){
	        	return htmlEscape($this->name).': '.gettext('No value supplied for required field');
	        }else{
	        	// control will be empty/use existing value
	        	return '';
	        }
    	}
    	
    	// make sure the file is there
    	if(!$fileExists){
    		return htmlEscape($this->name).': '.gettext('File upload failed');
    	}
    	
    	// check to see if file upload directory is write-able
	if($publicIngest) {
		//temporary storage for publically ingested files to be approved
		$fileDirectory = awaitingApprovalFileDir;
	}
	else {
		//default file directory, ingested from within KORA
		$fileDirectory = fileDir;
	}

	$baseUploadDir = basePath.$fileDirectory;
	$projUploadDir = $baseUploadDir.$this->pid.'/';
	$schemeUploadDir = $projUploadDir.$this->sid.'/';

	$oldumask = umask(0);	
	// i guess if the final target upload dir exists, even if the baseUploadDir is not writable... pass this check
	if (!is_dir($schemeUploadDir) && !is_writable($baseUploadDir)) {
		return htmlEscape($this->name).': '.gettext('Global file upload directory not writable');
	}
	elseif (!is_dir($projUploadDir) && is_writable($baseUploadDir)) { mkdir($projUploadDir, 02775);	}
	
	// same.. if the final target upload dir exists, even if the schemeUploadDir is not writable... pass this check
	if (!is_dir($schemeUploadDir) && !is_writable($projUploadDir)) {
		return htmlEscape($this->name).': '.gettext('Project file upload directory not writable');
	}
	elseif (!is_dir($schemeUploadDir) && is_writable($projUploadDir)) { mkdir($schemeUploadDir, 02775); }

	// this one just check the final target, if it exists, but is not writable... fail
	if (is_dir($schemeUploadDir) && !is_writable($schemeUploadDir)) {
		return htmlEscape($this->name).': '.gettext('Scheme file upload directory not writable');
	}
	umask($oldumask);
	// done checking if directories are writable    	

    	// check the file type
	if ( (string)$this->options->restrictTypes == 'Yes' ){
		$allowedMIME = array();
		foreach($this->options->allowedMIME->mime as $mime){
			$allowedMIME[] = (string)$mime;
		}
            
		if (!in_array($type, $allowedMIME)){
			return htmlEscape($this->name).': '.gettext('Filetype is not in approved list').': '.$type;
		}
	}
    	
    	// make sure file is the right size
	if ( (int)$this->options->maxSize > 0 && $fileSize > (int)$this->options->maxSize * 1024 ){
        	return htmlEscape($this->name).': '.gettext('File too large').'.';
	}
		
	$quotaQuery = 'SELECT quota,currentsize FROM project WHERE pid = "'.$this->pid.'"';
	$results = $db->query($quotaQuery);
	$result = $results->fetch_assoc();
	if($result['quota']!=0)
	{
		if((($fileSize/1024.0/1024)+$result['currentsize'])>$result['quota'])
		{
			return gettext('Quota has been reached. File is too large');
		}
	}
    	$sizeUpdate = 'UPDATE project SET currentsize='.($fileSize/1024.0/1024+$result['currentsize']).' WHERE pid="'.$this->pid.'"';
    	$db->query($sizeUpdate);
    	// everything is ok
	return '';
    }
	
}

class FileControlOptions
{
    // Show the list control editing dialog
    public static function showDialog($cid)
    {
    	global $db;
        $xml = getControlOptions($cid);
    	if(!$xml) return;

    	$existenceQuery = $db->query('SELECT presetid FROM controlPreset WHERE class=\'FileControl\' AND project='.escape($_SESSION['currentProject']).' LIMIT 1');
        if ($existenceQuery->field_count > 0){
            $existenceQuery = $existenceQuery->fetch_assoc();
        }
        
    	?>
		<form id="fileSettings">
		<table class="table">
		<tr>
		    <td width="60%"><strong><?php echo gettext('Maximum File Size')?></strong><br /><?php echo gettext('The maximum size (in kB, 1024kB = 1MB) allowed to be uploaded by this control.  Set to 0 to have no limit.')?></td>
		    <td><input type="text" name="fileSize" id="fileSize" value="<?php echo (string) $xml->maxSize ?>" />
		        <input type="button" onclick="updateFileSize();" value="<?php echo gettext('Update')?>" /></td>
		</tr>
		<tr>
		    <td><strong><?php echo gettext('Allowed File Types')?></strong><br />
		    <?php  printf('Provide a list of %s that are allowed for ingestion into this control',"<a href=\"http://www.iana.org/assignments/media-types/\">MIME</a> filetypes (such as 'image/jpeg')");?>.</td>
		    <td><select name="allowedTypes" id="allowedTypes" size="5">
		<?php
			    foreach($xml->allowedMIME->mime as $mime)
			    {
			    	echo "<option value=\"$mime\">$mime</option>";
			    }
		?>
		    </select> <br />
		    <input type="button" onclick="moveOption('up');" value="<?php echo gettext('Up')?>" />
		    <input type="button" onclick="moveOption('down');" value="<?php echo gettext('Down')?>" />
		    <input type="button" onclick="removeOption();" value="<?php echo gettext('Remove')?>" />
		    <br /><br /><input type="text" name="newOption" id="newOption" />
		    <input type="button" onclick="addOption();" value="<?php echo gettext('Add Option')?>" /></td>
		</tr>
		<tr>
		    <td><strong><?php echo gettext('Restrict File Types?')?></strong><br /><?php echo gettext('If this is set to yes, only the filetypes set in the above list will be allowed')?></td>
		    <td><input type="radio" name="restrictTypes" id="restrictTypes" value="No" <?php  if ( (string)$xml->restrictTypes == 'No' ) echo 'checked'; ?> /><?php echo gettext('No')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		    <input type="radio" name="restrictTypes" id="restrictTypes" value="Yes" <?php  if ( (string)$xml->restrictTypes == 'Yes' ) echo 'checked'; ?> /><?php echo gettext('Yes')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		        <input type="button" value="Update" onclick="updateFileRestrictions()" /></td>
		</tr>
		<tr>
		    <td><strong><?php echo gettext('Archival Enabled?')?></strong><br /><?php echo gettext('This will enable fixity integrity checking on files ingested after this is enabled')?></td>
		    <td><input type="radio" name="archival" id="archival" value="No" <?php if ((string)$xml->archival == 'No') echo 'checked'; ?> /><?php echo gettext('No')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		        <input type="radio" name="archival" id="archival" value="Yes" <?php if ((string)$xml->archival == 'Yes') echo 'checked'; ?> /><?php echo gettext('Yes')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		        <input type="button" value="Update" onclick="setArchival()" /></td>
		</tr>
		<tr>
		    <td><strong><?php echo gettext('Presets')?></strong><br /><?php echo gettext('Sets of pre-defined file-types which are commonly used')?></td>
		    <td><select name="filePreset" id="filePreset">
		    	<option></option>
				<?php
				
		        // Get the list of File Control Presets
		        $presetQuery = $db->query('SELECT name, presetid FROM controlPreset WHERE class=\'FileControl\' AND (global=1 OR project='.$_SESSION['currentProject'].') ORDER BY name');
		        while($preset = $presetQuery->fetch_assoc())
		        {
		            echo "<option value=\"$preset[presetid]\">".htmlEscape($preset['name']).'</option>';
		        }
		        ?>
		        </select> <input type="button" onclick="usePreset()" value="<?php echo gettext('Use Preset')?>" />
		</tr>
		<tr>
		    <td><strong><?php echo gettext('Create New Preset')?></strong><br /><?php echo gettext("If you would like to save this set of allowed file types as a preset, enter a name and click 'Save as Preset'")?>.</td>
		    <td><input type="text" name="presetName" id="presetName" /> <input type="button" onclick="savePreset()" value="<?php echo gettext('Save as Preset')?>" /></td>
		</tr>
		</table>
		</form>
		   
		<?php
    }
    
    public static function add($cid, $option)
    {
    	$xml = getControlOptions($cid);
    	if(!$xml) return;
        
        // check to make sure this wouldn't be a duplicate option
        $duplicate = false;
        foreach($xml->allowedMIME->mime as $xmlOption) {
            if ($option == (string) $xmlOption) $duplicate = true;
        }
        if (!$duplicate)
        {
            // add the option
            $xml->allowedMIME->addChild('mime', xmlEscape($option));
        
            setControlOptions($cid, $xml);
        } else echo '<div class="error">'.gettext('You cannot have a duplicate list item').'</div>';
    }
    
    // Remove an option from the list
    // cid: The ID of the control (in the currently selected scheme and project)
    // option: The text for the option to be removed
    public static function remove($cid, $option)
    {
        $xml = getControlOptions($cid);
    	if(!$xml) return;
    	
    	$n = 0;
    	foreach($xml->allowedMIME->mime as $mime){
    		if($option == (string)$mime){
    			unset($xml->allowedMIME->mime[$n]);
    			break;
    		}
    		$n++;
    	}
        setControlOptions($cid, $xml);
    }
    
    // Move a list option up or down in the display (and thus in the XML representation)
    // cid: The ID of the control (in the currently selected scheme and project)
    // option: The text for the option to be moved
    // direction: 'up' or 'down'
    public static function move($cid, $option, $direction)
    {
        $xml = getControlOptions($cid);
    	if(!$xml) return;

        // iterate through the list copying all non-list option options
        // move all the options to a PHP array which can be manipulated
        // then re-copy them to the end
        $newXML     = simplexml_load_string('<options></options>');
        $newOptions = array();
        foreach($xml->children() as $childType => $childValue)
        {
            if ($childType != 'allowedMIME')
            {
                $newXML->addChild($childType, xmlEscape($childValue));
            }
            else
            {
                foreach($childValue as $mime)
                {
            	   $newOptions[] = (string) $mime;
                }
            }
        }
        
        // if the option is not found in the array, don't bother going through
        // the replacement loop and updating the database
        $key = array_search($option, $newOptions, false);
        if ($key !== false)
        {
            if ($direction == 'up')
            {
                // unless the key is already at the top, swap it with the one above
                if ($key > 0) {
                    $temp = $newOptions[$key - 1];
                    $newOptions[$key - 1] = $newOptions[$key];
                    $newOptions[$key] = $temp;
                }
            } else if ($direction == 'down')
            {
                // unless the key is already at the bottom, swap it with the one below
                if ($key < (sizeof($newOptions) - 1))
                {
                    $temp = $newOptions[$key + 1];
                    $newOptions[$key + 1] = $newOptions[$key];
                    $newOptions[$key] = $temp;
                }
            }
            
            // iterate through and add the options to the new XML
            $allowedMIME = $newXML->addChild('allowedMIME');
            foreach($newOptions as $op) $allowedMIME->addChild('mime', xmlEscape($op));
            
            // update the database
            
            setControlOptions($cid, $xml);
        }
    }

    public static function updateFileSize($cid, $size)
    {
        $size = (int) $size;
        
        if ($size < 0)
        {
            echo gettext('Invalid maximum size specified').'.';
        }
        else
        {
        	$xml = getControlOptions($cid);
            if(!$xml) return;
            
            $xml->maxSize = $size;
                
            setControlOptions($cid, $xml);
            echo gettext('Maximum File Size Updated').'.<br /><br />';
        }
    }

    public static function updateFileRestrictions($cid, $restrictions)
    {
    	global $db;
    	
    	if (!in_array($restrictions, array('No', 'Yes')))
    	{
    		echo gettext("Restrictions must be 'Yes' or 'No'").'.';
    	}
    	else
    	{
    	    $xml = getControlOptions($cid);
            if(!$xml) return;
            
            $xml->restrictTypes = $restrictions;
            
            setControlOptions($cid, $xml);
            echo gettext('File Restrictions Updated').'.<br /><br />';
    	}
    }
    
    public static function updateArchival($cid, $archival)
    {
        if (!in_array($archival, array('No', 'Yes')))
        {
            echo gettext("Archival must be 'Yes' or 'No'").'.';
        }
        else
        {
            $xml = getControlOptions($cid);
            if(!$xml) return;
               
            $xml->archival = $archival;
                
            setControlOptions($cid, $xml);
            echo gettext('Archival Settings Updated').'.<br /><br />';
        }
    }

    public static function usePreset($cid, $newPresetID)
    {
        global $db;
        
        $existenceQuery = $db->query('SELECT value FROM controlPreset WHERE class=\'FileControl\' AND presetid='.escape($newPresetID).' LIMIT 1');
        
        if ($existenceQuery->field_count > 0)
        {
            $existenceQuery = $existenceQuery->fetch_assoc();
            
            $xml = getControlOptions($cid);
            if(!$xml) return;
            
            $presetXML = simplexml_load_string($existenceQuery['value']);
            unset($xml->allowedMIME);
            
            $xml->addChild('allowedMIME');
            foreach($presetXML->children() as $childValue){
            	$xml->addChild('mime', xmlEscape((string) $childValue));
            }
            
            
            setControlOptions($cid, $xml);
            echo gettext('Preset Selected').'.<br /><br />';
        }
    }

    // Save a control's current list of allowed file types as a preset
    public static function savePreset($cid, $pid, $name)
    {
    	global $db;
    	
    	// casting to integer (and then checking if it's 0 or below) sanitizes
    	// the data and prevents malicious strings from being passed
    	$cid = (int) $cid;
    	$pid = (int) $pid;
    	
    	$freeNameQuery = $db->query('SELECT presetid FROM controlPreset WHERE class=\'FileControl\' AND name='.escape($name).' LIMIT 1');
    	if ($freeNameQuery->num_rows > 0)
    	{
    		echo gettext('There is already a File Control preset with the name').': '.htmlEscape($name);
    	}
    	else if ($cid < 0 || $pid < 0)
    	{
    		echo gettext('Invalid Project or Control ID');
    	}
        else
        {
            $xml = getControlOptions($cid);
            if(!$xml) return;
                
            $newXML = simplexml_load_string('<allowedMIME />');
            if (isset($xml->allowedMIME->mime))
            {
            	foreach($xml->allowedMIME->mime as $mime)
            	{
                    $newXML->addChild('mime', xmlEscape((string) $mime));
            	}
            }
                
            $db->query('INSERT INTO controlPreset (name, class, project, global, value) VALUES ('.escape($name).", 'FileControl', $pid, 0, ".escape($newXML->asXML()).')');
        }
    }

    // Delete a File.  This is technically used by ingestion, not options
    public static function deleteFile($kid, $cid)
    {
    	// Make sure that this is a legitamite kid
        $kidInfo = parseRecordID($kid);
        if (!$kidInfo ||
             $kidInfo['project'] != $_SESSION['currentProject'] ||
             $kidInfo['scheme']  != $_SESSION['currentScheme'])
        {
        	echo gettext('Invalid KID');
            return;
        }
        // at this point, we know kid is valid.  Ensure cid isn't an ingestion
        // attack by casting it as an integer
        $cid = (int) $cid;
        
        // Delete the file, delete the record from the DB.  That SHOULD
        // be all that's required.  If for some reason the delete file function
        // starts breaking ingestion, this would be a good first place to look.
        $filePath = getFilenameFromRecordID($kid, $cid);
        global $db;
        if(file_exists($filePath))
        {
            // REMOVE FROM INDEX //
        	if (@$solr_enabled) deleteFromSolrIndexByRID($kid, $cid);
            unlink($filePath);
        }
        $db->query('DELETE FROM p'.$kidInfo['project'].'Data WHERE id='.escape($kid).' AND cid='.escape($cid).' LIMIT 1');
        echo gettext('File Deleted');
    }
}

// Handle the AJAX Calls
if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'FileControl'){
    requirePermissions(EDIT_LAYOUT, 'schemeLayout.php?schemeid='.$_SESSION['currentScheme']);
    
    $action = $_POST['action'];
    if($action == 'addOption') {
        FileControlOptions::add($_POST['cid'], $_POST['label']);
        FileControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'removeOption') {
        FileControlOptions::remove($_POST['cid'], $_POST['option']);
        FileControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'moveOption') {
        FileControlOptions::move($_POST['cid'], $_POST['option'], $_POST['direction']);
        FileControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateFileSize') {
    	FileControlOptions::updateFileSize($_POST['cid'], $_POST['size']);
        FileControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateFileRestrictions') {
    	FileControlOptions::updateFileRestrictions($_POST['cid'], $_POST['restrictions']);
        FileControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'usePreset') {
    	FileControlOptions::usePreset($_POST['cid'], $_POST['preset']);
        FileControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'savePreset') {
    	FileControlOptions::savePreset($_POST['cid'], $_POST['pid'], $_POST['name']);
        FileControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'deleteFile') {
    	FileControlOptions::deleteFile($_POST['kid'], $_POST['cid']);
    } else if ($action == 'setArchival') {
       FileControlOptions::updateArchival($_POST['cid'], $_POST['archive']);
       FileControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'showDialog') {
        FileControlOptions::showDialog($_POST['cid']);
    }
}

?>
