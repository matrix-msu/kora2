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

require_once('utilities.php');
require_once('clientUtilities.php');



// Make sure all the Control Classes are available.
$controlList = getControlList();
foreach($controlList as $c) { require_once(basePath.CONTROL_DIR.$c['file']); }

/**
 * This class encapsulates the creation, processing, and display of the
 * ingestion forms for KORA.  It pulls a list of controls and control
 * collections/pages, and displays the appropriate form.
 *
 * Constructor: ingestionForm(project id, scheme id, record id, preset record id)
 *nota
 */
class ingestionForm
{
    protected $projid;
    protected $schemeid;
    protected $recordid;     // if this is not null, the form is editing; if it is, it's submitting.
    protected $presetid;     // the id of another record in the scheme to copy the values from
    protected $newRecord;    // is this an ingest action on a new record?
    
    function isGood() { return ($this->projid != ''); }
    
    function ingestionForm($pid = '', $sid='', $rid='', $preset='', $newRec = false, $publicIngest = false)
    {
        global $db;

        $this->projid = (empty($pid)) ? $_SESSION['currentProject'] : $pid;
        $this->schemeid = (empty($sid)) ? $_SESSION['currentScheme'] : $sid;
        $this->recordid = $rid;
		$this->presetid = $preset;
		$this->newRecord = $newRec;

        // verify pid, schemeid, recordid, presetid are accurate and correspond to data within the system
        $test = $db->query("SELECT schemeName FROM scheme WHERE schemeid='".$this->schemeid."' AND pid='".$this->projid."' LIMIT 1");
        
        if ($test->num_rows > 0) {
            // Catch if a record ID was provided.  This has to correspond to a record from
            // the provided project/scheme, but does not have to correspond to an existing
            // object due to the way new object ingestion is handled.
            if (!empty($this->recordid)) {

            	if(!$publicIngest)
            	{
            		//check if rid is valid format
            		$rid = parseRecordID($this->recordid);
            		
            	 	if (!($rid && ($rid['project'] == $this->projid) && ($rid['scheme'] == $this->schemeid)))
            	 	{
            	 		// bad setup, so clear all variables to prevent use of this form
            	 		$this->projid = $this->schemeid = $this->recordid = $this->presetid = '';
               		}
            	}

            }
            // Catch if a Preset was provided.  The preset must point to a valid object
            if (!empty($this->presetid))
            {
                $test3 = $db->query('SELECT id FROM p'.$this->projid."Data WHERE schemeid='".$this->schemeid."' AND id='".$this->presetid."' LIMIT 1");
                if ($test3->num_rows == 0) {
                    // bad setup, so clear all variables to prevent use of this form
                    $this->projid = $this->schemeid = $this->recordid = $this->presetid = '';
                }
            }
        }
        else {
            // bad setup, so clear all variables to prevent use of this form
            $this->projid = $this->schemeid = $this->recordid = '';
        }

    }
    
