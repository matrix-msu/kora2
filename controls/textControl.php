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

class TextControl extends Control {
    protected $name = "Text Control";
    protected $ExistingData;
    protected $value;
    
    public function TextControl($projectid='', $controlid='', $recordid='', $presetid='', $inPublicTable = false)
    {
        if (empty($projectid) || empty($controlid)) return;
        global $db;
        
        $this->pid = $projectid;
        $this->cid = $controlid;
        $this->rid = $recordid;
        $this->cName = 'p'.$projectid.'c'.$controlid;
        $this->value = '';
        $this->ExistingData = false;  // needed just in case data is existing AND blank
        
        $controlCheck = $db->query('SELECT schemeid, name, description, required, options FROM p'.$projectid.'Control WHERE cid='.escape($controlid).' LIMIT 1');
        if ($controlCheck->num_rows > 0)
        {
            $controlCheck = $controlCheck->fetch_assoc();
            $this->sid = $controlCheck['schemeid'];
            foreach(array('name', 'description', 'required', 'options') as $field) {
                $this->$field = $controlCheck[$field];
            }
            $this->options = simplexml_load_string($this->options);
        }
        else
        {
            $this->pid = $this->cid = $this->rid = $this->cName = '';
            $this->options = simplexml_load_string($this->initialOptions());
        }
        
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
                $this->ExistingData = true;
                $this->value = $lastIngestion[$this->cName];
	    	}
	    	else
	    	{
                $valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($recordid).' AND cid='.escape($controlid).' LIMIT 1');
                if ($valueCheck->num_rows > 0)
                {
                    $this->ExistingData = true;
                    $valueCheck = $valueCheck->fetch_assoc();
                    $this->value = $valueCheck['value'];
                }
	    	}
        }
        else if (isset($lastIngestion[$this->cName]) && !isset($lastIngestion['editRecord']))
        {
            // load value from session
            $this->value = $lastIngestion[$this->cName];
            $this->ExistingData = true;
        }
        else if (!empty($presetid))
        {
            $valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($presetid).' AND cid='.escape($controlid).' LIMIT 1');
            if ($valueCheck->num_rows > 0)
            {
                $this->ExistingData = true;
                $valueCheck = $valueCheck->fetch_assoc();
                $this->value = $valueCheck['value'];
            }
        }
        else if (!empty($this->options->defaultValue))
        {
            // Otherwise, this is an initial ingestion, so fill in the default
            $this->value = (string) $this->options->defaultValue;
        }
    }
    
    // Delete any existing data for the control
    public function delete()
    {
        global $db;
        
        if (!$this->isOK()) return;
        
        if (!empty($this->rid))
        {
        	$deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1');
        }
        else
        {
            $deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid));
            $publicDeleteCall = $db->query('DELETE FROM p'.$this->pid.'PublicData WHERE cid='.escape($this->cid));
        }
    }
    
    // Show the Text Control
    public function display($isSearchForm=false)
    {
        if (!$this->isOK()) return;

        echo '<div class="kora_control">';
//        echo '<table><tr><td>';
		if($isSearchForm){
	        echo '<input name="'.$this->cName.'" id="'.$this->cName.'" size="25"/>';
		}
		else{
	        if ((int) $this->options->rows < 2 && @(string)$this->options->textEditor != 'rich')
	        {
	            print '<input type="text" name="'.$this->cName.'" id="something" size="'.(int)$this->options->columns.'" value="'.htmlEscape($this->value).'"/>';
	        }
	        else
	        {
				//if ((string)$this->options->textEditor == 'rich' && !$isSearchForm){
					// we use include once because there may be multiple rich text controls on the page.
					// this file just has a script tag including the ckeditor js.
				//	include_once 'ckeditor/ckeditor_include.php';
				//}
	            echo '<textarea ';
				if ((string)$this->options->textEditor == 'rich') echo 'class="ckeditor" ';
				echo 'name="'.$this->cName.'" id="'.$this->cName.'" ';
	            echo 'rows="'.$this->options->rows.'" cols="'.$this->options->columns.'">'.$this->value.'</textarea>';
			}
        }
         echo '</div>';
//        echo '</td></tr></table>';
    }
    
    public function displayXML()
    {
      if(!$this->isOK()) return;
      $xml = '<text>'.xmlEscape($this->value).'</text>';
      return $xml;
    }
    
    public function displayOptionsDialog()
    {
        $controlPageURL = baseURI . 'controls/textControl.php';
?>
		<script type="text/javascript">
		//<![CDATA[
			function updateRegEx()
			{
	    		var regExValue = $('#regex').val();
	    		$.post('<?php echo $controlPageURL;?>', {action:'updateRegEx',source:'TextControl',cid:<?php echo $this->cid?>,regex:regExValue}, function(resp){$("#ajax").html(resp);}, 'html');
			}
	
			function updateDefaultValue()
			{
	    		var defaultValue = $('#defaultValue').val();
	    		$.post('<?php echo $controlPageURL;?>', {action:'updateDefaultValue',source:'TextControl',cid:<?php echo $this->cid?>,defaultV:defaultValue}, function(resp){$("#ajax").html(resp);}, 'html');
			}
	
			function updateSize()
			{
	    		var rowValue = $('#textareaRows').val();
	    		var colValue = $('#textareaCols').val();
			    $.post('<?php echo $controlPageURL;?>', {action:'updateSize',source:'TextControl',cid:<?php echo $this->cid?>,rows:rowValue,columns:colValue}, function(resp){$("#ajax").html(resp);}, 'html');
			}
	
			function usePreset()
			{
	    		var answer = confirm("<?php echo gettext('Really select preset?  This will delete any existing RegEx and cannot be undone!')?>");
	    		var value = $('#textPreset').val();
	    		if (answer == true)
	        	{
	        		$.post('<?php echo $controlPageURL;?>', {action:'usePreset',source:'TextControl',cid:<?php echo $this->cid?>,preset:value}, function(resp){$("#ajax").html(resp);}, 'html');
	    		}
			}
	
			function savePreset()
			{
	    		var regExValue = $('#regex').val();
	    		var newName = $('#presetName').val();
	    		$.post('<?php echo $controlPageURL;?>', {action:'savePreset',source:'TextControl',cid:<?php echo $this->cid?>,pid:<?php echo $_SESSION['currentProject']?> ,regex:regExValue, name:newName}, function(resp){$("#ajax").html(resp);}, 'html');
			}
	
			function updateEditor()
			{
				var textEditor = (document.getElementsByName('textEditor')[0].checked) ? 'plain':'rich';
				$.post('<?php echo $controlPageURL;?>', {action:'updateEditor',source:'TextControl',cid:<?php echo $this->cid?>,pid:<?php echo $_SESSION['currentProject']?> ,textEditor:textEditor}, function(resp){$("#ajax").html(resp);}, 'html');
			}
	
			$.post('<?php echo $controlPageURL;?>', {action:'showDialog',source:'TextControl',cid:<?php echo $this->cid?>}, function(resp){$("#ajax").html(resp);}, 'html');
		//]]>
		</script>

		<div id="ajax"></div>
<?php
    }
    
    // Get the name of the control
    public function getName() {return $this->name;}

    public function getType() { return "Text"; }
    
    public function getSearchString($submitData)
    {
    	if(isset($submitData[$this->cName]) && !empty($submitData[$this->cName]))
    	{
    		return array(array('LIKE',"'%".$submitData[$this->cName]."%'"));
    	}
    	else
    	{
    		return false;
    	}
    }
    
    public function setXMLInputValue($value)
    {
    	$this->XMLInputValue = $value[0];
    }
    
    // Parse the input passed to the formand insert it into the database
    public function ingest($publicIngest = false)
    {
        global $db;
        
        if (!$this->isOK()) return;
        
        //determine whether to insert into public ingestion table or not
        if($publicIngest)
       	{
       		$tableName = 'PublicData';
       	}
       	else $tableName = 'Data';
        
        if (empty($this->rid))
        {
            echo '<div class="error">'.gettext('No Record ID Specified').'.</div>';
            return;
        }
        else if (isset($this->XMLInputValue))
        {
        	$this->value = $this->XMLInputValue;
        }
        else if (!empty($_REQUEST) && isset($_REQUEST[$this->cName]))
        {
            $this->value = $_REQUEST[$this->cName];
        }
        else $this->value = '';
        
        // ingest the data
        $query = '';    // default blank query
        if ($this->ExistingData)
        {
            if ($this->isEmpty())
            {
            	$query = 'DELETE FROM p'.$this->pid.$tableName.' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1';
            }
            else
            {
            	$query = 'UPDATE p'.$this->pid.$tableName.' SET value='.escape($this->value).
                 ' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1';
            }
		}
		else
		{
            if (!$this->isEmpty())
            {
            	$query = 'INSERT INTO p'.$this->pid.$tableName.' (id, cid, schemeid, value) VALUES ('.escape($this->rid).', '.escape($this->cid).', '.escape($this->sid).', '.escape($this->value).')';
            }
		}
        
        if (!empty($query)) $db->query($query);
    }
    
    public static function initialOptions()
    {
        return '<options><regex></regex><rows>1</rows><columns>25</columns><defaultValue /><textEditor>plain</textEditor></options>';
    }
    
    // tells if the control has data
    public function isEmpty()
    {
    	return !( !empty($_REQUEST[$this->cName]) || isset($this->XMLInputValue));
    }
    
    public function isXMLPacked() { return false; }
    
    public function showData()
    {
        if (!empty($this->rid)) {
			if((string)$this->options->textEditor == 'rich'){
				include_once 'ckeditor/ckeditor_include.php';
				return $this->value;
			}
			return htmlEscape($this->value);
		}
    }

    public function storedValueToDisplay($xml,$pid,$cid)
    {
		global $db;
		$controlCheck = $db->query('SELECT options FROM p'.$pid.'Control WHERE cid='.escape($cid).' LIMIT 1');
        if ($controlCheck->num_rows > 0)
        {
            $controlCheck = $controlCheck->fetch_assoc();
            $this->options = simplexml_load_string($controlCheck['options']);
        }
        else
        {
            $this->options = simplexml_load_string($this->initialOptions());
        }
		if((string)$this->options->textEditor == 'rich')
		{
			include_once 'ckeditor/ckeditor_include.php';
			return $xml;
		}
        return htmlEscape($xml);
    }
    
    public function storedValueToSearchResult($xml)
    {
        return $xml;
    }
    
    // Check to see if the input is valid for ingestion.  The only invalid case is if the control is marked
    // required and no data is supplied.
    public function validateIngestion($publicIngest = false)
    {
        if ($this->required && ($this->isEmpty()))
        {
            return gettext('No value supplied for required field').': '.htmlEscape($this->name);
        }
        $pattern = (string) $this->options->regex;
        if (!empty($pattern))
        {
            if ($this->isEmpty() ||
              (!empty($_REQUEST) && isset($_REQUEST[$this->cName]) && preg_match($pattern, $_REQUEST[$this->cName])) ||
              (isset($this->XMLInputValue) && preg_match($pattern,$this->XMLInputValue)))
            {
                return '';
            }
            else
            {
                return gettext('Value supplied for field').': '.htmlEscape($this->name).' '.gettext('does not match the required pattern').'.';
            }
        }
        else
        {
            return '';
        }
    }
}

