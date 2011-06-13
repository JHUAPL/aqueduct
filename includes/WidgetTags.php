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


// Necessary to include this to be able to call up the Translation Table to pass to each page ONCE PER PAGE for the widgets.
require_once('extensions/AqueductExtension/includes/AqueductDbCalls.php');

//Convenience function to extract the grid-mode height, width, and position parameters from the widget tag
function aqWidgetGridParams($args, $data)
{
	$data['height'] = ($args['height']===NULL?'':$args['height']);
	$data['width'] = ($args['width']===NULL?'':$args['width']);
	$data['position'] = ($args['position']===NULL?'':$args['position']);
	$data['resizable'] = ($args['resizable']===NULL?'':$args['resizable']);
	return $data;
}

/// Widget tag to display the "Raw" skinned widget to a page.
function aqRawWidgetTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets, $wgAqueductJSFiles;

	aqProfile('aq');
	$parser->disableCache();

	//First set the title.
	$myTitle = $parser->getTitle()->getPrefixedDBkey();
		
	//JSON-encode the title so titles with special characters work
	$myJsonTitle = json_encode($myTitle);

	//Set Widget data array.
	$wgtData = array('title' => $myJsonTitle, 'skin'=>'RawRDFCisternSkin');
	$wgtData = aqWidgetGridParams($args, $wgtData);

	if (!isset($wgAqueductJSFiles))
		$wgAqueductJSFiles = array();
		
	if (!isset($wgAqueductJSFiles['rawRdfSkin.js']))
		$wgAqueductJSFiles['rawRdfSkin.js'] = true;

	//Check the accumulation variable.
	if(isset($wgAqueductAccumWidgets))
	{
		//If it is set, add the data to the accumulation and do nothing else.
		$wgAqueductAccumWidgets[] = $wgtData;
		aqProfile('mw');
	}
	else
	{
		//If it is not set, then flush the widget to the page.
		return aqWidgetTagFlush($wgtData);
	}
}

/// Widget tag to display the Table View widget to a page.
function aqTableViewWidgetTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets, $wgAqueductJSFiles;

	aqProfile('aq');
	$parser->disableCache();

	//First set the title.
	$myTitle = $parser->getTitle()->getPrefixedDBkey();
		
	//JSON-encode the title so titles with special characters work
	$myJsonTitle = json_encode($myTitle);

	//Set Widget data array.
	$wgtData = array('title' => $myJsonTitle, 'skin'=>'TableViewCisternSkin');
	$wgtData = aqWidgetGridParams($args, $wgtData);

	if (!isset($wgAqueductJSFiles))
		$wgAqueductJSFiles = array();
		
	if (!isset($wgAqueductJSFiles['TableViewCisternSkin.js']))
		$wgAqueductJSFiles['TableViewCisternSkin.js'] = true;

	//Check the accumulation variable.
	if(isset($wgAqueductAccumWidgets))
	{
		//If it is set, add the data to the accumulation and do nothing else.
		$wgAqueductAccumWidgets[] = $wgtData;
		aqProfile('mw');
	}
	else
	{
		//If it is not set, then flush the widget to the page.
		return aqWidgetTagFlush($wgtData);
	}
}