 	function display($publicIngest = false)
    {
    	global $db;
        
        if (!$this->isGood()) {
            echo gettext('There was an error preparing the ingestion form').'.';

            return;
        }
        // get list of collections and controls.  Display them in <div> tags with ids corresponding
        // to the collection id.  Include Javascript scripts and buttons to jump between pages.  Call
        // the display() method on each control to have it show.
        
        // get a list of the collections in the scheme
        $collectionQuery = $db->query('SELECT collid, name, description, sequence FROM collection WHERE schemeid='.escape($this->schemeid).' ORDER BY sequence');
        $collectionList = array();
        while($coll = $collectionQuery->fetch_assoc())
        {
           $collectionList[$coll['collid']] = array('name' => $coll['name'], 'description' => $coll['description'], 'controls' => array() );
        }
        
        $cTable = 'p'.$this->projid.'Control';
        
        if(!$publicIngest)
        {
         	// get an ordered list of the controls in the project
        	$controlQuery =  "SELECT $cTable.name AS name, $cTable.cid AS cid, $cTable.collid AS collid, $cTable.sequence AS sequence, ";
        	$controlQuery .= "$cTable.description AS description, $cTable.type AS type, $cTable.publicEntry AS publicEntry, $cTable.required AS required ";
        	$controlQuery .= "FROM $cTable LEFT JOIN collection USING (collid) WHERE $cTable.schemeid=";
        	$controlQuery .= escape($this->schemeid)." ORDER BY collection.sequence, $cTable.sequence";
        }
        
        else
        {
        	// get an ordered list of the controls in the project that excludes the Associator Control for public ingestion
        	$controlQuery =  "SELECT $cTable.name AS name, $cTable.cid AS cid, $cTable.collid AS collid, $cTable.sequence AS sequence, ";
        	$controlQuery .= "$cTable.description AS description, $cTable.type AS type, $cTable.publicEntry AS publicEntry, $cTable.required AS required ";
        	$controlQuery .= "FROM $cTable LEFT JOIN collection USING (collid) WHERE $cTable.schemeid=";
        	$controlQuery .= escape($this->schemeid)." AND $cTable.type != 'AssociatorControl' ORDER BY collection.sequence, $cTable.sequence";
        }

        $controlQuery = $db->query($controlQuery);
        
        $controlList = array();
        while ($c = $controlQuery->fetch_assoc()) {
            	// add the control to the list in the appropriate collection
            	$collectionList[$c['collid']]['controls'][] = $c;
        }
        
        $i = 0;
        
        
        $collDivs = array();
        foreach($collectionList as $key=>$coll) {
            if (empty($coll['controls']) || $key == 0) continue;
            $i++;
            
            ob_start();     // begin output buffer
            
            echo '<div id="'.'id'.$i.'" class="controlCollection">'."\n";
            echo '<div><h3>'.htmlEscape($coll['name']).'</h3>';
            echo '<p id="thickboxDescrip">'.htmlEscape($coll['description']).'</p>'."<br/><br/>";
            
            // create a control instance
            foreach($coll['controls'] as $control)
            {
            	    echo '<div class="ctrlEdit"><strong>'.htmlEscape($control['name']).'</strong>';
            	    if ($control['required']) echo ' <font color="#FF0000">*</font> ';
            	    $c = new $control['type']($this->projid, $control['cid'], $this->recordid, $this->presetid);
            	    echo '<div id="inlineInput">';
            	    $c->display();
            	    echo '</div>';
            	    if ($control['description'] != '') { echo '<div class="ctrlDesc">'.htmlEscape($control['description']).'</div>'; }
            	    echo '</div>'."<br />";
            }
            
            echo '</div>';
            echo '</div>';
            $collDivs[$i] = ob_get_contents();
            ob_end_clean();         // flush the output buffer
        }

        // this is only for the text control, and is in its separate file
        // because it was previously included from the text control->display() function
        $phpServe = $_SERVER['PHP_SELF'];

        if (strpos($phpServe, 'addRecordToAssocControl.php')){
        	include_once '../ckeditor/ckeditor_include.php';
        }
        else {
        	include_once 'ckeditor/ckeditor_include.php';
        }
        ?>
        <style type="text/css">
        form > div {display:none;width:100%}
        #id1{display:inline;}
        #navNum2{display:inline;}
        </style>
	<script type="text/javascript" >
	//<![CDATA[
	var numPages = <?php echo $i?>;
	var currPage = 1;
	function setPage(p)
	{
		if (p < 0 || p > numPages) return;
		
		currPage = p;
		
		$.post('<?php echo baseURI?>includes/schemeFunctions.php', {action:'showNavigationLinks',source:'SchemeFunctions', nPages:numPages, cPage:currPage }, function(resp){$("#navNum1").html(resp);}, 'html');
		$.post('<?php echo baseURI?>includes/schemeFunctions.php', {action:'showNavigationLinks',source:'SchemeFunctions', nPages:numPages, cPage:currPage }, function(resp){$("#navNum2").html(resp);}, 'html');
		
		
		var i=1;
		for(i; i <= numPages; i++)
		{
			$('#id'+i.toString()).hide();
			//document.getElementById('id' + i.toString()).style.display = 'none';
		}
		$('#id'+p.toString()).show();
		//document.getElementById('id' + p.toString()).style.display = 'inline';
		//document.getElementById('id' + p.toString()).style.width = '100%';
		window.scrollTo(0,0);
	}
	
	function nextPage()
	{
		if (currPage < numPages) { setPage(currPage + 1); }
	}
	
	function prevPage()
	{
		if (currPage > 1) { setPage(currPage - 1); }
	}
	
	function submitForm()
	{
		
		// select all options in all associator controls
		var ref;
		<?php
		foreach($collectionList as $coll)
		{
			if (!empty($coll['controls']))
			{
				foreach($coll['controls'] as $control)
				{
					// Handle selection of multi-list-type objects.
					// Use Javascript to select all options to ensure they're all submitted
					if (in_array($control['type'], array('AssociatorControl', 'MultiTextControl', 'MultiDateControl')))
					{
						?>
						ref = document.getElementById('<?php echo 'p'.$this->projid.'c'.$control['cid'];?>');
						for(i=0; i<ref.options.length; i++) {
							ref.options[i].selected = "selected";
						}
						<?php
					}
				}
			}
		}
		?>
		
		if ($('#tb_closeBox').length != 0){
			$.post('ingestObject.php', $("#tbIngestionForm").serialize(), function(data){
					//$('#formRestyled').html(data);
					$("#tbIngestionForm").empty();
					var content = $( data ).find( '#right_container' );
					$( "#tb_closeBox" ).empty().append( content);
					$( "#tb_closeBox" ).append('<br/><a onClick="tb_remove()">Close</a>' );
			});
			
		}
		else {
			document.getElementById('ingestionForm').submit();
		}
	}
	
	// ONLOAD FOR FORM
	$(function() {
		setPage(1);
	});
	//]]>
	</script>
		<div id="navNum1" class="kora_navNumbers"></div>
		
		<?php if (strpos($phpServe, 'addRecordToAssocControl.php')) {?>
		        <form name="tbIngestionForm" id="tbIngestionForm" enctype="multipart/form-data" action="" method="post">
		<?php }else{?>
			<form name="ingestionForm" id ="ingestionForm" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
		<?php }?>
		<input type="hidden" name="ingestionForm" value="true" />
		<input type="hidden" name="projectid" value="<?php echo $this->projid?>" />
		<input type="hidden" name="schemeid" value="<?php echo $this->schemeid?>" />
		<input type="hidden" name="recordid" value="<?php echo $this->recordid?>" />
		 
		<?php foreach($collDivs as $c) echo $c."\n"; ?>
		<div id="navNum2" class="kora_navNumbers"></div> <?php
		
		//only want reCAPTCHA to display for external sites, don't ever want it to show up in internal KORA
		if($publicIngest)
		{
			?>
			<script type="text/javascript" src="http://api.recaptcha.net/challenge?k=6LdGJwAAAAAAAHpPVLBwS4Hdwy7DIicU48JsoaHR"></script>
			<noscript>
				<iframe src="http://api.recaptcha.net/noscript?k=6LdGJwAAAAAAAHpPVLBwS4Hdwy7DIicU48JsoaHR" height="300" width="500" frameborder="0"></iframe>
				<textarea name="recaptcha_challenge_field" rows="3" cols="40">
				</textarea>
				<input type="hidden" name="recaptcha_response_field" value="manual_challenge">
			</noscript>
			<?php
			echo recaptcha_get_html( PUBLIC_KEY );
		}
		
//        echo '<input id="pPage" type="button" value="Previous Page" onclick="prevPage();" />';
//        echo '<input id="nPage" type="button" value="Next Page" onclick="nextPage();" />';
//        echo '<br /><br /><input type="submit" value="Submit Data" />';
    	if (strpos($phpServe, 'addRecordToAssocControl.php')){
        	echo '<br /><br /><input type="button" value="'.gettext('Submit Data').'" onclick="submitForm();" />';
    	}
        else {
        	echo '<br /><br /><input type="button" value="'.gettext('Submit Data').'" onclick="submitForm();" />';
        }
        ?>
        </form>
		

		<?php
    }//end of display() function
    