// the ListControlOptions class encapsulates the functions used for the
// "edit options" page inside a single control to avoid global namespace
// pollution.
class TextControlOptions
{
    public static function showDialog($cid)
    {
        global $db;
        
        // get the XML list of modifiers
        $query = 'SELECT options FROM p'.$_SESSION['currentProject'].'Control WHERE cid='.escape($cid).' LIMIT 1';
        $query = $db->query($query);
        if (!is_object($query) || $query->num_rows != 1)
        {
            echo gettext('Improper Control ID or Project ID Specified').'.';
            return;
        }
        $query = $query->fetch_assoc();
        
        $xml = simplexml_load_string($query['options']);
        ?>
		<form id="textSettings">
			<table class="table">
				<tr>
	    			<td><b><?php echo gettext('Size')?></b></td>
	    			<td>
		        		<table border="0">
		        			<tr>
		            			<td><?php echo gettext('Rows')?>:</td>
		            			<td><input type="text" name="textareaRows" id="textareaRows" value="<?php echo htmlEscape($xml->rows)?>" /></td>
		        			</tr>
		        			<tr>
		            			<td><?php echo gettext('Columns')?>:</td>
		            			<td><input type="text" name="textareaCols" id="textareaCols" value="<?php echo htmlEscape($xml->columns)?>" /></td>
		        			</tr>
		        			<tr>
		            			<td colspan="2"><input type="button" onclick="updateSize()" value="<?php echo gettext('Update')?>" /></td>
		        			</tr>
		        		</table>
	    			</td>
				</tr>
				<tr>
	    			<td><b><?php echo gettext('Regular Expression Match')?></b><br />(<?php echo gettext('Leave blank to allow any input')?>;<br />
	    				   <?php  printf('use %s otherwise', '<a href="http://perldoc.perl.org/perlretut.html">'.gettext('Perl-style RegEx').'</a>')?>)</td>
	    			<td><input type="text" name="regex" id="regex" value="<?php echo htmlEscape($xml->regex)?>" /><br />
	    				<input type="button" value="<?php echo gettext('Update')?>" onclick="updateRegEx()" />
	    			</td>
				</tr>
				<tr>
	    			<td width="60%"><b><?php echo gettext('Default Value')?></b><br />(<?php echo gettext('Leave blank to have no initial value')?>)</td>
	    			<td>
						<?php
						if ((int) $xml->rows < 2)
						{
						    echo '<input type="text" name="defaultValue" id="defaultValue" size="'.(int)$xml->columns.'" value="'.htmlEscape($xml->defaultValue).'"/>';
						}
						else
						{
						    echo '<textarea name="defaultValue" id="defaultValue" ';
						    echo 'rows="'.$xml->rows.'" cols="'.$xml->columns.'">'.$xml->defaultValue.'</textarea>';
						}
						
						  /*  <textarea rows="<?php echo $xml->rows?>" cols="<?php echo $xml->columns?>" name="defaultValue" id="defaultValue"><?php echo $xml->defaultValue?></textarea> <input type="button" value="Update" onclick="updateDefaultValue()" /></td> */
						?>
						<input type="button" value="<?php echo gettext('Update')?>" onclick="updateDefaultValue()" />
					</td>
				</tr>
				<tr>
	    			<td><b><?php echo gettext('Presets')?></b><br /><?php echo gettext('Pre-created Regular Expressions to match common patterns for your convieneince');?></td>
	    			<td><select name="textPreset" id="textPreset">
						<?php
						// Get the list of Text Control Presets
				        $presetQuery = $db->query('SELECT name, presetid FROM controlPreset WHERE class=\'TextControl\' AND (global=1 OR project='.$_SESSION['currentProject'].') ORDER BY name');
				        while($preset = $presetQuery->fetch_assoc())
				        {
				            echo "<option value=\"$preset[presetid]\">".htmlEscape($preset['name']).'</option>';
				        }
						?>
	    				</select> <input type="button" onclick="usePreset()" value="<?php echo gettext('Use Preset')?>" /><br />
	    			</td>
				</tr>
				<tr>
				    <td><b><?php echo gettext('Create New Preset')?></b><br /><?php echo gettext("If you would like to save this regular expression as a preset, enter a name and click 'Save as Preset'")?>.</td>
				    <td><input type="text" name="presetName" id="presetName" /> <input type="button" onclick="savePreset()" value="<?php echo gettext('Save as Preset')?>" /></td>
				</tr>
				<tr>
				    <td><b><?php echo gettext('Editor')?></b><br /><?php echo gettext("Choose the type of editor you would like to use with this control");?>.</td>
				    <td>
						<input type="radio" name="textEditor" value="plainEditor" <?php if(!empty($xml->textEditor) && $xml->textEditor != 'rich') echo 'checked="checked"';?>/> <label for="plainEditor"><?php echo gettext("Plain-text editor");?></label><br/>
						<input type="radio" name="textEditor" value="richTextEditor" <?php if(!empty($xml->textEditor) && $xml->textEditor == 'rich') echo 'checked="checked"';?>/> <label for="richTextEditor"><?php echo gettext("Rich-text editor");?></label><br/>
						<input type="button" onclick="updateEditor()" value="<?php echo gettext('Update')?>" /></td>
				</tr>
			</table>
		</form>
	<?php
    }

