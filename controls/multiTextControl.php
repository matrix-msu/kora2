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

require_once('textControl.php');

class MultiTextControl extends TextControl
{
	protected $name = 'Multi-Text Control';

    public function MultiTextControl($projectid='', $controlid='', $recordid='', $presetid='', $inPublicTable = false)
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

        if (!empty($recordid))
        {
            // See if there was a previous ingestion for this object
            if (isset($lastIngestion['editRecord']) && $lastIngestion['editRecord'] == $recordid && isset($lastIngestion[$this->cName]))
            {
                $this->value = simplexml_load_string('<multitext></multitext>');
                foreach($lastIngestion[$this->cName] as $selectedOption)
                {
                    $this->value->addChild('text', xmlEscape($selectedOption));
                }
                $this->ExistingData = true;
            }
            else
            {
                $valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($recordid).' AND cid='.escape($controlid).' LIMIT 1');
                if ($valueCheck->num_rows > 0)
                {
                    $this->ExistingData = true;
                    $valueCheck = $valueCheck->fetch_assoc();
                    $this->value = simplexml_load_string($valueCheck['value']);
                }
            }
        }
        else if (isset($lastIngestion[$this->cName]) && !isset($lastIngestion['editRecord']))
        {
            // load value from session
            $this->value = simplexml_load_string('<multitext></multitext>');
            foreach($lastIngestion[$this->cName] as $selectedOption)
            {
                $this->value->addChild('text', xmlEscape($selectedOption));
            }
            $this->ExistingData = true;
        }
        else if (!empty($presetid))
        {
            $valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($presetid).' AND cid='.escape($controlid).' LIMIT 1');
            if ($valueCheck->num_rows > 0)
            {
                $this->ExistingData = true;
                $valueCheck = $valueCheck->fetch_assoc();
                $this->value = simplexml_load_string($valueCheck['value']);
            }
        }
        else if (isset($this->options->defaultValue->value))
        {
            // Otherwise, this is an initial ingestion, so fill in the default
            $this->value = simplexml_load_string('<multitext />');
            foreach($this->options->defaultValue->value as $option)
            {
                $this->value->addChild('text', xmlEscape((string)$option));
            }
        }
    }

    public function display()
    {
    	global $db;
    	
    	?>
<script type="text/javascript">
//<![CDATA[
function addListOption<?php echo $this->cid?>()
{
    var optn = document.createElement("option");
    var input = document.getElementById("Input<?php echo $this->cid?>");
    var selectbox = document.getElementById("<?php echo $this->cName?>");
    optn.text = input.value;
    optn.value = input.value;
    selectbox.options.add(optn);
                
    input.value = "";
}

function removeListOption<?php echo $this->cid?>()
{
    var selectbox = document.getElementById("<?php echo $this->cName?>");
                
    for(i = selectbox.length - 1; i >= 0; i--)
    {
        if (selectbox.options[i].selected)
        {
            for(j = i; j < (selectbox.length - 1); j++)
            {
                selectbox.options[j].text = selectbox.options[j+1].text;
                selectbox.options[j].value = selectbox.options[j+1].value;
            }
                        
            selectbox.length--;
        }
    }
}

function moveListOptionUp<?php echo $this->cid?>()
{
    var selectbox = document.getElementById("<?php echo $this->cName?>");
    
    // Start at 1 rather than 0 so that we don't even bother dealing with the top case
    // If the desired element is at the top it won't be moved anyway
    for(i = 1; i < selectbox.length; i++)
    {
        if (selectbox.options[i].selected)
        {
            var tempText = selectbox.options[i-1].text;
            var tempValue = selectbox.options[i-1].value;
            
            selectbox.options[i-1].text = selectbox.options[i].text;
            selectbox.options[i-1].value = selectbox.options[i].value;
            selectbox.options[i].text = tempText;
            selectbox.options[i].value = tempValue;
        }
    }
}

function moveListOptionDown<?php echo $this->cid?>()
{
    var selectbox = document.getElementById("<?php echo $this->cName?>");
    
    // End at selectbox.length - 2 rather than -1 so that we don't even bother dealing
    // with the bottom case.  If the desired element is at the bottom it won't be moved anyway
    for(i = 0; i <= (selectbox.length - 2); i++)
    {
        if (selectbox.options[i].selected)
        {
            var tempText = selectbox.options[i+1].text;
            var tempValue = selectbox.options[i+1].value;
            
            selectbox.options[i+1].text = selectbox.options[i].text;
            selectbox.options[i+1].value = selectbox.options[i].value;
            selectbox.options[i].text = tempText;
            selectbox.options[i].value = tempValue;
        }
    }
}
//]]>
</script>

<table border="0">
<tr>
    <td colspan="2">
        <select id="<?php echo $this->cName?>" name="<?php echo $this->cName?>[]" multiple="multiple" size="5">
<?php       if (isset($this->value->text))
        {
            foreach($this->value->text as $text) {
                echo '            <option value="'.(string)$text.'">'.(string)$text."</option>\n";
            }
        }
?>
        </select>
    </td>
</tr>
<tr>
    <td><input type="button" onclick="moveListOptionUp<?php echo $this->cid?>()" value="<?php echo gettext('Up')?>" /></td>
    <td><input type="button" onclick="moveListOptionDown<?php echo $this->cid?>()" value="<?php echo gettext('Down')?>" /></td>
</tr>
<tr>
    <td colspan="2"><input type="text" name="Input<?php echo $this->cid?>" id="Input<?php echo $this->cid?>" value="" /></td>
</tr>
<tr>
    <td><input type="button" onclick="addListOption<?php echo $this->cid?>()" value="<?php echo gettext('Add')?>" /></td>
    <td><input type="button" onclick="removeListOption<?php echo $this->cid?>()" value="<?php echo gettext('Remove')?>" /></td>
</tr>
</table>
<?php
    }

    public function displayOptionsDialog()
    {
        $controlPageURL = baseURI . 'controls/multiTextControl.php';
?>
<script type="text/javascript">
//<![CDATA[
function updateRegEx()
{
    var regExValue = $('#regex').val();
    $.post('<?php echo $controlPageURL?>', {action:'updateMTRegEx',source:'MultiTextControl',cid:<?php echo $this->cid?>,regex:regExValue}, function(resp){$("#ajax").html(resp);}, 'html');
}

function moveDefaultValue(varDirection)
{
    var defaultValue = $('#defaultValue').val();
    $.post('<?php echo $controlPageURL?>', {action:'moveDefaultValue',source:'MultiTextControl',cid:<?php echo $this->cid?>,defaultV:defaultValue,direction:varDirection}, function(resp){$("#ajax").html(resp);}, 'html');
}

function removeDefaultValue()
{
    var defaultValue = $('#defaultValue').val();
    $.post('<?php echo $controlPageURL?>', {action:'removeDefaultValue',source:'MultiTextControl',cid:<?php echo $this->cid?>,defaultV:defaultValue}, function(resp){$("#ajax").html(resp);}, 'html');
    
}

function addDefaultValue()
{
    var defaultValue = $('#defVal').val();
    $.post('<?php echo $controlPageURL?>', {action:'addDefaultValue',source:'MultiTextControl',cid:<?php echo $this->cid?>,defaultV:defaultValue}, function(resp){$("#ajax").html(resp);}, 'html');
}

function updateSize()
{
    var rowValue = $('#textareaRows').val();
    var colValue = $('#textareaCols').val();

    $.post('<?php echo $controlPageURL?>', {action:'updateMTSize',source:'MultiTextControl',cid:<?php echo $this->cid?>,rows:rowValue,columns:colValue}, function(resp){$("#ajax").html(resp);}, 'html');
}

function usePreset() {
    var answer = confirm("<?php echo gettext('Really select preset?  This will delete any existing RegEx and cannot be undone!')?>");
    var value = $('#textPreset').val();
    if (answer == true) {
        $.post('<?php echo $controlPageURL?>', {action:'useMTPreset',source:'MultiTextControl',cid:<?php echo $this->cid?>,preset:value}, function(resp){$("#ajax").html(resp);}, 'html');
    }
}

function savePreset() {
    var regExValue = $('#regex').val();
    var newName = $('#presetName').val();
    $.post('<?php echo $controlPageURL?>', {action:'saveMTPreset',source:'MultiTextControl',cid:<?php echo $this->cid?>,pid:<?php echo $_SESSION['currentProject']?> ,regex:regExValue, name:newName}, function(resp){$("#ajax").html(resp);}, 'html');
}

$.post('<?php echo $controlPageURL?>', {action:'showMTDialog',source:'MultiTextControl',cid:<?php echo $this->cid?>}, function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>

<div id="ajax"></div>
<?php
    }
    
    public function displayXML()
    {
        if(!$this->isOK()) return;

        $xmlString = '<multitext>';
        
        foreach($this->value->text as $text)
        {
        	$xmlString .= '<text>'.xmlEscape( (string) $text).'</text>';
        }
        
        $xmlString .= '</multitext>';
        
        return $xmlString;
    }
    
    public function getType()
    {
    	return 'Text (Multi-Input)';
    }
    
    public function getSearchString($submitData) {
    	if (isset($submitData[$this->cName]) && !empty($submitData[$this->cName])) {
    		$values = array();
    		foreach($submitData[$this->cName] as $text) {
    			$values[] = array('LIKE',"'%<text>".$text."</text>%'");
    		}
    		return $values;
    	}
    	else
    		return false;
    }
    
    
    public function setXMLInputValue($value) {
    	$this->XMLInputValue = $value;
    }
    
    private function loadValues($textArray) {
    	$this->value = simplexml_load_string('<multitext></multitext>');
    	foreach($textArray as $selectedOption)
        {
            $this->value->addChild('text', xmlEscape($selectedOption));
        }
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
        } else if (isset($this->XMLInputValue)) {
        	$this->loadValues($this->XMLInputValue);
        }else if (!empty($_REQUEST) && isset($_REQUEST[$this->cName])) {
			$this->loadValues($_REQUEST[$this->cName]);
        } else {
        	$this->value = simplexml_load_string('<multitext></multitext>');
        }
        
        // ingest the data
        $query = '';    // default blank query
        if ($this->ExistingData) {
            if ($this->isEmpty()) $query = 'DELETE FROM p'.$this->pid.$tableName.' WHERE id='.escape($this->rid).
                                           ' AND cid='.escape($this->cid).' LIMIT 1';
            else $query = 'UPDATE p'.$this->pid.$tableName.' SET value='.escape($this->value->asXML()).
                 ' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1';
        } else {
            if (!$this->isEmpty()) $query = 'INSERT INTO p'.$this->pid.$tableName.' (id, cid, schemeid, value) VALUES ('.escape($this->rid).', '.escape($this->cid).', '.escape($this->sid).', '.escape($this->value->asXML()).')';
        }
        
        if (!empty($query)) $db->query($query);
    }
    
    public function isXMLPacked() { return true; }
    
    public function showData()
    {
    	if (!empty($this->rid) && isset($this->value->text))
    	{
    		$returnString = '';
    	    foreach($this->value->text as $text)
    	    {
    	        $returnString .= (string)$text . '<br />';
    	    }
    	    return $returnString;
    	}
    }
    
    public function storedValueToDisplay($xml,$pid,$cid)
    {
    	$xml = simplexml_load_string($xml);
    	
    	$returnVal = '';
    	
    	foreach($xml->text as $text)
    	{
    	   $returnVal .= (string)$text . '<br />';
    	}
    	
    	return $returnVal;
    }
    
    public function storedValueToSearchResult($xml)
    {
    	$xml = simplexml_load_string($xml);
    	
    	$returnVal = array();
    	
        foreach($xml->text as $text)
        {
           $returnVal[] = (string)$text;
        }
    	
    	return $returnVal;
    }
    
    public function validateIngestion($publicIngest = false)
    {
        if ($this->required && $this->isEmpty()){
            return gettext('No value supplied for required field').': '.htmlEscape($this->name);
        }

        if(!empty($_REQUEST[$this->cName])){
        	$texts = $_REQUEST[$this->cName];
        }else if (!empty($this->XMLInputValue)){
        	$texts = $this->XMLInputValue;
        }else return '';
        
        $pattern = (string) $this->options->regex;
        if (empty($pattern)) return '';
        
        $returnVal = '';
        foreach($texts as $text)
        {
        	if (!preg_match($pattern, $text))
        	{
        		$returnVal = gettext('Value supplied for field').': '.htmlEscape($this->name).' '.gettext('does not match the required pattern').'.';
        	}
        }
        return $returnVal;
    }
}