    //This function is used to control what controls are shown in public ingestion
 	function publicIngestDisplay($publicIngest = false)
    {
    	global $db;
        
        if (!$this->isGood()) {
            echo gettext('There was an error preparing the ingestion form').'.';

            return;
        }
        // get list of collections and controls.  Display them in <div> tags with ids corresponding
        // to the collection id.  Include Javascript scripts and buttons to jump between pages.  Call
        // the display() method on each control to have it show.
        
        // get a list of the collections in the scheme
        $collectionQuery = $db->query('SELECT collid, name, description, sequence FROM collection WHERE schemeid='.escape($this->schemeid).' ORDER BY sequence');
        $collectionList = array();
        while($coll = $collectionQuery->fetch_assoc())
        {
           $collectionList[$coll['collid']] = array('name' => $coll['name'], 'description' => $coll['description'], 'controls' => array() );
        }
        
        $cTable = 'p'.$this->projid.'Control';
        
        if(!$publicIngest)
        {
         	// get an ordered list of the controls in the project
        	$controlQuery =  "SELECT $cTable.name AS name, $cTable.cid AS cid, $cTable.collid AS collid, $cTable.sequence AS sequence, ";
        	$controlQuery .= "$cTable.description AS description, $cTable.type AS type, $cTable.publicEntry AS publicEntry, $cTable.required AS required ";
        	$controlQuery .= "FROM $cTable LEFT JOIN collection USING (collid) WHERE $cTable.schemeid=";
        	$controlQuery .= escape($this->schemeid)." ORDER BY collection.sequence, $cTable.sequence";
        }
        
        else
        {
        	// get an ordered list of the controls in the project that excludes the Associator Control for public ingestion
        	$controlQuery =  "SELECT $cTable.name AS name, $cTable.cid AS cid, $cTable.collid AS collid, $cTable.sequence AS sequence, ";
        	$controlQuery .= "$cTable.description AS description, $cTable.type AS type, $cTable.publicEntry AS publicEntry, $cTable.required AS required ";
        	$controlQuery .= "FROM $cTable LEFT JOIN collection USING (collid) WHERE $cTable.schemeid=";
        	$controlQuery .= escape($this->schemeid)." AND $cTable.type != 'AssociatorControl' ORDER BY collection.sequence, $cTable.sequence";
        }

        $controlQuery = $db->query($controlQuery);
        
        $controlList = array();
        while ($c = $controlQuery->fetch_assoc()) {
            	// add the control to the list in the appropriate collection
				// only add the control if the publicEntry box is checked
  
           		if($c['publicEntry']!=0)
           		{
           			$collectionList[$c['collid']]['controls'][] = $c;
           		}
        }
        
        $i = 0;
        
        // build up the list of Control Collections first to populate the
        // navigation links
        
        $collDivs = array();
        foreach($collectionList as $key=>$coll) {
            if (empty($coll['controls']) || $key == 0) continue;
            $i++;
            
            ob_start();     // begin output buffer
            
            echo '<div id="'.'id'.$i.'" class="controlCollection">'."\n";
            echo '<table class="kora_ingest_table"><tr><th colspan="2">'.htmlEscape($coll['name']).'</th></tr>';
            echo '<tr><td colspan="2">'.htmlEscape($coll['description']).'</td></tr>'."\n";
            
            // create a control instance
            foreach($coll['controls'] as $control)
            {
            	
                echo '<tr><td class="kora_ccLeftCol"><strong>'.htmlEscape($control['name']).'</strong>';
                if ($control['required']) echo ' <font color="#FF0000">*</font> ';
                echo '</td><td class="kora_ccRightCol">'."\n";
                $c = new $control['type']($this->projid, $control['cid'], $this->recordid, $this->presetid);
                $c->display();
                echo htmlEscape($control['description']).'</td></tr>'."\n";
            }
            
            echo '</table>';
            echo '</div>';
            
            $collDivs[$i] = ob_get_contents();
            ob_end_clean();         // flush the output buffer
        }

        ?>
		<link rel="stylesheet" href="<?php echo baseURI."includes/thickbox/thickbox.css"?>" type="text/css" media="screen" />
		<script type="text/javascript" src="<?php echo baseURI."includes/thickbox/thickbox.js"?>"></script>
		<script type="text/javascript" >
		
		$(document).ready(function(){
			var formdata;
			$('#previewer').click(function(){
				var form = $("#ingestionForm");
				var newform = $(document.createElement("div"));
				
				//find all rows in the ingestion form
				form.find('.kora_ingest_table tr').each(function(){
					var row = this;
					//get the name of the control from the left column of the row
					var name = $(row).find('.kora_ccLeftCol').html();
					var right = $(row).find('.kora_ccRightCol');
					//search for 'kora control' in right column of row
					if($(right).find('.kora_control').size()!=0)
					{
						var control = $(right).find('.kora_control').children();
						if($(control).find('input').length!=0) control = $(control).find('input');
					}
					//if there isn't a 'kora control' it's a select
					else
					{
						var control = $(right).find('select');
					}
					var value;
					if($(control).is('select'))
					{
						//find out whether select is multi-select
						if($(control)[0].multiple)
						{
							//if there's only one entry field
							if($(control).length==1)
							{
								var multi="";
								//add all options that are selected to a string (in case it's a multi-select list)
								if(!($(right).find('tr').length>1))
								{
									$(control).find('option').each(function(){
										if($(this)[0].selected)
										{
											multi+=("<br>"+$(this).val());
										}
									});
								}
								else
								{
									//if it's not a multi-select list, add all options to the string
										$(control).find('option').each(function(){
											multi+=("<br>"+$(this).val());
										});
								}

								value = multi;
							}
							//more than one field means multi-input date
							else
							{
								var multi="";
								var selectCount=1;
								var options;
								//only the first input field matters, so gather those options
								$(control).each(function(){
									if(selectCount>1) var others = 1;
									else options = $(this).find('option');
									selectCount++;
								});
								//add each of those options to a string
								$(options).each(function(){
									multi+=("<br>"+$(this).html());
								});
								value = multi;
							}
						}
						else
						{
							//list controls are simple (yay!)
							if($(control).length==1)
							{
								value = $(control).val();
							}
							//must be a date control
							else
							{
								value = control;
								var selectVal = '';
								var dateCount = 1;
								value.each(function(){
								//print name of month instead of number
								if(dateCount==1)
									{
										if(this.value==1) selectVal+= 'January ';
									 	if(this.value==2) selectVal+= 'February ';
									 	if(this.value==3) selectVal+= 'March ';
									 	if(this.value==4) selectVal+= 'April ';
									 	if(this.value==5) selectVal+= 'May ';
									 	if(this.value==6) selectVal+= 'June ';
									 	if(this.value==7) selectVal+= 'July ';
									 	if(this.value==8) selectVal+= 'August ';
									 	if(this.value==9) selectVal+= 'September ';
									 	if(this.value==10) selectVal+= 'October ';
									 	if(this.value==11) selectVal+= 'November ';
									 	if(this.value==12) selectVal+= 'December ';
									}
									else if(dateCount==2)selectVal+=(this.value+" ");
									else
									{
										if(dateCount<4)selectVal+=this.value;
									}
									//keep track of what part of the date you're on
									dateCount++;
									});
									value = selectVal;
							}
						}
					}
					else
					{
						if($(control).is('input'))
						{
							
							if($(control).length>1)
							{
								value = $(control)[1].value;
								//get rid of leading path and print out file name
								value = value.substring(12,800);
							}
							//text inputs are easy
							else value = $(control)[0].value;
						}
						//don't output hidden fields
						else if($(control).is(':hidden')) return;
					}
					//create div element
					var element = $(document.createElement("div"));
					//if there's no name, the name shouldn't be output
					if(name==null)
					{
						if($(row).find('th').length!=0) element.html("<center><h3>"+$(row).html()+"</h3></center>");
						else element.html(value);
					}
					//otherwise output name and value
					else element.html(name+': '+value+'<br/><br/>');
					//add element to new form
					newform.append(element);
					formdata = newform.html();
					
				});
				//empty div where the form will be displayed, then add the new form to the div
				$('#cloned').empty();
				$('#cloned').append(newform);
		});
				
	});
		
		//<![CDATA[
		var numPages = <?php echo $i ?>;
		var currPage = 1;
		function setPage(p)
		{
		    if (p < 0 || p > numPages) return;
		    
		    currPage = p;

		    $.post('<?php echo baseURI?>includes/schemeFunctions.php', {action:'showNavigationLinks',source:'SchemeFunctions', nPages:numPages, cPage:currPage }, function(resp){$("#navNum1").html(resp);}, 'html');
			$.post('<?php echo baseURI?>includes/schemeFunctions.php', {action:'showNavigationLinks',source:'SchemeFunctions', nPages:numPages, cPage:currPage }, function(resp){$("#navNum2").html(resp);}, 'html');

		   
		    var i=1;
		    for(i; i <= numPages; i++)
		    {
		        document.getElementById('id' + i.toString()).style.display = 'none';
		    }
		    document.getElementById('id' + p.toString()).style.display = 'inline';
		    document.getElementById('id' + p.toString()).style.width = '100%';
		    window.scrollTo(0,0);
		}
		
		function nextPage()
		{
		    if (currPage < numPages) { setPage(currPage + 1); }
		}
		
		function prevPage()
		{
		    if (currPage > 1) { setPage(currPage - 1); }
		}
		
		function submitForm()
		{
		    // select all options in all associator controls
		    var ref;
			<?php
		    foreach($collectionList as $coll)
		    {
		        if (!empty($coll['controls']))
		        {
		           foreach($coll['controls'] as $control)
		           {
		                  // Handle selection of multi-list-type objects.
		                  // Use Javascript to select all options to ensure they're all submitted
		                  if (in_array($control['type'], array('AssociatorControl', 'MultiTextControl', 'MultiDateControl')))
		                  {
		                     echo "    ref = document.getElementById('".'p'.$this->projid.'c'.$control['cid']."');\n";
		                     echo "    for(i=0; i<ref.options.length; i++)\n    {\n";
		                     echo "        ref.options[i].selected = \"selected\";\n";
		                     echo "    }\n";
		                  }
		           }
		        }
		    }
			?>
		    document.ingestionForm.submit();
		}
		//]]>

		</script>
		<div id="navNum1" class="kora_navNumbers"></div>
		        
		<form  id="ingestionForm" name="ingestionForm" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
		<input type="hidden" name="ingestionForm" value="true" />
		<input type="hidden" name="projectid" value="<?php echo $this->projid?>" />
		<input type="hidden" name="schemeid" value="<?php echo $this->schemeid?>" />
		<input type="hidden" name="recordid" value="<?php echo $this->recordid?>" />
		 
		<?php foreach($collDivs as $c) echo $c."\n"; ?>
		<div id="navNum2" class="kora_navNumbers"></div>
		<a id = "previewer" href="#TB_inline?height=500&width=500&inlineId=previewbox" title="Preview" class="thickbox">Preview Entry</a>

		<?php
		//only want reCAPTCHA to display for external sites, don't ever want it to show up in internal KORA
		echo' <script type="text/javascript" src="http://api.recaptcha.net/challenge?k=6LdGJwAAAAAAAHpPVLBwS4Hdwy7DIicU48JsoaHR">
				</script>
				
				<noscript>
				 <iframe src="http://api.recaptcha.net/noscript?k=6LdGJwAAAAAAAHpPVLBwS4Hdwy7DIicU48JsoaHR"
				 height="300" width="500" frameborder="0"></iframe>
				
				 <textarea name="recaptcha_challenge_field" rows="3" cols="40">
				 </textarea>
				 <input type="hidden" name="recaptcha_response_field"
				 value="manual_challenge">
				</noscript>';
		
			echo recaptcha_get_html( PUBLIC_KEY );
		
       // echo '<input id="pPage" type="button" value="Previous Page" onclick="prevPage();" />';
       // echo '<input id="nPage" type="button" value="Next Page" onclick="nextPage();" />';
       // echo '<br /><br /><input type="submit" value="Submit Data" />';
        echo '<br /><br /><input type="button" value="'.gettext('Submit Data').'" onclick="submitForm();" />';
        ?>
        </form>
		


		<![CDATA[
		setPage(1);
		]]>
		</script>
		
		<!-- <div id="boxes">-->
		<div id="previewbox" style="display:none" class="window">
		<div id="cloned"></div>
		</div>

	<?php
    }//end of displayPublicIngest() function
    