/// Widget tag to display the Google Map widget to a page.
function aqNetworkViewWidgetTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets, $wgAqueductJSFiles, $wgAqueductJSScripts, $wgJsMimeType, $wgGoogleAPIKey;
	aqProfile('aq');
	$parser->disableCache();

	//First set the title.
	$myTitle = $parser->getTitle()->getPrefixedDBkey();
		
	//JSON-encode the title so titles with special characters work
	$myJsonTitle = json_encode($myTitle);

	//Set Widget data array.
	$wgtData = array('title' => $myJsonTitle, 'skin'=>'NetworkViewSkin');
	$wgtData = aqWidgetGridParams($args, $wgtData);

	if (!isset($wgAqueductJSFiles))
		$wgAqueductJSFiles = array();

	if (!isset($wgAqueductJSScripts))
		$wgAqueductJSScripts = array();
		
	if (!isset($wgAqueductJSScripts["<script type=\"{$wgJsMimeType}\" src=\"http://www.google.com/jsapi?key={$wgGoogleAPIKey}\"></script>\n"]))
		$wgAqueductJSScripts["<script type=\"{$wgJsMimeType}\" src=\"http://www.google.com/jsapi?key={$wgGoogleAPIKey}\"></script>\n"] = true;
	if (!isset($wgAqueductJSFiles['NetworkViewSkin.js']))
		$wgAqueductJSFiles['NetworkViewSkin.js'] = true;
	if (!isset($wgAqueductJSFiles['GoogleCore.js']))
		$wgAqueductJSFiles['GoogleCore.js'] = true;
	if (!isset($wgAqueductJSFiles['GoogleEarthPlugin.js']))
		$wgAqueductJSFiles['GoogleEarthPlugin.js'] = true;

	//Check the accumulation variable.
	if(isset($wgAqueductAccumWidgets))
	{
		//If it is set, add the data to the accumulation and do nothing else.
		$wgAqueductAccumWidgets[] = $wgtData;
		aqProfile('mw');
	}
	else
	{
		//If it is not set, then flush the widget to the page.
		return aqWidgetTagFlush($wgtData);
	}
}

/// Widget tag to display the Google Map widget to a page.
function aqNetworkViewWidget2DTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets, $wgAqueductJSFiles, $wgAqueductJSScripts, $wgJsMimeType, $wgGoogleAPIKey;

	aqProfile('aq');
	$parser->disableCache();

	//First set the title.
	$myTitle = $parser->getTitle()->getPrefixedDBkey();
		
	//JSON-encode the title so titles with special characters work
	$myJsonTitle = json_encode($myTitle);

	//Set Widget data array.
	$wgtData = array('title' => $myJsonTitle, 'skin'=>'NetworkViewSkin2D');
	$wgtData = aqWidgetGridParams($args, $wgtData);

	if (!isset($wgAqueductJSFiles))
		$wgAqueductJSFiles = array();

	if (!isset($wgAqueductJSScripts))
		$wgAqueductJSScripts = array();

	if (!isset($wgAqueductJSScripts['<script type="'.$wgJsMimeType.'" src="http://maps.google.com/maps?file=api&v=2.x&key='.$wgGoogleAPIKey.'&sensor=false"></script>']))
		$wgAqueductJSScripts['<script type="'.$wgJsMimeType.'" src="http://maps.google.com/maps?file=api&v=2.x&key='.$wgGoogleAPIKey.'&sensor=false"></script>'] = true;
	if (!isset($wgAqueductJSFiles['NetworkViewSkin2D.js']))
		$wgAqueductJSFiles['NetworkViewSkin2D.js'] = true;
		
	//Check the accumulation variable.
	if(isset($wgAqueductAccumWidgets))
	{
		//If it is set, add the data to the accumulation and do nothing else.
		$wgAqueductAccumWidgets[] = $wgtData;
		aqProfile('mw');
	}
	else
	{
		//If it is not set, then flush the widget to the page.
		return aqWidgetTagFlush($wgtData);
	}
}

/// Tag to define a user-defined widget layout
function aqLayoutTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets, $wgCurrentLayout;
	aqProfile('aq');
	$parser->disableCache();	
	
	//Set Widget data array.
	$wgtData = array('skin'=>'Layout');
			
	//Remember what the active layout is
	$wgCurrentLayout = $input;

	//Check the accumulation variable.
	if(isset($wgAqueductAccumWidgets))
	{
		//If it is set, add the data to the accumulation and do nothing else.
		$wgAqueductAccumWidgets[] = $wgtData;
		aqProfile('mw');
	}
	else
	{
		//If it is not set, then immediately apply the layout
		return aqWidgetTagFlush($wgtData);
	}
}