    public static function updateRegEx($cid, $regex) {
    	$xml = getControlOptions($cid,TextControl::initialOptions());
        
    	$xml->regex = xmlEscape($regex);
        
    	setControlOptions($cid, $xml);
        echo gettext('Regular Expression Updated').'.<br /><br />';
    }
    
    public static function updateDefaultValue($cid, $default)
    {
        $xml = getControlOptions($cid,TextControl::initialOptions());
        
        $xml->defaultValue = xmlEscape($default);
        
        setControlOptions($cid, $xml);
        echo gettext('Default Value Updated').'.<br /><br />';
    }

    public static function updateSize($cid, $rows, $columns)
    {
        $xml = getControlOptions($cid,TextControl::initialOptions());
        
        $newRows = (int) $rows;
        $newColumns = (int) $columns;
        if ($newRows > 0 && $newColumns > 0)
        {
            $xml->rows = $newRows;
            $xml->columns = $newColumns;
            
            setControlOptions($cid, $xml);
            echo gettext('Size Updated').'.<br /><br />';
        }
        else
        {
            echo gettext('The number of rows and columns must be a positive integer').'.';
        }
    }
    
    public static function usePreset($cid, $newPresetID)
    {
        global $db;
        
        $existenceQuery = $db->query('SELECT value FROM controlPreset WHERE class=\'TextControl\' AND presetid='.escape($newPresetID).' LIMIT 1');
        
        if ($existenceQuery->field_count > 0)
        {
            $existenceQuery = $existenceQuery->fetch_assoc();
            
            // get the XML list of modifiers
            $query = 'SELECT options FROM p'.$_SESSION['currentProject'].'Control WHERE cid='.escape($cid).' LIMIT 1';
            $query = $db->query($query);
            if (!is_object($query) || $query->num_rows != 1)
            {
                echo gettext('Improper Control ID or Project ID Specified').'.';
                return;
            }
            $query = $query->fetch_assoc();
        
            $xml = simplexml_load_string($query['options']);
            $xml->regex = $existenceQuery['value'];

            $query = 'UPDATE p'.$_SESSION['currentProject'].'Control SET options='.escape($xml->asXML());
            $query .= ' WHERE cid='.escape($cid).' LIMIT 1';
            $db->query($query);
        
            echo gettext('Preset Selected').'.<br /><br />';
           
            $db->query($query);
        }
    }