    // Parse the data passed to a form and add or update the data in the system
    function ingest($overrideData=null, $publicIngest = false)
    {
        global $db;
        // update or insert the data into the database.  No idea how this is going to work yet.
        if (!$this->isGood()) {
            echo gettext('There was an error preparing the ingestion form').'.';
            return;
        }
        // get list of collections and controls.  Display them in <div> tags with ids corresponding
        // to the collection id.  Include Javascript scripts and buttons to jump between pages.  Call
        // the display() method on each control to have it show.

        
        $cTable = 'p'.$this->projid.'Control';
        
        // get an ordered list of the controls in the project
        $controlQuery =  "SELECT $cTable.name AS name, $cTable.cid AS cid, $cTable.collid AS collid, $cTable.sequence AS sequence, ";
        $controlQuery .= "$cTable.description AS description, $cTable.type AS type, $cTable.publicEntry AS publicEntry, $cTable.required AS required, ";
        $controlQuery .= "$cTable.showInResults AS privateResults, $cTable.showInPublicResults as publicResults, $cTable.searchable AS searchable ";
        $controlQuery .= "FROM $cTable LEFT JOIN collection USING (collid) WHERE $cTable.schemeid=";
        $controlQuery .= escape($this->schemeid)." ORDER BY collection.sequence, $cTable.sequence";

        $controlQuery = $db->query($controlQuery);

        // Iterate through the Control Results and Ingest Them
        
        $controlList = array();

        // create a control instance
        while($control = $controlQuery->fetch_assoc())
        {
        	$dataControl = new $control['type']($this->projid, $control['cid'], $this->recordid);
         	//if the override data was defined, set the XML value to the value in overrideData
            if (!empty($overrideData) && isset($overrideData[$control['name']])) {
            	$dataControl->setXMLInputValue($overrideData[$control['name']]);
            }
            $controlList[] = $dataControl;
        }
        $ingestionGood = true;
        $allEmpty = true;
        $errorList = array();
        
        foreach($controlList as $control) {
            // make sure the control is OK for ingestion - required fields have data, etc.
            $iVal = $control->validateIngestion($publicIngest);
            if (!empty($iVal)) {
               $ingestionGood = false;
               $errorList[] = $iVal;
            }
            $allEmpty = $allEmpty && $control->isEmpty();
        }
        
        if ($ingestionGood && !$allEmpty) {

           if($this->recordid) {  //should always be true
              $query = "SELECT * FROM dublinCore WHERE kid = '$this->recordid' LIMIT 1";
              $query_result = $db->query($query);
              $result =& $query_result;
              if($result->num_rows != 0 ) {  // record exists in dublinCore table, kill it so it can be inserted after edit.
                 $query = "DELETE FROM dublinCore WHERE kid = '$this->recordid' LIMIT 1";
                 $db->query($query);
              }
           }

           //if $_SESSION variable are defined, use them for DublinCore fields
           //otherwise use the class' scheme and project id
           if (isset($_SESSION['currentScheme']) && isset($_SESSION['currentProject'])) {
           		$currentScheme = $_SESSION['currentScheme'];
           		$currentProject = $_SESSION['currentProject'];
           } else {
           		$currentScheme = $this->schemeid;
           		$currentProject = $this->projid;
           }
           
           $dcfields = getDublinCoreFields($currentScheme,$currentProject);
           $dcarray = array(); //will be used to store xml objects for the DC fields.
           //$dcfieldarray = array();
           if (is_array($dcfields))
           {
               foreach($dcfields as $dcfield => $cids) {
                     if($cids) $dcarray[$dcfield] = simplexml_load_string("<$dcfield></$dcfield>");
                     
               }
           }
           foreach($controlList as $control) {
           		//Hack specifically for the timestamp 'object' ... add the current time to the request
           		//array under key cName
           		if($control->getName() == "systimestamp"){
           			if(!empty($overrideData) && isset($overrideData["systimestamp"])) {
           					$overrideData[$control->getName()][0] = date('c');
           			}
           			else {
           				$_REQUEST["p".$this->projid."c".$control->cid] = date('c');
           			}
           			//Also add this control to the dcfields array if there is at least 1 field in the array already
           			//so that timestamp gets added to any record in the dublin core table
           			if(is_array($dcfields) && !empty($dcfields)){
           				//print_r($dcfields);
           				$dcfields['timestamp']=array(); // LOOK AT HOW DUBLIN CORE DATA IS STORED ... index is likely not just 'systimestamp'
           				$dcfields['timestamp'][]=simplexml_load_string("<id>".$control->cid."</id>");
           				$dcarray['timestamp'] = simplexml_load_string("<timestamp></timestamp>");
           			}
           		}
           		
           		//Hack specifically for the recordowner 'object' ... add the current time to the request
           		//array under key cName
           		if($control->getName() == "recordowner")
           		{
           			//get the record owner
           			if($publicIngest)
           			{
           				$recordOwner = "public ingestion";
           			}
           			else
           			{
           				$currUser = $_SESSION['uid'];
           				$userquery = $db->query("SELECT username FROM user WHERE uid='$currUser'");
           				$userquery = $userquery->fetch_assoc();
           				$recordOwner = $userquery['username'];
           			}
           			
           			if(!empty($overrideData))
           			{
           				$overrideData[$control->getName()][0] = $recordOwner;
           			}
           			else
           			{
           				$_REQUEST["p".$this->projid."c".$control->cid] = $recordOwner;
           			}
           			//Dublin core stuff?
           			/*if(is_array($dcfields) && !empty($dcfields)){
           				//print_r($dcfields);
           				$dcfields['timestamp']=array(); // LOOK AT HOW DUBLIN CORE DATA IS STORED ... index is likely not just 'systimestamp'
           				$dcfields['timestamp'][]=simplexml_load_string("<id>".$control->cid."</id>");
           				$dcarray['timestamp'] = simplexml_load_string("<timestamp></timestamp>");
           			}*/
           		}
           		
           		$control->ingest($publicIngest);
               //add in dc on ingestion...
               if($dcfields) {
                  foreach($dcfields as $dcfield => $cids) {
                     foreach($cids as $cid) {
                        if($cid == $control->cid) {
                            $dcarray[$dcfield]->addChild($control->cid,$control->displayXML());
                        }
                    }
                  }
               }
           } // end foreach controlList as control
           // create the query and insert into the dublinCore table
           if($dcfields) {
               $query = "INSERT INTO dublinCore(kid,pid,sid,";
               $query .= implode(',',array_keys($dcarray)).") VALUES ('$this->recordid','$this->projid','$this->schemeid',";
               
               $xmlarray = array();
               foreach($dcarray as $dctype => $values) {
                  $xmlstring = simplexml_load_string("<$dctype></$dctype>");
                  foreach($values as $id => $value) {
                     $xmlstring->addChild($id,xmlEscape($value));
                  }
                  $xmlarray[] = escape($xmlstring->asXML());
               }
               $query .= implode(',',$xmlarray);
               $query .= ")";
//               print "<br /> .".htmlEscape($query)." <br />   ";
               $db->query($query);
               echo $db->error;
           }
           // remove any saved ingestion forms
           unset($_SESSION['lastIngestion']);
           if($publicIngest)
           {
           		echo gettext('Object Ingested and awaiting approval from a moderator.').'<br/><br/>';
           }
           else
           {
           		echo gettext('Object Ingested').'.<br/><br/>';
           		echo "<a href=\"".baseURI."viewObject.php?rid=$this->recordid\">".gettext('View Record')."</a><br/>";
           }
           
        }
        else {
            echo gettext('Ingestion Failed.  See below errors.').'<br />';
            foreach($errorList as $e) echo "<div class=\"error\">".gettext($e)."</div>";
            if ($allEmpty) echo '<div class="error">'.gettext('At least one control must have data').'.</div>';

            //if overrideData exists, it is an xml ingestion and reset the recordID
            if (isset($overrideData)) {
            	resetNextRecordId($this->schemeid);
            }
            //if just ingesting one object, store the record data in $_SESSION
            else {
	            // store the ingestion fields for the back button
	            $storedStuff = $_POST;
	            if (!empty($this->recordid) && !$this->newRecord)
	            {
	                $storedStuff['editRecord'] = $this->recordid;
	            }
		    	$_SESSION['lastIngestion'] = serialize($storedStuff);
            }
		}
    }
}

?>