/// Widget tag to display a user-defined widget on the page, using the last processed layout.
function aqLayoutWidgetTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets,$wgAqueductJSFiles;
	aqProfile('aq');
	$parser->disableCache();	
	
	//First set the title.
	$myTitle = $parser->getTitle()->getPrefixedDBkey();
		
	//JSON-encode the title so titles with special characters work
	$myJsonTitle = json_encode($myTitle);

	//Set Widget data array.
	$wgtData = array('title' => $myJsonTitle, 'skin'=>'LayoutSkin');
	$wgtData = aqWidgetGridParams($args, $wgtData);

	if (!$wgAqueductJSFiles)
		$wgAqueductJSFiles = array();
	if (!isset($wgAqueductJSFiles['TemplateCisternSkin.js']))
		$wgAqueductJSFiles['TemplateCisternSkin.js'] = true;
	if (!isset($wgAqueductJSFiles['jquery-jtemplates.js']))
		$wgAqueductJSFiles['jquery-jtemplates.js'] = true;

	//Check the accumulation variable.
	if(isset($wgAqueductAccumWidgets))
	{
		//If it is set, add the data to the accumulation and do nothing else.
		$wgAqueductAccumWidgets[] = $wgtData;
		aqProfile('mw');
	}
	else
	{
		//If it is not set, then flush the widget to the page.
		return aqWidgetTagFlush($wgtData);
	}
}

// Tag to mark the wikitext position when using grid mode
function aqWikiTextTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets,$wgAqueductJSFiles,$wgAqueductLayoutMode;

	aqProfile('aq');
	if ($wgAqueductLayoutMode)
	{
		$parser->disableCache();
		
		//Set Widget data array.
		$wgtData = array('skin'=>'WikiText');
		$wgtData = aqWidgetGridParams($args, $wgtData);

		//Check the accumulation variable.
		if(isset($wgAqueductAccumWidgets))
		{
			//If it is set, add the data to the accumulation and do nothing else.
			$wgAqueductAccumWidgets[] = $wgtData;
			aqProfile('mw');
		}
		else
		{
			//If it is not set, then flush the widget to the page.
			return aqWidgetTagFlush($wgtData);
		}
	}
}


/// Widget tag to add a custom header
function aqHeaderTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets, $wgAqueductJSFiles;
	aqProfile('aq');
	$parser->disableCache();

	//First set the title.
	$myTitle = $parser->getTitle()->getPrefixedDBkey();
		
	//JSON-encode the title so titles with special characters work
	$myJsonTitle = json_encode($myTitle);

	//Set Widget data array.
	$wgtData = array('title' => $myJsonTitle, 'skin' => 'CustomHeader');

	if (!isset($wgAqueductJSFiles))
		$wgAqueductJSFiles = array();
		
	if (!isset($wgAqueductJSFiles[$input]))
		$wgAqueductJSFiles[$input] = true;

	//Check the accumulation variable.
	if(isset($wgAqueductAccumWidgets))
	{
		//If it is set, add the data to the accumulation and do nothing else.
		$wgAqueductAccumWidgets[] = $wgtData;
		aqProfile('mw');
	}
	else
	{
		//If it is not set, then flush the widget to the page.
		return aqWidgetTagFlush($wgtData);
	}
}

/// Widget tag to add a custom script
function aqScriptTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets, $wgAqueductJSScripts;
	aqProfile('aq');
	$parser->disableCache();

	//First set the title.
	$myTitle = $parser->getTitle()->getPrefixedDBkey();
		
	//JSON-encode the title so titles with special characters work
	$myJsonTitle = json_encode($myTitle);

	//Set Widget data array.
	$wgtData = array('title' => $myJsonTitle, 'skin' => 'CustomScript' );

	if (!isset($wgAqueductJSScripts))
		$wgAqueductJSScripts = array();
		
	if (!isset($wgAqueductJSScripts['<script type="text/javascript"> '.$input.' </script>']))
		$wgAqueductJSScripts['<script type="text/javascript"> '.$input.' </script>'] = true;

	//Check the accumulation variable.
	if(isset($wgAqueductAccumWidgets))
	{
		//If it is set, add the data to the accumulation and do nothing else.
		$wgAqueductAccumWidgets[] = $wgtData;
		aqProfile('mw');
	}
	else
	{
		//If it is not set, then flush the widget to the page.
		return aqWidgetTagFlush($wgtData);
	}
}