    public static function savePreset($cid, $pid, $name, $regex)
    {
        global $db;
        
        $dupeQuery = $db->query('SELECT name FROM controlPreset WHERE class=\'TextControl\' AND name='.escape($name).' LIMIT 1');
        
        if ($dupeQuery->num_rows > 0)
        {
            echo gettext('There is already a Text Control Preset with the name').': '.$name;
        }
        else
        {
            $db->query('INSERT INTO controlPreset(name, class, project, global, value) VALUES ('.escape($name).", 'TextControl', ".escape($pid).", 0, ".escape($regex).')');
        }
    }

	public static function updateEditor($cid, $pid, $textEditor)
	{
		$xml = getControlOptions($cid,TextControl::initialOptions());
        
        $xml->textEditor = xmlEscape($textEditor);

        setControlOptions($cid, $xml);
        if($textEditor == 'rich') echo gettext('Text Editor Updated.  Using Rich-Text Editor.').'<br /><br />';
		else echo gettext('Text Editor Updated.  Using Plain-Text Editor.').'<br /><br />';
	}
}

// Handle the AJAX Calls
if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'TextControl')
{
    $action = $_POST['action'];
    if($action == 'updateRegEx') {
        TextControlOptions::updateRegEx($_POST['cid'], $_POST['regex']);
        TextControlOptions::showDialog($_POST['cid']);
    } else if($action == 'updateDefaultValue') {
        TextControlOptions::updateDefaultValue($_POST['cid'], $_POST['defaultV']);
        TextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateSize') {
        TextControlOptions::updateSize($_POST['cid'], $_POST['rows'], $_POST['columns']);
        TextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'usePreset') {
        TextControlOptions::usePreset($_POST['cid'], $_POST['preset']);
        TextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'savePreset') {
        TextControlOptions::savePreset($_POST['cid'], $_POST['pid'], $_POST['name'], $_POST['regex']);
        TextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'showDialog') {
        TextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateEditor') {
        TextControlOptions::updateEditor($_POST['cid'], $_POST['pid'], $_POST['textEditor']);
		TextControlOptions::showDialog($_POST['cid']);
    }
}

?>