class MultiTextControlOptions
{
    public static function showDialog($cid)
    {
        global $db;
        
        $xml = getControlOptions($cid);
        if(!$xml) $xml = simplexml_load_string(MultiTextControl::initialOptions());

        ?>
		<form id="textSettings">
		
		<table class="table">
		<div class="kora_control">
		
		<tr>
		    <td><b><?php echo gettext('Regular Expression Match')?></b><br />(<?php echo gettext('Leave blank to allow any input')?>;<br />
		    <?php  printf(gettext('use %s  otherwise'), '<a href="http://perldoc.perl.org/perlretut.html">'.gettext('Perl-style RegEx').'</a>')?></td>
		    <td><input type="text" name="regex" id="regex" value="<?php echo htmlEscape($xml->regex)?>" />
		        <br /><input type="button" value="<?php echo gettext('Update')?>" onclick="updateRegEx()" />
		    </td>
		</tr>
		<tr>
		    <td width="60%"><b><?php echo gettext('Default Value')?></b><br />(<?php echo gettext('Leave blank to have no initial value')?>)</td>
		    <td>
				<table>
				<tr><td colspan="2">
					<select name="defaultValue" id="defaultValue" size="5">
			    	<?php
					if (isset($xml->defaultValue->value))
					{
					    $i = 1;
					    foreach($xml->defaultValue->value as $value)
					    {
					        echo '<option value="'.$i.'">'.htmlEscape((string)$value).'</option>';
					        $i++;
					    }
					}
					?>
					</select>
		    	</td></tr>
				<tr>
				    <td><input type="button" value="<?php echo gettext('Up')?>" onclick="moveDefaultValue('up')" /></td>
				    <td><input type="button" value="<?php echo gettext('Down')?>" onclick="moveDefaultValue('down')" /></td>
				</tr>
				<tr>
				    <td colspan="2"><input type="text" name="defVal" id="defVal" /></td>
				</tr>
				<tr>
				    <td><input type="button" value="<?php echo gettext('Add')?>" onclick="addDefaultValue()" /></td>
				    <td><input type="button" value="<?php echo gettext('Remove')?>" onclick="removeDefaultValue()" /></td>
				</tr>
			</table>
		</td></tr>
		<tr>
		    <td><b><?php echo gettext('Presets')?></b><br /><?php echo gettext('Pre-created Regular Expressions to match common patterns for your convieneince')?></td>
		    <td><select name="textPreset" id="textPreset">
				<?php
		        // Get the list of Text Control Presets
		        $presetQuery = $db->query('SELECT name, presetid FROM controlPreset WHERE class=\'TextControl\' AND (global=1 OR project='.$_SESSION['currentProject'].') ORDER BY name');
		        while($preset = $presetQuery->fetch_assoc())
		        {
		            echo "<option value=\"$preset[presetid]\">".htmlEscape($preset['name']).'</option>';
		        }
		        ?>
		    </select>
		    <input type="button" onclick="usePreset()" value="<?php echo gettext('Use Preset')?>" /><br /></td>
		</tr>
		<tr>
		    <td><b><?php echo gettext('Create New Preset')?></b><br /><?php echo gettext("If you would like to save this regular expression as a preset, enter a name and click 'Save as Preset'")?>.</td>
		    <td><input type="text" name="presetName" id="presetName" /> <input type="button" onclick="savePreset()" value="<?php echo gettext('Save as Preset')?>" /></td>
		</tr>
		</table>
		</form>
		<?php
    }

    public static function moveDefaultValue($cid, $default, $direction)
    {
        // Make sure the default to be moved is a number (corresponding to position in list)
        if (is_numeric($default))
        {
            $default = (int)$default;
        }
        else
        {
            return;
        }
        
        // Make sure the direction is valid
        if (!in_array($direction, array('up', 'down')))
        {
            return;
        }
        
        $xml = getControlOptions($cid);
        if(!$xml) return;
        
        $newXML = simplexml_load_string('<options />');
        
        // Ensure that the key is valid
        if (!isset($xml->defaultValue->value) || $default < 1 || $default > count($xml->defaultValue->value))
        {
            return;
        }

        // Otherwise, make sure this isn't a redundant move
        if (($direction == 'up' && $default == 1) || ($direction == 'down' && $default == count($xml->defaultValue->value) )           )
        {
            return;
        }
        
        // copy over fields other than the default Value
        foreach($xml->children() as $key => $value)
        {
            if ($key != 'defaultValue')
            {
                $newXML->addChild($key, (string)$value);
            }
        }
        $defVal = $newXML->addChild('defaultValue');
        // Copy the old defaults, minus the one to be removed
        $i = 1;
        $cache = '';
        foreach($xml->defaultValue->value as $v)
        {
            if ($direction == 'up')
            {
                if ($i == ($default - 1))
                {
                    $cache = (string)$v;
                }
                else if ($i == $default)
                {
                    $defVal->addChild('value', (string)$v);
                    $defVal->addChild('value', $cache);
                }
                else
                {
                    $defVal->addChild('value', (string)$v);
                }
            }
            else // if direction == 'down'
            {
                if ($i == $default)
                {
                    $cache = (string)$v;
                }
                else if ($i == ($default + 1))
                {
                    $defVal->addChild('value', (string)$v);
                    $defVal->addChild('value', $cache);
                }
                else
                {
                    $defVal->addChild('value', (string)$v);
                }
            }
            $i++;
        }
        
        setControlOptions($cid, $newXML);
    }
    
    public static function removeDefaultValue($cid, $default)
    {
        // Make sure the default to be moved is a number (corresponding to position in list)
        if (is_numeric($default))
        {
            $default = (int)$default;
        }
        else
        {
            return;
        }
        
        $xml = getControlOptions($cid);
        if(!$xml) return;
        
        $newXML = simplexml_load_string('<options />');
        
        // Ensure that the key is valid
        if (!isset($xml->defaultValue->value) || $default < 1 || $default > (count($xml->defaultValue->value) + 1))
        {
            return;
        }
        
        // copy over fields other than the default Value
        foreach($xml->children() as $key => $value)
        {
            if ($key != 'defaultValue')
            {
                $newXML->addChild($key, (string)$value);
            }
        }
        $defVal = $newXML->addChild('defaultValue');
        // Copy the old defaults, minus the one to be removed
        $i = 1;
        foreach($xml->defaultValue->value as $v)
        {
            if ($i != $default)
            {
                $defVal->addChild('value', (string)$v);
            }
            $i++;
        }
        
        setControlOptions($cid, $newXML);
    }
    
    public static function addDefaultValue($cid, $default)
    {
        $xml = getControlOptions($cid);
        if(!$xml) return;
        
        $xml->defaultValue->addChild('value', xmlEscape($default));
        
        setControlOptions($cid, $xml);
    }
}

if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'MultiTextControl')
{
    $action = $_POST['action'];
    if($action == 'showMTDialog') {
        MultiTextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'moveDefaultValue') {
        MultiTextControlOptions::moveDefaultValue($_POST['cid'], $_POST['defaultV'], $_POST['direction']);
        MultiTextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'removeDefaultValue') {
        MultiTextControlOptions::removeDefaultValue($_POST['cid'], $_POST['defaultV']);
        MultiTextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'addDefaultValue') {
        MultiTextControlOptions::addDefaultValue($_POST['cid'], $_POST['defaultV']);
        MultiTextControlOptions::showDialog($_POST['cid']);
    } else if($action == 'updateMTRegEx') {
        TextControlOptions::updateRegEx($_POST['cid'], $_POST['regex']);
        MultiTextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateMTSize') {
        TextControlOptions::updateSize($_POST['cid'], $_POST['rows'], $_POST['columns']);
        MultiTextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'useMTPreset') {
        TextControlOptions::usePreset($_POST['cid'], $_POST['preset']);
        MultiTextControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'saveMTPreset') {
        TextControlOptions::savePreset($_POST['cid'], $_POST['pid'], $_POST['name'], $_POST['regex']);
        MultiTextControlOptions::showDialog($_POST['cid']);
    }
}
?>