/// Core function that flushes all widgets in the buffer for this page to the page view.
function aqWidgetTagFlush($wgtData = NULL)
{
	global $wgAqueductLayoutMode, $wgCurrentLayout, $wgOut, $wgServer, $wgScriptPath, $wgAqueductDivNumber, $wgAqueductJSFiles, $wgAqueductJSScripts, $wgAqueductAccumWidgets, $wgAqueductDataAdded, $wgAqueductQueryAdded;

	// Check to make sure we are not in a Data Inclusion API call. If so, get out.
	if (isset($wgAqueductDataAdded) || isset($wgAqueductQueryAdded))
	{
		aqProfile('mw');
		return true;
	}

	// Widget div number initialization.
	if (!$wgAqueductDivNumber)
		$wgAqueductDivNumber = 1;

	// if wgtData is null, use the accum widgets array. 
	//If they are both set or unset, this is a failstate.
	if ($wgtData != NULL && !isset($wgAqueductAccumWidgets))
	{
		$widgetsArray = array($wgtData);
	}
	else if ($wgtData == NULL && isset($wgAqueductAccumWidgets))
	{
		$widgetsArray = $wgAqueductAccumWidgets;
	}
	else
	{
		aqProfile('mw');
		return 'A fatal error has occurred in an Aqueduct Widget on this page. Please contact your Wiki Administrator.';
	}

	// Add widget-insensitive core scripts.
	if (!isset($wgAqueductJSFiles['jquery-1.3.2.min.js']))
	{
		$wgAqueductJSFiles['jquery-1.3.2.min.js'] = false;
		$wgOut->addScriptFile($wgScriptPath. '/extensions/AqueductExtension/widget/js/jquery-1.3.2.min.js');
	}
	if (!isset($wgAqueductJSFiles['widgetCore.js']))
	{
		$wgAqueductJSFiles['widgetCore.js'] = false;
		$wgOut->addScriptFile($wgScriptPath. '/extensions/AqueductExtension/widget/js/widgetCore.js');
	}

	// Add the aqueduct namespace translation table to the javascript, allowing the JS uri->title functionality.
	$rows = aqDbTableGetAll();
	$jsonrows = array();

	foreach ($rows as $row => $vals)
	{
		$jsonvals = array();
		$jsonvals['aq_source_uri'] = $vals['aq_source_uri'];
		$jsonvals['aq_initial_lowercase'] = $vals['aq_initial_lowercase'];
		$jsonvals['aq_wiki_namespace_id'] = $vals['aq_wiki_namespace_id'];
		$jsonvals['aq_wiki_namespace'] = $vals['aq_wiki_namespace'];
		$jsonrows []= $jsonvals;
		//For security, don't pass anything else to the browser
	}

	// JSON the array
	$data = json_encode($jsonrows);

	// Add the json array to the page as a head script.
	if (!isset($wgAqueductJSScripts['<script type="text/javascript">/*<![CDATA[*/var aqTransTable = '.$data.'/*]]>*/</script>']))
	{
		$wgAqueductJSScripts['<script type="text/javascript">/*<![CDATA[*/var aqTransTable = '.$data.'/*]]>*/</script>'] = false;
		$wgOut->addScript('<script type="text/javascript">/*<![CDATA[*/var aqTransTable = '.$data.'/*]]>*/</script>' . "\n");
	}
	
	// If we are in grid mode, init the widget list that the grid-construction code in the skin will read
	if ($wgAqueductLayoutMode && !isset($wgAqueductJSScripts['<script type="text/javascript">/*<![CDATA[*/var aqWidgetList = []; /*]]>*/</script>']))
	{
		$wgAqueductJSScripts['<script type="text/javascript">/*<![CDATA[*/var aqWidgetList = []; /*]]>*/</script>'] = false;
		$wgOut->addScript('<script type="text/javascript">/*<![CDATA[*/var aqWidgetList = []; /*]]>*/</script>' . "\n");
	}

	$output = '';

	foreach ($wgAqueductJSScripts as $script => &$trigger)
	{
		if ($trigger == true)
		{
			$wgOut->addScript($script . "\n");
			$trigger = false;
		}
	}

	// Dump the needed JS files and scripts;
	foreach ($wgAqueductJSFiles as $file => &$trigger)
	{
		if ($trigger == true)
		{
			$wgOut->addScriptFile($wgScriptPath. '/extensions/AqueductExtension/widget/js/' . $file);
			$trigger = false;
		}
	}

	// Go through the Widgets on this page and flush their data to the page.
	foreach ($widgetsArray as $data)
	{
		$templatejson = 'null';
		if (isset($wgCurrentLayout))
		{
			$templatejson = json_encode($wgCurrentLayout);
		}		
		if ($data['skin'] == 'WikiText' && $wgAqueductLayoutMode)
		{
			//This is the wikitext position marker for layout mode
			$wgOut->addScript('<script type="text/javascript">'."\n"
				. 'aqWidgetList.push([null,null,null,null,null,'
				. json_encode($data['position']).','
				. json_encode($data['height']).','
				. json_encode($data['width']) . ','
				. 'true' . ','
				. json_encode($data['resizable'])
				. ']);'
				. "</script>\n");
		}		
		else if($data['skin'] != 'CustomHeader' && $data['skin'] != 'CustomScript' && $data['skin'] != 'Layout')
		{
			//If we are in grid mode, let the Javascript construct the widget divs, instead of doing it right away
			if ($wgAqueductLayoutMode)
			{
				$wgOut->addScript('<script type="text/javascript">'."\n"
				. 'aqWidgetList.push(["'
				. $data['skin'].'","'
				. $wgServer.$wgScriptPath.'/api.php",'
				. $data['title'].','
				. $data['title'].','
				. $templatejson.','
				. json_encode($data['position']).','
				. json_encode($data['height']).','
				. json_encode($data['width']) . ','
				. 'false'. ','
				. json_encode($data['resizable'])
				. ']);'
				. "</script>\n");
			}
			else
			{
				// Put up script block with the widget call
				$wgOut->addScript('<script type="text/javascript">'."\n\t\t\t"
						. 'initialQuery = '.$data['title']."\n\t\t\t"
						. 'fullQuery = '.$data['title']."\n\t\t\t"
						. 'template = '.$templatejson."\n\t\t\t"
						. 'widget'.$wgAqueductDivNumber.' = new CisternWidget("AqueductDiv'.$wgAqueductDivNumber.'", "'.$data['skin'].'", "'.$wgServer.$wgScriptPath.'/api.php", initialQuery, fullQuery, template,false)'."\n\t\t"
						. "</script>\n");
						
				// Put out the Div for the widget in this page.
	 			$output .= "<div id=\"AqueductDiv".$wgAqueductDivNumber."\" name=\"AqueductDiv".$wgAqueductDivNumber."\" style=\"border:1px solid #B0B0B0; background-color:#FFFFFF; padding:5px; margin:10px; float:left\"> </div>";
				// autoincrement to keep div IDs unique
				$wgAqueductDivNumber += 1;
			}
		}
	}
	aqProfile('mw');
	return $output;
}

