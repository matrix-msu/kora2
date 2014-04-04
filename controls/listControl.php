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

require_once('control.php');

class ListControl extends Control {

	protected $name = "List Control";
	protected $ExistingData;
	protected $value;
	protected $options;
	
	public function ListControl($projectid='', $controlid='', $recordid='', $presetid='', $inPublicTable = false)
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
        if ($controlCheck->num_rows > 0) {
            $controlCheck = $controlCheck->fetch_assoc();
            $this->sid = $controlCheck['schemeid'];
            foreach(array('name', 'description', 'required', 'options') as $field) {
                $this->$field = $controlCheck[$field];
            }
            
            $this->options = simplexml_load_string($this->options);
        } else $this->pid = $this->cid = $this->rid = $this->cName = '';
        
	// If data exists for this control, get it
	
        if (isset($_SESSION['lastIngestion']))
        {
            $lastIngestion = unserialize($_SESSION['lastIngestion']);
        }
        else
        {
            $lastIngestion = array();
        }

        if (!empty($recordid)) {
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
		else if (!empty($presetid)) {
            $valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($presetid).' AND cid='.escape($controlid).' LIMIT 1');
            if ($valueCheck->num_rows > 0) {
                $this->ExistingData = true;
                $valueCheck = $valueCheck->fetch_assoc();
                // If this were a Multilist we'd have to use XML packing for the 'value' field,
                // but for a normal list there's no reason to.
                //$this->value = simplexml_load_string($valueCheck['value']);
                $this->value = $valueCheck['value'];
            }
        }
        else if (!empty($this->options->defaultValue))
        {
        	// Otherwise, this is an initial ingestion, so fill in the default
            $this->value = (string) $this->options->defaultValue;
        }
	}
	
	public function delete()
	{
	    global $db;
        
        if (!$this->isOK()) return;
        
        if (!empty($this->rid)) $deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1');
        else {
            $deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid));
            $publicDeleteCall = $db->query('DELETE FROM p'.$this->pid.'PublicData WHERE cid='.escape($this->cid));
        }
	}
	
	public function display()
	{
        if (!$this->isOK()) return;

        print '<div class="kora_control"><select name="'.$this->cName.'">';
        
        // default blank option
        echo '<option value=""';
        if (empty($this->value)) echo ' selected="selected"';
        echo '>&nbsp;</option>';
        
        // display the options, with the current value selected.
        foreach($this->options->option as $option) {
        	echo "<option value=\"$option\"";
        	if ($this->value == $option) echo ' selected="selected"';
        	echo ">$option</option>\n";
        }
        
        echo '</select></div>';
	}
    
	public function displayAutoFill($category) {
		
		print '<select name="af_'.$category.'" id="af_'.$category.'">';
       	       
        // display the options, with the current value selected.
        foreach($this->options->option as $option) {
        	echo "<option value=\"$option\">$option</option>\n";
        }
        
        echo '</select>';
	
	}
	
	public function displayXML() {
       if(!$this->isOK()) return;
       
       $xmlstring = '<list>'.xmlEscape($this->value).'</list>';

       return $xmlstring;
    }

    public function displayOptionsDialog()
    {
    	$controlPageURL = baseURI . 'controls/listControl.php';
?><!-- Javascript Code below for list add/remove/up/down buttons -->
<script type="text/javascript">
//<![CDATA[
    function removeOption() {
        var answer = confirm("<?php echo gettext('Really delete option?  This will delete all data about this option from any records which currently have selected it.')?>");
        var value = $('#listOptions').val();
        if (answer == true) {
        	$.post('<?php echo $controlPageURL?>', {action:'removeOption',source:'ListControl',cid:<?php echo $this->cid?>,option:value }, function(resp){$("#ajax").html(resp);}, 'html');
        }
    }
    function addOption() {
        var value = $('#newOption').val();
    	$.post('<?php echo $controlPageURL?>', {action:'addOption',source:'ListControl',cid:<?php echo $this->cid?>,label:value }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    function moveOption(vardirection) {
        var value = $('#listOptions').val();
    	$.post('<?php echo $controlPageURL?>', {action:'moveOption',source:'ListControl',cid:<?php echo $this->cid?>,option:value,direction:vardirection }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function usePreset(varpreset) {
        var answer = confirm('Really select preset?  This will delete all existing options and cannot be undone!');
        var value = $('#listPreset').val();
        if (answer == true) {
        	$.post('<?php echo $controlPageURL?>', {action:'usePreset',source:'ListControl',cid:<?php echo $this->cid?>,preset:value }, function(resp){$("#ajax").html(resp);}, 'html');
        }
    }
    
    function savePreset()
    {
        var newName = $('#presetName').val();
    	$.post('<?php echo $controlPageURL?>', {action:'savePreset',source:'ListControl',cid:<?php echo $this->cid?>,pid:<?php echo $this->pid?>,name:newName }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function updateDefaultValue()
    {
        var defaultValue = $('#defaultOption').val();
    	$.post('<?php echo $controlPageURL?>', {action:'updateDefaultValue',source:'ListControl',cid:<?php echo $this->cid?>,defaultV:defaultValue }, function(resp){$("#ajax").html(resp);}, 'html');
    }

	function selectAutoFillControl() {
		var selectBox = $('#autoFillSelect')[0];
		var control = selectBox.options[selectBox.selectedIndex];
    	$.post('<?php echo $controlPageURL?>', {action:'setAutoFillControl',cid:<?php echo $this->cid?>,autoFillCid:control.value,source:'ListControl' }, function(resp){$("#ajax").html(resp);}, 'html');
	}
    
    function addAutoFillRule() {
		params = {};
		
   		var idParts;
   		var values;
		for(var i=0 ; true ; ++i) {
			values = $('.af_param_val'+i);
			if (values.length <= 0) {
				break;
			}
			
			for (var j=0 ; j<values.length ; ++j) {
				idParts = values[j].id.split('_');
				if (idParts[idParts.length - 1] != 'val'+i) {
					params['val' + i + '[' + idParts[idParts.length - 1] + ']'] = values[j].value;
				} else {
					params['val'+i+'[]'] = values[j].value;
				}
			}
		}

		var op = $('#af_param_val_op').val();
		if (op) {
			params.op=op;
		}

		params.fillValue=$('#af_value').val();
		params.action='addAutoFillRule';
		params.source='ListControl';
		params.cid='<?php echo $this->cid?>';
		params.numOfRules=$('tr.autoFillRuleRow').length;
		$("#ajax").load("<?php echo $controlPageURL;?>", params);
    }

    function removeAutoFillRuleById(id) {
    	$.post('<?php echo $controlPageURL?>', {action:'removeAutoFillRuleById',source:'ListControl',cid:'<?php echo $this->cid?>',id:id }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
	$.post('<?php echo $controlPageURL?>', {action:'showDialog',source:'ListControl',cid:<?php echo $this->cid?> }, function(resp){$("#ajax").html(resp);}, 'html');
// ]]>
</script>

<div id="ajax"></div>

<?php
    }
	
	public function getName() { return $this->name; }
	
	public function getSearchString($submitData) {
    	if(isset($submitData[$this->cName]) && !empty($submitData[$this->cName]))
    		return array(array('=',"'".$submitData[$this->cName]."'"));
    	else
    		return false;
    }
	
    public function getType() { return "List"; }
	
    public function setXMLInputValue($value) {
    	$this->XMLInputValue = $value[0];
    }
    
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
        
        if (empty($this->rid)) {
            echo '<div class="error">'.gettext('No Record ID Specified').'.</div>';
            return;
        } else if (isset($this->XMLInputValue)){
        	$this->value = $this->XMLInputValue;
        } else if (!empty($_REQUEST) && isset($_REQUEST[$this->cName])) {
            $this->value = $_REQUEST[$this->cName];
        } else $this->value = '';
        
        // ingest the data
        $query = '';    // default blank query
        if ($this->ExistingData) {
            if ($this->isEmpty()) $query = 'DELETE FROM p'.$this->pid.$tableName.' WHERE id='.escape($this->rid).
                                           ' AND cid='.escape($this->cid).' LIMIT 1';
            else $query = 'UPDATE p'.$this->pid.$tableName.' SET value='.escape($this->value).
                 ' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1';
        } else {
            if (!$this->isEmpty()) $query = 'INSERT INTO p'.$this->pid.$tableName.' (id, cid, schemeid, value) VALUES ('.escape($this->rid).', '.escape($this->cid).', '.escape($this->sid).', '.escape($this->value).')';
        }
        
        if (!empty($query)) $db->query($query);
	}
	
    public static function initialOptions()
    {
        return '<options><defaultValue /></options>';
    }
	
	public function isEmpty()
	{
		return !( !empty($_REQUEST[$this->cName]) || isset($this->XMLInputValue));
	}

    public function isXMLPacked() { return false; }
	
    // When a list item is removed, all controls that currently have that item set
    // need to have that item removed.
    public function removeDeletedDataItem($pid, $cid, $dataItem)
    {
    	global $db;
    	
        // iterate through all records and remove references to value
        $query  = 'DELETE FROM p'.$pid.'Data WHERE cid='.escape($cid);
        $query .= ' AND value='.escape($dataItem);
        $db->query($query);
    }
	
	public function showData()
	{
		if (!empty($this->rid)) return htmlEscape($this->value);
	}
	
	public function storedValueToDisplay($xml,$pid,$cid)
	{
		return $xml;
	}
	
	public function storedValueToSearchResult($xml)
	{
		return $xml;
	}
	
	public function validateIngestion($publicIngest = false)
	{
		if (isset($this->XMLInputValue)) {
        	$value = $this->XMLInputValue;
        } else if (!empty($_REQUEST) && isset($_REQUEST[$this->cName])){
        	$value = $_REQUEST[$this->cName];
        }
        
		foreach ($this->options->option as $option) {
			$optionArray[] = (string) $option;
		}
		
		if (!$this->required) return '';
		if ($this->isEmpty()) {
		  return gettext('No value supplied for required field').': '.htmlEscape($this->name);
		} else if (isset($value) && !in_array((string) $value,$optionArray)) {
			return '"'.htmlEscape($value).gettext('" is not an valid value for '.$this->getName());
		}
		else
		  return '';
	}
}

// the ListControlOptions class encapsulates the functions used for the
// "edit options" page inside a single control to avoid global namespace
// pollution.
class ListControlOptions
{
// Show the list control editing dialog
	public static function showDialog($cid)
	{
	    global $db;
		
	    $xml = getControlOptions($cid);
	    if(!$xml) $xml = simplexml_load_string(ListControl::initialOptions());
	    
		?>
		<form id="listSettings">
				
		<table class="table">
		<tr>
		    <td width="40%"><b><?php echo gettext('List Options')?></b><br /><?php echo gettext('These are the choices users will be presented with when ingesting')?>.</td>
		    <td><select name="listOptions" id="listOptions" size="7">
				<?php
				// display all the modifiers
				foreach($xml->option as $option) {
					echo '<option>'.htmlEscape($option).'</option>'."\n";
				}
				?>
		        </select><br />
		        <input type="button" onclick="moveOption('up');" value="<?php echo gettext('Up')?>" />
		        <input type="button" onclick="moveOption('down');" value="<?php echo gettext('Down')?>" />
		        <input type="button" onclick="removeOption();" value="<?php echo gettext('Remove')?>" />
		        <br /><br /><input type="text" name="newOption" id="newOption" />
		        <input type="button" onclick="addOption();" value="<?php echo gettext('Add Option')?>" />
		    </td>
		</tr>
		<tr>
		    <td><b><?php echo gettext('Default Value')?></b><br /><?php echo gettext('Optionally, select a value which will be initially selected upon first ingestion')?>.</td>
		    <td><select name="defaultOption" id="defaultOption">
		        <option<?php if (empty($xml->defaultValue)) echo ' selected="selected"'; ?>>&nbsp;</option>
				<?php
		        // display all the modifiers
		        foreach($xml->option as $option) {
		            echo '<option';
		            if ($option == (string) $xml->defaultValue)
		            {
		            	echo ' selected="selected"';
		            }
		            echo '>'.htmlEscape($option).'</option>'."\n";
		        }
		        ?>
		        </select><br />
		        <input type="button" onclick="updateDefaultValue();" value="<?php echo gettext('Update')?>" />
		    </td>
		</tr>
		<tr>
		    <td><b><?php echo gettext('Presets')?></b><br /><?php echo gettext('Sets of pre-defined list values which are commonly used')?></td>
		    <td><select name="listPreset" id="listPreset">
		        <?php
			    // Get the list of List Control Presets
		        $presetQuery = $db->query('SELECT presetid, name FROM controlPreset WHERE class=\'ListControl\' AND (global=1 OR project='.$_SESSION['currentProject'].') ORDER BY name');
		        while($preset = $presetQuery->fetch_assoc())
		        {
		            echo "<option value=\"$preset[presetid]\">".htmlEscape($preset['name']).'</option>';
		        }
		        ?>
		    </select> <input type="button" onclick="usePreset()" value="<?php echo gettext('Use Preset')?>" /></td>
		</tr>
		<tr>
		    <td><b><?php echo gettext('Create New Preset')?></b><br /><?php echo gettext("If you would like to save this set of allowed file types as a preset, enter a name and click 'Save as Preset'")?>.</td>
		    <td><input type="text" name="presetName" id="presetName" /> <input type="button" onclick="savePreset()" value="<?php echo gettext('Save as Preset')?>" /></td>
		</tr>
		<tr>
			<td><b><?php echo  gettext('Set AutoFill')?></b><br/><?php echo gettext("Choose a control to auto select a value for this control when specific value is selected.")?></td>
			<td>
			<?php
			if (!isset($xml->autoFillBy)) {
				?>
				<select name="autoFillSelect" id="autoFillSelect" onchange="">
				<?php
				//get all schemes in project
				$schemeQuery = $db->query("SELECT cid,name FROM p".$_SESSION['currentProject']."Control WHERE schemeid=".$_SESSION['currentScheme']." AND cid!=".$_REQUEST['cid']." ORDER BY name");
				while( $scheme = $schemeQuery->fetch_assoc() ) {
					echo '<option value="'.$scheme['cid'].'">'.$scheme['name'].'</option>';
				}
				?>
				</select> <input type="button" onclick="selectAutoFillControl();" value="<?php echo gettext('Select Control')?>" />
				<div id="autoFillOptions"></div>
				<?php
			}
			else {
				$query = 'SELECT name,type FROM p'.$_SESSION['currentProject'].'Control WHERE schemeid='.$_SESSION['currentScheme'].' AND cid='.$xml->autoFillBy.' LIMIT 1';
				$query = $db->query($query);
				$fromData = $query->fetch_assoc();
				
				?><p>Auto Fill By: <?php echo $fromData['name']?></p>
				Rules: <br/>
				
				<?php
				$cTable = 'p'.$_SESSION['currentProject'].'Control';
		        $query = $db->query("SELECT $cTable.type AS class, control.file AS file FROM $cTable LEFT JOIN control ON ($cTable.type = control.class) WHERE cid=".escape($xml->autoFillBy).' LIMIT 1');
		        $query = $query->fetch_assoc();
		        require_once(basePath.CONTROL_DIR.$query['file']);
		        $controlClass = $query['class'];
				
				$fromControl = new $controlClass($_SESSION['currentProject'],$xml->autoFillBy);
				$toControl = new ListControl($_SESSION['currentProject'],$cid);
				
				//show existing rules
				$autoFillRules = $xml->autoFillRules;
				
				echo '<table>';
				foreach($autoFillRules->children() as $param) {
					$from = $param->from;
					$id = $param->attributes()->id;
					echo '<tr class="autoFillRuleRow" id="'.$id.'">';
					$paramValues = array();
					
					echo '<td>';
					if($fromData['type'] == 'DateControl') {
						$paramValues[] = $fromControl->formatDateForDisplay($from->val0->month,$from->val0->day,$from->val0->year,$from->val0->era,true,"MDY");
						$paramValues[] = $fromControl->formatDateForDisplay($from->val1->month,$from->val1->day,$from->val1->year,$from->val1->era,true,"MDY");
					} else {
						$paramValues[] = $from->op.' '.$from->value;
					}
					echo implode(' - ',$paramValues);
					echo '</td>';
					
					echo '<td>';
					echo $param->to;
					echo '</td>';
					
					echo '<td><a onclick="removeAutoFillRuleById('.$id.')">X</a></td>';
					echo '</tr>';
				}
					
				
				//show rule creator
				?>
				<table><tr><td>
				<?php echo $fromControl->displayAutoFill('param_val');?>
				</td><td>
				<?php echo $toControl->displayAutoFill('value');?>
				</td><td>
				<button type="button" onclick="addAutoFillRule();">Add</button>
				</td></tr></table>
				<?php
			}
			?>
			</td>
		</tr>
		</table>
		</form>
		<?php
	}
    // cid: The ID of the control (in the currently selected scheme and project)
    // option: The text for the new option
    public static function add($cid, $option)
	{
		// Remove starting/end whitespace since it causes headaches with how
		// HTML/Javascript parse the whitespace
		$option = trim($option);
		if (empty($option)) return;
		
        $xml = getControlOptions($cid);
        if(!$xml) return;

		// check to make sure this wouldn't be a duplicate option
		$duplicate = false;
		foreach($xml->option as $xmlOption) {
			if ($option == $xmlOption) $duplicate = true;
		}
		if (!$duplicate)
		{
    		// add the option
    		$xml->addChild('option', xmlEscape($option));
		
    		setControlOptions($cid, $xml);
		
		} else echo '<div class="error">'.gettext('You cannot have a duplicate list item').'</div>';
	}
	
	public static function addAutoFillRule($cid,$fillValue,$paramRules,$numOfRules) {
		global $db;
		
	
		//if the param operator is between and there are not 2 values, it
		//is bad input, and return without adding a autofill rule
		if ( $paramRules['op'] == 'between' && sizeof($paramRules) < 3 ) {
			return;
		}
		
		$xml = getControlOptions($cid);
        if(!$xml) return;
		
		if (isset($xml->autoFillBy)) {
			$query = 'SELECT type FROM p'.$_SESSION['currentProject'].'Control WHERE schemeid='.$_SESSION['currentScheme'].' AND cid='.$xml->autoFillBy.' LIMIT 1';
			$query = $db->query($query);
			$fromData = $query->fetch_assoc();
			
			$newXML = createAutoFillRule($xml,$fillValue,$paramRules,$fromData['type'],$numOfRules);
			
			setControlOptions($cid, $newXML);
		}
	}
	
	// Remove an option from the list
	// cid: The ID of the control (in the currently selected scheme and project)
	// option: The text for the option to be removed
	public static function remove($cid, $option)
	{
		global $db;
		
        $xml = getControlOptions($cid);
        if(!$xml) return;
		
        $n = 0;
        foreach($xml->option as $existingOption){
        	if($option == (string)$existingOption){
        		unset($xml->option[$n]);
        		break;
        	}
        	$n++;
        }
        
        setControlOptions($cid, $xml);
		
        // clean up existing records that have the deleted option.  This has to be done all pleasantly and OO
        // since derivatives of the list class can (and do) store their data differently.  Yes, this creates a
        // little more database and class instantiation overhead, but it's unavoidable if we want to properly clean
        // up after ourselves.
        $cTable = 'p'.$_SESSION['currentProject'].'Control';
        $query = $db->query("SELECT $cTable.type AS class, control.file AS file FROM $cTable LEFT JOIN control ON ($cTable.type = control.class) WHERE cid=".escape($cid).' LIMIT 1');
        $query = $query->fetch_assoc();
        require_once(basePath.CONTROL_DIR.$query['file']);
        $controlClass = $query['class'];
        $theControl = new $controlClass();
        $theControl->removeDeletedDataItem($_SESSION['currentProject'], $cid, $option);
    }
    
    function removeAutoFillRuleById($cid,$attId) {
    	$xml = getControlOptions($cid);
        if(!$xml) return;
        
        $newXML = simplexml_load_string('<?xml version="1.0"?><options>'.removeXMLByAttribute($xml,'id',$attId).'</options>');
        
        setControlOptions($cid, $newXML);
    }
	
	// Move a list option up or down in the display (and thus in the XML representation)
	// cid: The ID of the control (in the currently selected scheme and project)
	// option: The text for the option to be moved
	// direction: 'up' or 'down'
	public static function move($cid, $option, $direction)
	{
	    if ($direction != 'up' && $direction != 'down') {
            echo 'Improper Direction Specified.';
            return;
        }
        $xml = getControlOptions($cid);
        if(!$xml) return;

        // iterate through the list copying all non-list option options
        // move all the options to a PHP array which can be manipulated
        // then re-copy them to the end
        $newXML     = simplexml_load_string('<options></options>');
        $newOptions = array();
	    foreach($xml->children() as $childType => $childValue)
        {
            if ($childType != 'option') $newXML->addChild($childType, xmlEscape($childValue));
            else $newOptions[] = $childValue;
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
        	foreach($newOptions as $op) $newXML->addChild('option', xmlEscape($op));
        	
        	setControlOptions($cid, $newXML);
        }
	}

	public static function savePreset($cid, $pid, $name)
	{
	    global $db;
        
        // casting to integer (and then checking if it's 0 or below) sanitizes
        // the data and prevents malicious strings from being passed
        $cid = (int) $cid;
        $pid = (int) $pid;
        
        $freeNameQuery = $db->query('SELECT presetid FROM controlPreset WHERE class=\'ListControl\' AND name='.escape($name).' LIMIT 1');
        if ($freeNameQuery->num_rows > 0)
        {
            echo gettext('There is already a List Control preset with the name').': '.htmlEscape($name);
        }
        else if ($cid < 0 || $pid < 0)
        {
            echo gettext('Invalid Project or Control ID');
        }
        else
        {
            $xml = getControlOptions($cid);
            if(!$xml) return;
                
            $newXML = simplexml_load_string('<options />');
            if (isset($xml->option))
            {
                foreach($xml->option as $option)
                {
                    $newXML->addChild('option', xmlEscape((string) $option));
                }
            }
                
            $db->query('INSERT INTO controlPreset (name, class, project, global, value) VALUES ('.escape($name).", 'ListControl', $pid, 0, ".escape($newXML->asXML()).')');
        }
	}
	
	public static function usePreset($cid, $newPresetID)
	{
		global $db;
		
        $existenceQuery = $db->query('SELECT value FROM controlPreset WHERE class=\'ListControl\' AND presetid='.escape($newPresetID).' LIMIT 1');
        
        if ($existenceQuery->field_count > 0)
        {
            $existenceQuery = $existenceQuery->fetch_assoc();
            
            $query = 'UPDATE p'.$_SESSION['currentProject'].'Control SET options=';
            $query .= escape($existenceQuery['value']);
            $query .= ' WHERE cid='.escape($cid).' LIMIT 1';
            
            $db->query($query);
		}
	}
	
    public static function updateDefaultValue($cid, $default)
    {
        $xml = getControlOptions($cid);
        if(!$xml) return;
        
        $xml->defaultValue = xmlEscape($default);

        setControlOptions($cid, $xml);
        
        echo gettext('Default Value Updated').'.<br /><br />';
    }

	public static function setAutoFillControl($cid,$autoFillCid) {
		global $db;
		
		$xml = getControlOptions($cid);
        if(!$xml) return;
        
        $xml->addChild('autoFillBy',xmlEscape($autoFillCid));
        $xml->addChild('autoFillRules');
        
        setControlOptions($cid, $xml);
        
        //add autoFill option to $autoFillCid options to easily detect what to autofill
        $xml = getControlOptions($autoFillCid);
        if(!$autoFillCid) return;
        
        $xml->addChild('autoFill',xmlEscape($cid));
        
        setControlOptions($autoFillCid, $xml);
        echo gettext('Auto Fill Control Updated').'.<br /><br />';
	}
}

// Handle the AJAX Calls
if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'ListControl'){
	requirePermissions(EDIT_LAYOUT, isset($_SESSION['currentScheme']) ? 'schemeLayout.php?schemeid='.$_SESSION['currentScheme'] : 'selectScheme.php');
	
    $action = $_POST['action'];
    if($action == 'addOption') {
    	ListControlOptions::add($_POST['cid'], $_POST['label']);
    	ListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'removeOption') {
    	if (isset($_POST['option']))
        {
    	   ListControlOptions::remove($_POST['cid'], $_POST['option']);
        }
    	ListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'moveOption') {
    	if (isset($_POST['option']))
        {
    	   ListControlOptions::move($_POST['cid'], $_POST['option'], $_POST['direction']);
        }
    	ListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'usePreset') {
        ListControlOptions::usePreset($_POST['cid'], $_POST['preset']);
        ListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'savePreset') {
        ListControlOptions::savePreset($_POST['cid'], $_POST['pid'], $_POST['name']);
        ListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateDefaultValue') {
        ListControlOptions::updateDefaultValue($_POST['cid'], $_POST['defaultV']);
        ListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'showDialog') {
    	ListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'setAutoFillControl') {
    	ListControlOptions::setAutoFillControl($_POST['cid'],$_POST['autoFillCid']);
    	ListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'addAutoFillRule') {
    	$paramArr = array();
    	$i=0;
    	while (isset($_POST['val'.$i])) {
    		$paramArr['val'.$i] = $_POST['val'.$i];
    		++$i;
    	}
    	if (isset($_POST['op'])) {
    		$paramArr['op'] = $_POST['op'];
    		ListControlOptions::addAutoFillRule($_POST['cid'],$_POST['fillValue'],$paramArr,$_POST['numOfRules']);
    		ListControlOptions::showDialog($_POST['cid']);
    	}
    } else if ($action == 'removeAutoFillRuleById') {
    	ListControlOptions::removeAutoFillRuleById($_POST['cid'],$_POST['id']);
    	ListControlOptions::showDialog($_POST['cid']);
    }
}

?>
