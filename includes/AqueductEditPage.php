<?php
/*
Aqueduct: A linked data semantic web extension for MediaWiki
Copyright (C) 2010 The Johns Hopkins University/Applied Physics Laboratory

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/
if ( !defined( 'MEDIAWIKI' ) )
{
	die();
}



// Modify the text coming into the edit page to allow help items for the Widget Tags to be displayed.
function aqEditWidgetTagHelp($editpage)
{
	global $wgAqueductWidgetTags, $wgAqueductAdvWidgetTags;
	aqProfile("aq");

	// This call initates only the descriptions! We are in a hook that occurs before the wgParser is initialized.
	wfAqueductSetParserHooks(null);

	$editpage->editFormPageTop .= '<br /><span style="font-size:larger;font-weight:bold">Available Aqueduct Widget Tags: </span><br />';
	$editpage->editFormPageTop .= 'Click the tag name to insert it into the page text at the cursor.<br />';
//	$editpage->editFormPageTop .= '<span style="font-weight:bold">Standard Widget Tags: </span><br />';
//	$editpage->editFormPageTop .= 'These tags do not require any parameters to be input.<br />';

	foreach ($wgAqueductWidgetTags as $name => $description)
	{
		$editpage->editFormPageTop .= '<button type="button" name="'. $name .'" title="'. $description .'" class="tagInsert">' . $name . "</button>";
	}
		
	$editpage->editFormPageTop .= '<br />';

	if ($wgAqueductAdvWidgetTags)
	{
		$editpage->editFormPageTop .= '<br /><span style="font-weight:bold">Advanced Widget Tags: </span><br />';
		$editpage->editFormPageTop .= 'These tags require a parameter to be input as described for each tag below.<br />';

		foreach ($wgAqueductAdvWidgetTags as $name => $description)
		{
			$editpage->editFormPageTop .= '<button type="button" name="'. $name .'" title="'. $description .'" class="tagInsertAdv">' . $name . '</button>';
		}

		$editpage->editFormPageTop .= '<br /><br />';
	}

	aqProfile("mw");
	return true;
}


// Function to insert the Javascript operations to insert the Tags when clicked.
function aqEditWidgetTagJS($editpage)
{
	global $wgOut, $wgScriptPath;
	$wgOut->addScriptFile($wgScriptPath.'/extensions/AqueductExtension/widget/js/jquery-1.3.2.min.js');

	$wgOut->addScript(
		'<script type="text/javascript">
			function insertWidgetTag(text) {
			'.
			// Test for browser, because IE handles this in strange and silly fashions.
				'var txtarea = document.getElementById("wpTextbox1");
				var scrollPos = txtarea.scrollTop;
				var strPos = 0; 
				var br = ((txtarea.selectionStart || txtarea.selectionStart == "0") ? "ff" : (document.selection ? "ie" : false ) );
				'.
				
				// Give the element focus and extract the position.
				'if (br == "ie") 
				{ 
					txtarea.focus(); 
					var range = document.selection.createRange(); 
					range.moveStart ("character", -txtarea.value.length); 
					strPos = range.text.length; 
				} 
				else if (br == "ff") 
				{
					strPos = txtarea.selectionStart; 
				}
				'.
	
				// Insert the tag.
				'var front = (txtarea.value).substring(0,strPos); 
				var back = (txtarea.value).substring(strPos,txtarea.value.length); 
				txtarea.value=front+text+back; 
				strPos = strPos + text.length; 
				'.
	
				// Rework the selection to point JUST AFTER the tag that was just inserted.
				'if (br == "ie") 
				{ 
					txtarea.focus(); 
					var range = document.selection.createRange(); 
					range.moveStart ("character", -txtarea.value.length); 
					range.moveStart ("character", strPos); 
					range.moveEnd ("character", 0); 
					range.select(); 
				} 
				else if (br == "ff") 
				{ 
					txtarea.selectionStart = strPos; 
					txtarea.selectionEnd = strPos; 
					txtarea.focus(); 
				} 
				txtarea.scrollTop = scrollPos; 
			};

			jQuery(document).ready(function() {
				jQuery(".tagInsert").click(function() {
					var name = jQuery(this).attr("name");
					var text = "\n<" + name + " />"; 
					insertWidgetTag(text);
				});
				jQuery(".tagInsertAdv").click(function() {
					var name = jQuery(this).attr("name");
					var text = "\n<" + name + ">   INSERT YOUR PARAMETER HERE   </" + name + ">"; 
					insertWidgetTag(text);
				});
			});	
		</script>'
	);
	return true;
}
?>