/// Tag function to recursively include widgets from other pages.
function aqIncludeWidgetsTag($input, $args, $parser)
{
	global $wgAqueductAccumWidgets, $wgAqueductPagesSeen;
	aqProfile('aq');
	$parser->disableCache();

	// Initialize the PagesSeen array if it doesn't exist.
	if (!isset($wgAqueductPagesSeen))
		$wgAqueductPagesSeen = array();

	// Tell the widgets to accumulate instead of printing straight away.
	// Otherwise, continue on with recursion.
	if (!isset($wgAqueductAccumWidgets))
	{
		$wgAqueductAccumWidgets = array();
		$entryPoint = true;
		$myTitle = json_encode($parser->getTitle()->getPrefixedDBkey());
		$wgAqueductPagesSeen["$myTitle"] = true;
	}

	// Make a Title object for the page you're recursing to.
	$wgtTitle = Title::newFromText($input);

	// Add the page we are recursing to to the pages seen.
	// Otherwise, we need to break this tree of recursions.
	if($wgAqueductPagesSeen["$wgtTitle"] != true)
	{	
		$wgAqueductPagesSeen["$wgtTitle"] = true;
	}
	else
	{
		return true;
	}

	// Create an article object that points to the page.
	$wgtArticle = new Article($wgtTitle);
	
	// Get the contents of the page template transclusion style.
	$wgtTags = $wgtArticle->getContent();
	
	// Return the parsed widget tags to display the widgets.
	$oldTitle = $parser->getTitle();

	// Instantiate a new parser to use.
	$wgtParser = new Parser();
	wfAqueductSetParserHooks($wgtParser);
	$wgtParser->disableCache();

	// Recurse to the page we need to include.
	$wgtParser->parse($wgtTags, $wgtTitle, $parser->Options());
	aqProfile('aq');

	// Reset this so that other include tags will work.
	$wgAqueductPagesSeen["$wgtTitle"] = false;

	if ($entryPoint == true)
	{
		$output = aqWidgetTagFlush();
		// Unset only works locally. Unless you do this.
		unset($GLOBALS['wgAqueductAccumWidgets']);
		$wgAqueductPagesSeen["$myTitle"] = false;
		aqProfile('mw');
		return $output;
	}
	else
	{
		aqProfile('mw');
		return true;
	}
}


