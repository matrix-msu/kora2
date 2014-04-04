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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * MappingManager is a JavaScript singleton class that handles the actions
 * for the mapping table when uploading an XML file to KORA
 * 
 * Initial Version: Meghan McNeil, 2009
 */

var MappingManager = new function() {
		
	//variables to be held in the class
	var instance = this;
	var unmappedControls;
	var selectedValue;
	
	//Ids of elements to be accessed
	var displayDivId = "xmlActionDisplay";
	var continueButtonId = "continueButton";
	var cancelButtonId = "cancelButton";
	var indicatorId = "indicator";
	
	//Ids that are row specific, a # will follow the '_'
	var controlId = "controlCell_";
	var controlSelectId = "tagnameSelect_";
	var tagnameId = "tagCell_";
	var actionId = "action_";
	
	//Classes of elements to be accessed
	var selectBoxClass = "tagnameSelect";
	
	//Control types that have special associated functionality
	var assocControl = "AssociatorControl";
	
	/**
	 * Adds control to the selection boxes
	 * @param control
	 *		control name to be added
	 */
	function addControl() {
		//add control name to list
		unmappedControls.push(selectedValue);
		unmappedControls.sort()
		
		//add control to select boxes
		var selectBoxes = $('select.'+selectBoxClass);		
		for (var i=0 ; i<selectBoxes.length ; ++i) {
			for (var j=0 ; j<unmappedControls.length ; ++j) {
				selectBoxes[i].options[j] = new Option (unmappedControls[j],unmappedControls[j]);
			}
		}
	}
	
	/**
	 * Check for association control
	 * @param response - response text for ajax call formatted as controlId->controlType
	 * @param rowId - id of the row selected 
	 * @param showTable - true = show additional mapping table, false = hide additional mapping table
	 */
	function checkForAssociator(response,rowId,showTable) {
		var controlData = response.split('->');
		
		if (controlData[1] == assocControl) {
			if(showTable) {
				$.post('includes/controlDataFunctions.php', {action:'getAllowedAssociations',cid:controlData[0] }, 
						function(response) { drawNewMappingTable(response,rowId);}, 'html');

			} else {
				var fromsid = rowId.split('_')[0];
				var fromTag = $('#'+tagnameId+rowId)[0].innerHTML;
				
				$('#'+fromTag+"_"+fromsid)[0].style.display = "none";
			}
		}
	}
	
	/**
	 * Draw new mapping table for associations
	 * @param response - response from ajax call formatted as schemeid->schemeName///schemeid->schemeName//etc.
	 * @param rowId - row id 
	 */
	function drawNewMappingTable(response,rowId) {
		var assocSchemes = response.responseText.split('///');
		
		//take the first association for now.  Multiple scheme 
		//association support may come later
		var association = assocSchemes[0];
		association = association.split('->');
		
		var fromsid = rowId.split('_')[0];
		var fromTag = $('#'+tagnameId+rowId)[0].innerHTML;
		
		var tableTag = $('#'+fromTag+"_"+fromsid)[0];
		
		if (!tableTag) {
			$.post('includes/controlDataFunctions.php', {action:'addNewMappingTable',toSchemeId:association[0],toSchemeName:association[1],
				  fromSchemeId:fromsid,fromTagname:fromTag}, 
				  function(o) { $("#additionalMapping").innerHTML += o.responseText; }, 'html');

		} else {
			tableTag.style.display = "block";
		}
	}
	
	/**
	 * Enables/Disable continue Button
	 * @param enable : boolean true=enables button, false=disables button
	 */
	function enableContinueButton() {
		$('#'+continueButtonId)[0].disabled = $('select.'+selectBoxClass).length == 0 ? false : true;
	}
	
	/**
	 * Remove or add specified control names returned from
	 * Ajax call from selection box
	 */
	function fileControlCallback(response,action) {
		var options = response.responseText.split('///');
		
		for (var i=0 ; i<options.length ; ++i) {
			selectedValue = options[i].split('->').pop();
			if (action=='remove') {
				removeControl();
			} else if (action=='add') {
				addControl();
			}
		}
	}
	
	/**
	 * Builds a selectBox's options
	 * @param selectBox : selectBox object in which options will be added to
	 */
	function getSelectBoxOptions(selectBox) {
		//add all unmapped controls to a selectbox
		for (var i=0 ; i<unmappedControls.length ; ++i) {
			selectBox.options[selectBox.options.length] = new Option(unmappedControls[i],unmappedControls[i]);
		}
	}
	
	/**
	 * Remove all of the children of an element
	 * @param element - element to remove all children from 
	 */
	function removeAllChildren(element) {
		$(element).children().empty().remove();
	}
	
	/**
	 * Removes control name from unmappedControl list and selectBoxes
	 * @param control : control name to be removed
	 */
	function removeControl() {
		//remove tagname from unmapped control names
		for (var i=0 ; i<unmappedControls.length ; ++i) {
			if (unmappedControls[i] == selectedValue) {
				unmappedControls.splice(i,1);
				break;
			}
		}
		
		//remove tagname from each of the selection boxes
		var selectBoxes = $('select.'+selectBoxClass);
		for (var i=0 ; i<selectBoxes.length ; ++i) {
			for (var j=0 ; j<selectBoxes[i].length ; ++j) {
				if (selectBoxes[i].options[j].value == selectedValue) {
					selectBoxes[i].remove(j);
				}
			}
		}
		
	}
	
	/**
	 * Add tagname and control mapping
	 */
	instance.addMapping = function (sid,rowId) {
		//if unmappedControls is empty, get current options
		if (!unmappedControls) {
			instance.setUnmappedControls();
		}
		additionalControlCellData = new Array();
		
		var idExtension = sid+"_"+rowId;
		
		//get tagname and control to map together
		var tagnameValue = $('#'+tagnameId+idExtension)[0].innerHTML;
		selectedValue = $('#'+controlSelectId+idExtension)[0].value;
		
		//clearout controlTD
		var controlTD = $('#'+controlId+idExtension)[0];
		removeAllChildren(controlTD);
		
		if (selectedValue == "All File Controls") {
			
			$.post('includes/controlDataFunctions.php',{action:'getFileControls','controls[]':'name'},
					function(response) { fileControlCallback(response,'remove');}, 'html');
			
		} else {
			$.post('includes/controlDataFunctions.php', {action:"getControlType", controlName: selectedValue, schemeId:sid}, 
					function(response) { //alert(response.responseText);
				checkForAssociator(response,idExtension,true); } );
		}
		
		//display the selected value as the control to map to 
		controlTD.innerHTML = selectedValue;
		
		//create Edit link to undo this selection
		var actionTD = $('#'+actionId+idExtension)[0];
		var x = document.createElement("a");
		x.onclick = function() {
			instance.removeMapping(sid,rowId);
		}
		x.innerHTML = "Edit";
		removeAllChildren(actionTD);
		actionTD.appendChild(x);
		
		//remove control name from select boxes
		if (selectedValue != ' -- Ignore -- ') {
			removeControl();
		}
		
		//check to see if continue button should be enabled
		enableContinueButton();
		
		selectedValue = null;
	};
	
	/**
	 *	Remove tagname and control mapping
	 */
	instance.removeMapping = function (sid,rowId) {
		//if unmappedControls is empty, get current options
		if (!unmappedControls) {
			instance.setUnmappedControls();
		}
		
		var idExtension = sid+"_"+rowId;
		
		//get control name to remove from mapping
		var controlTD = $('#'+controlId+idExtension)[0];
		selectedValue = $('#'+controlId+idExtension)[0].innerHTML;
		
		if (selectedValue == "All File Controls") {
			$.post('includes/controlDataFunctions.php',{action:'getFileControls','controls[]':'name'},
					function(response) { fileControlCallback(response,'add');} , 'html');
			
		} else {
			$.post('includes/controlDataFunctions.php', {action:"getControlType", controlName: selectedValue}, 
					function(response) { checkForAssociator(response,idExtension,false); } , 'html');
		}
		
		//add control to unmapped controls
		if(selectedValue != ' -- Ignore -- ') {
			addControl();
		}
		
		//remove text from control td
		removeAllChildren(controlTD);
		//insert a selection box in it's place
		var selectBox = document.createElement("select");
		selectBox.id = controlSelectId+idExtension;
		selectBox.className = selectBoxClass;
		getSelectBoxOptions( selectBox );
		controlTD.appendChild( selectBox );
		
		//create OK button to save selection
		var actionTD = $('#'+actionId+idExtension)[0];
		var ok = document.createElement("a");
		ok.onclick = function () {
			instance.addMapping(sid,rowId);
		}
		ok.innerHTML = "OK";
		removeAllChildren(actionTD);
		actionTD.appendChild(ok);
		
		//disable continue button
		enableContinueButton();
		
		selectedValue = null;
	};
	
	/**
	 * Set the unmappedControls so that if a tagname:controlName 
	 * mapping is removed, the select box still displays correctly
	 */
	instance.setUnmappedControls = function(overrideControls) {
		if (!unmappedControls) {
			if (overrideControls) {
				unmappedControls = overrideControls.split("///");
			} else {
				unmappedControls = new Array();
				var selectBoxes = $('select.'+selectBoxClass);
				
				if (selectBoxes.length > 0) {
					selectBoxes = selectBoxes[0];
					for (var i=0 ; i<selectBoxes.options.length ; ++i) {
						unmappedControls.push(selectBoxes.options[i].value);
					}
				}
			}
		}
		
	};
	
	/**
	 * Ingest the record data using the tagname:controlName mapping
	 */
	instance.submit = function () {
		var params = new Array();
		var mapping = new Array();
		
		var tags = $('td.tagname');
		
		var id;
		var idParts;
		var controlTd;
		//foreach row in the table, get the control/tagname mapping
		for(var i=0 ; i<tags.length ; ++i) {
			id = tags[i].id;
			
			idParts = id.split("_");
			controlTd = $('#'+controlId+idParts[1]+"_"+idParts[2])[0];
			
			mapping.push(idParts[1]+"->"+tags[i].innerHTML+"->"+controlTd.innerHTML);
		}
		
		$('#'+continueButtonId)[0].disabled = true;
		$('#'+cancelButtonId)[0].disabled = true;
		$('#'+indicatorId)[0].style.display = "inline";
		
		//send Ajax request
		$('#'+displayDivId).load("uploadXML.php", {controlMapping: mapping.join("///")});
	}
	
}