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

require_once("fileControl.php");

class ImageControl extends FileControl
{
	protected $name = 'Image Control';
	public static $maxThumbWidth = 300;
	public static $maxThumbHeight = 300;
	
	public function delete()
	{
		// Delete the Thumb before calling FileControl::Delete because otherwise the
		// information will be lost
		@unlink($this->thumbPath($this->inPublicTable));
		
		FileControl::delete();
	}
	
    public function displayOptionsDialog()
    {
    	$controlPageURL = baseURI . 'controls/imageControl.php';
?>
<script type="text/javascript">
//<![CDATA[
    function updateThumbnailSize()
    {
        var varWidth = $('#thumbWidth').val();
        var varHeight = $('#thumbHeight').val();
    	$.post('<?php echo $controlPageURL;?>', {action:'updateThumbnailSize',source:'ImageControl',cid:<?php echo $this->cid?>,width:varWidth,height:varHeight }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function updateFileSize()
    {
        var varSize = $('#fileSize').val();
        $.post('<?php echo $controlPageURL;?>', {action:'updateFileSizeImage',source:'ImageControl',cid:<?php echo $this->cid?>,size:varSize }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
     function setArchival() {
		var value;
		$('input[type="radio"]').each(function(){
			if(this.name == 'archival' && this.checked){
				value = this.value;
			}
		});
        $.post('<?php echo $controlPageURL;?>', {action:'setArchivalImage',source:'ImageControl',cid:<?php echo $this->cid?>, archive:value }, function(resp){$("#ajax").html(resp);}, 'html');
    }
     $.post('<?php echo $controlPageURL;?>', {action:'showDialogImage',source:'ImageControl',cid:<?php echo $this->cid?> }, function(resp){$("#ajax").html(resp);}, 'html');
// ]]>
</script>

<div id="ajax"></div>
<?php
    }

    public function getType() { return "Image"; }

    public function setXMLInputValue($value) {
    	FileControl::setXMLInputValue($value);
    }
    
    public function ingest($publicIngest = false)
    {
    	// If there's existing data and a new file is uploaded, we must remove the thumbnail
    	// before calling FileControl::ingest because otherwise the information necessary to
    	// delete the file will be lost
    	if ($this->ExistingData && !empty($_FILES[$this->cName]) && $_FILES[$this->cName]['error'] != UPLOAD_ERR_NO_FILE)
    	{
    		@unlink($this->thumbPath($publicIngest));
    	}
    	
        FileControl::ingest($publicIngest);

        if (!FileControl::isEmpty())
        {
        	if ( (isset($_FILES[$this->cName]) && $_FILES[$this->cName]['error'] != UPLOAD_ERR_NO_FILE) || isset($this->value))
        	{
		        // Create the Thumbnail
		        
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

		        $parentDir = basePath.$fileDirectory.$this->pid.'/'.$this->sid;
		        $thumbDir = basePath.$fileDirectory.$this->pid.'/'.$this->sid.'/thumbs/';
		        if (!is_dir($thumbDir)) {
		        	if (is_writable($parentDir))
		        		mkdir($thumbDir, 02775);
		        	else {
		        		echo '<div class="error">'.gettext('Thumbnail directory not writable').'.</div>';
		        		return;
		        	}
		        }
		        
		        // I don't use getFilenameFromRecordID here because for some reason (database refresh
		        // stuff?) it pulls up the old localName.
		        $origPath = basePath.$fileDirectory.$this->pid.'/'.$this->sid.'/'.(string) $this->value->localName;
		        
		        createThumbnail($origPath, $this->thumbPath($publicIngest), (int) $this->options->thumbWidth, (int) $this->options->thumbHeight);
        	}
            else if (isset($_REQUEST['preset'.$this->cName]) && !empty($_REQUEST['preset'.$this->cName]))
            {
                createThumbnail(getFilenameFromRecordID($this->rid, $this->cid), $this->thumbPath($publicIngest), (int) $this->options->thumbWidth, (int) $this->options->thumbHeight);
            }
        }
    }
    
    public static function initialOptions()
    {
        return '<options><maxSize>0</maxSize><restrictTypes>Yes</restrictTypes><allowedMIME><mime>image/bmp</mime><mime>image/gif</mime><mime>image/jpeg</mime><mime>image/png</mime><mime>image/pjpeg</mime><mime>image/x-png</mime></allowedMIME><thumbWidth>125</thumbWidth><thumbHeight>125</thumbHeight><archival>No</archival></options>';
    }

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
            // Regenerate the thumbnail if necessary
            if (!file_exists($this->thumbPath($this->inPublicTable)))
            {
                createThumbnail(getFilenameFromRecordID($this->rid, $this->cid), $this->thumbPath($this->inPublicTable), (int) $this->options->thumbWidth, (int) $this->options->thumbHeight);
            }
            $returnString .= '<div class="kc_file_tn"><img src="'.$this->thumbURL($this->inPublicTable).'" /></div>';
            return $returnString;
        }
    }

    public function storedValueToDisplay($xml, $pid, $cid)
    {
        $xml = simplexml_load_string($xml);
        $pid = (int) $pid;
        if ($pid < 1) return gettext('Invalid PID');
        $cid = (int) $cid;
        if ($cid < 1) return gettext('Invalid Control ID');
        
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
        if (isset($xml->localName))
        {
            // Regenerate the thumbnail if necessary
            if (!file_exists(getThumbPathFromFileName($xml->localName)))
            {
            	// Load the options to get the maximum thumbnail
            	global $db;
            	$optionQuery = $db->query('SELECT options FROM p'.$pid.'Control WHERE cid='.$cid.' LIMIT 1');
            	if ($optionQuery->num_rows < 1)
            	{
            		return gettext('Invalid PID/CID');
            	}
            	$optionQuery = $optionQuery->fetch_assoc();
            	
            	$options = simplexml_load_string($optionQuery['options']);
            	
                createThumbnail(getFullPathFromFileName($xml->localName), getThumbPathFromFileName($xml->localName), (int) $options->thumbWidth, (int) $options->thumbHeight);
            }
            $returnVal .= '<div class="kc_file_tn"><img src="'.getThumbURLFromFileName((string)$xml->localName).'" /></div>';
        }
        
        return $returnVal;
    }
    
    public function validateIngestion($publicIngest = false) {
    	$fileError = FileControl::validateIngestion();
    	if ($fileError != '') return $fileError;
    	
    	// check if there was a file uploaded
    	$fileName = '';
    	if (!empty($this->XMLInputValue)){
    		$fileName = $this->XMLInputValue;
    	}else if (!empty($_FILES[$this->cName])){
    		$fileName = $_FILES[$this->cName]['tmp_name'];
    	}
    	// test that the uploaded file is actually an image
    	if($fileName!='' && getimagesize($fileName)===false){
    		return htmlEscape($this->name).': '.gettext('File is not an image');
    	}
    	
    	// Don't fail if there is no file uploaded.
    	// This is checked for in the File Control.
    	return '';
    }
    
    protected function thumbPath($publicIngest = false)
    {
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

        return basePath.$fileDirectory.$this->pid.'/'.$this->sid.'/thumbs/'
        .(string) $this->value->localName;
    }
    
    protected function thumbURL($publicIngest = false)
    {
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
    	
        return baseURI.$fileDirectory.$this->pid.'/'.$this->sid.'/thumbs/'.(string) $this->value->localName;
    }
}

class ImageControlOptions
{
	static function showDialog($cid)
	{
		global $db;
		
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		?>
<form id="imageSettings">
<table class="table">

<tr>
    <td width="60%"><b>Thumbnail Size</b><br />These settings control the maximum width/height of the image previews shown when displaying a record.  Please keep sizes between 1 and <?php echo ImageControl::$maxThumbWidth?> pixels.</td>
    <td>
<?php
            echo '<table border="0">';
            echo '<tr><td>'.gettext('Width').':</td><td><input type="text" name="thumbWidth" id="thumbWidth" value="'.(string) $xml->thumbWidth.'" /></td></tr>';
            echo '<tr><td>'.gettext('Height').':</td><td><input type="text" name="thumbHeight" id="thumbHeight" value="'.(string) $xml->thumbHeight.'" /></td></tr>';
            echo '</table>';
?>
            <input type="button" onclick="updateThumbnailSize();" value="<?php echo gettext('Update')?>" />
    </td>
</tr>

<tr>
    <td><b><?php echo gettext('Maximum File Size')?></b><br /><?php echo gettext('The maximum size (in kB, 1024kB = 1MB) allowed to be uploaded by this control.  Set to 0 to have no limit.')?></td>
    <td><input type="text" name="fileSize" id="fileSize" value="<?php echo (string) $xml->maxSize ?>" />
        <input type="button" onclick="updateFileSize();" value="<?php echo gettext('Update')?>" /></td>
</tr>
<tr>
    <td><b><?php echo gettext('Allowed File Types')?></b><br /><?php echo gettext('Note: This list is based on the image types which PHP can process and cannot be changed; it is provided here solely for reference.')?></td>
    <td>
        <select multiple="multiple" size="4">
            <option>image/bmp</option>
            <option>image/gif</option>
            <option>image/jpeg</option>
            <option>image/png</option>
         </select>
     </td>
</tr>
<tr>
    <td><b><?php echo gettext('Archival Enabled?')?></b><br /><?php echo gettext('This will enable fixity integrity checking on files ingested after this is enabled')?></td>
    <td><input type="radio" name="archival" id="archival" value="No" <?php if ((string)$xml->archival == 'No') echo 'checked'; ?> /><?php echo gettext('No')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <input type="radio" name="archival" id="archival" value="Yes" <?php if ((string)$xml->archival == 'Yes') echo 'checked'; ?> /><?php echo gettext('Yes')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <input type="button" value="Update" onclick="setArchival()" /></td>
</tr>
</table>
</form>
        <?php
	}

    static function updateThumbnailSize($cid, $width, $height, $publicIngest = false)
    {
    	global $db;
    	
        $width = (int) $width;
        $height = (int) $height;

        $validControlQuery = $db->query('SELECT cid FROM p'.$_SESSION['currentProject'].'Control WHERE cid='.escape($cid).' AND type="ImageControl" LIMIT 1');
        
        if ($validControlQuery->num_rows < 1)
        {
        	echo gettext('Invalid Control ID');
        }
        else if ($width < 1 || $height < 1)
        {
        	echo gettext('Width and Height must be Positive Integers');
        }
        else if ($width > ImageControl::$maxThumbWidth)
        {
        	echo gettext('Maximum Width').': '.ImageControl::$maxThumbWidth.' '.gettext('pixels').'.';
        }
        else if ($height > ImageControl::$maxThumbHeight)
        {
        	echo gettext('Maximum Height').': '.ImageControl::$maxThumbHeight.' '.gettext('pixels').'.';
        }
        else
        {
        	$xml = getControlOptions($cid);
        	if(!$xml) return;
		        
	        $xml->thumbWidth = $width;
	        $xml->thumbHeight = $height;
		        
	        setControlOptions($cid, $xml);
	        // Purge all existing thumbnails for this scheme in case they're the
	        // wrong size
	        
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
	        
	        $thumbDir = basePath.$fileDirectory.$_SESSION['currentProject'].'/'.$_SESSION['currentScheme'].'/thumbs/';
	        $fileHandle = '/^'.dechex($_SESSION['currentProject']).'-'.dechex($_SESSION['currentScheme']).'-/';
	        
	        if ($dir = opendir($thumbDir))
	        {
	        	while ($f = readdir($dir))
	        	{
	        		// If the image is from this scheme, delete it
	        		if (preg_match($fileHandle, $f))
	        		{
	        			unlink($thumbDir.$f);
	        		}
	        	}
	        }
	        else
	        {
	        	echo '<div class="error">'.gettext('Unable to open thumbnail directory').'</div><br />';
	        }
	        
	        echo gettext('Thumbnail Size Updated').'<br /><br />';

        }
    }
}

// Handle the AJAX Calls
if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'ImageControl'){
	requireScheme();
    requirePermissions(EDIT_LAYOUT, 'schemeLayout.php?schemeid='.$_SESSION['currentScheme']);
    
    $action = $_POST['action'];

    if($action == 'updateThumbnailSize') {
        ImageControlOptions::updateThumbnailSize($_POST['cid'], $_POST['width'], $_POST['height']);
        ImageControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateFileSizeImage') {
        FileControlOptions::updateFileSize($_POST['cid'], $_POST['size']);
    	ImageControlOptions::showDialog($_POST['cid'], $_POST['size']);
    } else if ($action == 'setArchivalImage') {
       FileControlOptions::updateArchival($_POST['cid'], $_POST['archive']);
       ImageControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'showDialogImage') {
        ImageControlOptions::showDialog($_POST['cid']);
    }
}
?>