/// Tag to handle the Data inclusion case, where one widget displays many pages' data.
function aqAddDataTag($input, $args, $parser)
{
	aqProfile('aq');
	$parser->disableCache();
	global $wgAqueductDataAdded;

	// Check to make sure we are in a Data Inclusion API call. If not, get out.
	if (!isset($wgAqueductDataAdded))
	{
		aqProfile('mw');
		return '';
	}
	
	// Create the title object
	$dataTitle = Title::newFromText($input);

	// Add title object to the array
	if ($dataTitle !== NULL)
	{
		//Only add it if the title didn't have any syntax errors in it
		$wgAqueductDataAdded[] = $dataTitle;
	}
	aqProfile('mw');
	return '';
}


// Inline SPARQL queries to allow a widget on a page to display arbitrary SPARQL.
function aqAddQueryTag($input, $args, $parser)
{
	aqProfile('aq');
	$parser->disableCache();
	global $wgAqueductQueryAdded;

	// Check to make sure we are in a Query API call. If not, get out
	if (!isset($wgAqueductQueryAdded))
	{
		aqProfile('mw');
		return '';
	}
	
	$queryColumns = array(
		'aq_wiki_namespace_id',		//Namespace ID:	Assumed by the call; 				 not needed in the parameters.
		'aq_wiki_parent_namespace', 	//Namespace: 	Namespace of the query to be made in; 		 not needed in the parameters.
		'aq_wiki_namespace_tag', 	//Query Tag:	Query tag used to reference the row in database; not needed in the parameters.
		'aq_query_type', 		//Query Type:	Type of query to be made;			 To be passed as a parameter.
		'aq_datasource', 		//Datasource:	Datasource to query against;			 To be passed as a parameter.
		'aq_algorithm', 		//Algorithm:	Algorithm used by the query;			 To be passed as a parameter.
		'aq_query_uri_param',		//URI as param:	Boolean to say whether title is used as param;	 To be passed as a parameter.
		'aq_query'			//Query Text:	Actual body of the query;			 Taken through $input.
		);

	// Generate the 'row' to mimic the rows in the database.
	$queryrow = array();
	$myTitle = Title::newFromText($args['namespace'] . ':Query');
	$queryrow["$queryColumns[0]"] = $myTitle->getNamespace();
	$queryrow["$queryColumns[1]"] = $args['namespace'];
	$queryrow["$queryColumns[2]"] = '';
	$queryrow["$queryColumns[3]"] = $args['type'];
	$queryrow["$queryColumns[4]"] = $args['datasource'];
	$queryrow["$queryColumns[5]"] = $args['algorithm'];
	$queryrow["$queryColumns[6]"] = $args['uriasparam'];
	$queryrow["$queryColumns[7]"] = $input;

	$wgAqueductQueryAdded[] = $queryrow;
	aqProfile('mw');
	return '';
}

//Allows RDF triples to be directly embedded in a page
function aqAddTripleTag( &$parser, $predicate, $object, $objecttype=NULL, $fragment=NULL )
{
	aqProfile('aq');
	$parser->disableCache();
	global $wgAqueductTriplesAdded;
	//Don't activate this unless we are specifically checking for these triples.	
	if (!isset($wgAqueductTriplesAdded))
	{
		aqProfile('mw');
		return '';
	}
	$wgAqueductTriplesAdded []=
		array('fragment' => $fragment, 'object' => $object, 'predicate' => $predicate, 'objecttype' => $objecttype);
	aqProfile('mw');
	return '';
}
