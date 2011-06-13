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
	
	//Begin profiling
	aqProfile("aq");
	
	//Put settings here so they can be modified easily
	$wgAqueductTblname = "aqueduct";
	$wgAqueductQueryTblname = "aqueductqueries";
	$wgAqueductOldNSTblname = "aqueductoldnamespaces";
	
	//Set up PHP options
	ini_set("soap.wsdl_cache_enabled", "0");
	
	$dir = dirname(__FILE__) . '/includes/';
	//Set up general extension settings
	$wgExtensionCredits['specialpage'][] = array(
		'name' => 'Aqueduct Extension',
		'author' => 'The Johns Hopkins University Applied Physics Laboratory',
		'description' => 'A Mediawiki-based platform that allows users to explore, visualize, and annotate external RDF data sources within a wiki interface',
		'version' => 1.2
		);
		
	//Set up the special page
	$wgExtensionMessagesFiles['AqueductExtension'] = $dir . 'AqueductExtension.i18n.php';
	$wgAutoloadClasses['SpecialAqueductConfiguration'] = $dir . 'SpecialAqueductConfiguration.php';
	$wgSpecialPages['AqueductConfiguration'] = 'SpecialAqueductConfiguration';
	
	//Set up the Aqueduct API
	$wgAutoloadClasses['ApiAqueduct'] = $dir . 'ApiAqueduct.php';	
	$wgAPIModules['aqueduct'] = 'ApiAqueduct';

	//Set up the Aqueduct Set API
	$wgAutoloadClasses['ApiAqueductSet'] = $dir . 'ApiAqueductSet.php';	
	$wgAPIModules['aqueductset'] = 'ApiAqueductSet';
	
	// Article prepopulation extension
	require_once($dir . 'AqueductPagePopulation.php');
	require_once($dir . 'AqueductEditPage.php');
	
	// Set up the hook to interupt the edit to check for a new page creation.
	$wgHooks['EditPage::showEditForm:initial'][] = 'aqEditWidgetTagHelp';
	$wgHooks['AlternateEdit'][] = 'aqEditWidgetTagJS';
	$wgHooks['AlternateEdit'][] = 'aqPopulateNewPage';
 	
	//Register a hook to set up the user-configured Mediawiki namespaces
	$wgHooks['SetupAfterCache'][] = 'wfAqueductSetupNS';
	
	//Load the file with the widget tag logic
	require_once($dir . 'WidgetTags.php');
	
	//Register the general extension init code (used to set up the widget parser tags)
	if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
		$wgHooks['ParserFirstCallInit'][] = 'wfAqueductExtension';
	}
	 else { // Otherwise do things the old fashioned way
		$wgExtensionFunctions[] = "wfAqueductExtension";
	}
	
	//Register the magic word init code (used to set up the parser function alias)
	$wgHooks['LanguageGetMagic'][] = 'wfAqueductExtensionSetMagicWords';

	
	aqProfile("mw");	
	
	//Function and class definitions go below
	
	//Set up the user-defined Aqueduct namespaces (this must be called early in Mediawiki's init)
	function wfAqueductSetupNS()
	{
		global $wgAqueductTblname, $wgCanonicalNamespaceNames, $wgExtraNamespaces, $wgAqueductQueryTblname, $wgAqueductOldNSTblname;
		//Don't use DB functions because that file is not loaded here...
		aqProfile("aq");
		$newNamespaces = array();
		$db =& wfGetDB( DB_SLAVE );
		
		$res = $db->select($wgAqueductTblname, '*');
		while($row = $db->fetchRow($res))
		{
			if (intval($row['aq_wiki_namespace_id'])!=0)
			{
				$newNamespaces[intval($row['aq_wiki_namespace_id'])] = $row['aq_wiki_namespace'];
				$newNamespaces[intval($row['aq_wiki_namespace_id'])+1] = $row['aq_wiki_namespace'].'_talk';
			}
		}
		$db->freeResult($res);

		$res = $db->select($wgAqueductOldNSTblname, '*');
		while($row = $db->fetchRow($res))
		{
			if (intval($row['aq_wiki_namespace_id'])!=0)
			{
				$newNamespaces[intval($row['aq_wiki_namespace_id'])] = $row['aq_wiki_namespace'];
				$newNamespaces[intval($row['aq_wiki_namespace_id'])+1] = $row['aq_wiki_namespace'].'_talk';
			}
		}
		$db->freeResult($res);

		$res = $db->select($wgAqueductQueryTblname, '*');
		while($row = $db->fetchRow($res))
		{
			if (intval($row['aq_wiki_namespace_id'])!=0)
			{
				$newNamespaces[intval($row['aq_wiki_namespace_id'])] = $row['aq_wiki_parent_namespace'] . '_' . $row['aq_wiki_namespace_tag'];
				$newNamespaces[intval($row['aq_wiki_namespace_id'])+1] = $row['aq_wiki_parent_namespace'] . '_' . $row['aq_wiki_namespace_tag'] . '_talk';
			}
		}
		$db->freeResult($res);

		if (!is_array($wgExtraNamespaces))
		{
			$wgExtraNamespaces = array();
		}
		$wgExtraNamespaces = $wgExtraNamespaces + $newNamespaces;
		$wgCanonicalNamespaceNames = $wgCanonicalNamespaceNames + $newNamespaces;
		aqProfile("mw");
		return true;
	}
	
	//Extension initialization function (normally used to set up parser tags)
	function wfAqueductExtension()
	{
		global $wgParser,$wgAqueductLayoutMode,$wgDefaultSkin,$wgAqueductJSFiles,$wgRequest,$wgTitle,$wgOut,$wgScriptPath;
		aqProfile("aq");
		wfAqueductSetParserHooks($wgParser);
		//Only do this once (multiple parser init will be called if we are displaying a compound page)
		if (!isset($wgAqueductLayoutMode))
		{
			//Figure out if we are using the gridbook skin; if so, go into layout mode
			$wgAqueductLayoutMode =
				($wgDefaultSkin == 'gridbook' &&
				$wgRequest->getText( 'action', 'view' ) == 'view' &&
				$wgTitle->getNamespace()>-1);
			if ($wgAqueductLayoutMode)
			{
				if (!$wgAqueductJSFiles)
				{
					$wgAqueductJSFiles = array();
				}
				//Force a bunch of JS and CSS files to load even if there are no widgets on the page because we are in layout mode
				$wgAqueductJSFiles['jquery-1.3.2.min.js'] = false;
				$wgAqueductJSFiles['jquery-ui-1.7.2.custom.min.js'] = false;
				$wgAqueductJSFiles['jquery.layout.min-1.2.0.js'] = false;
				$wgAqueductJSFiles['gridPlacement.js'] = false;
				$wgAqueductJSScripts['<link type="text/css" href="'.$wgScriptPath.'/extensions/AqueductExtension/widget/js/aqueduct-layout.css" rel="Stylesheet" />'] = false;
				$wgOut->addScriptFile($wgScriptPath. '/extensions/AqueductExtension/widget/js/jquery-1.3.2.min.js');
				$wgOut->addScriptFile($wgScriptPath. '/extensions/AqueductExtension/widget/js/jquery-ui-1.7.2.custom.min.js');
				$wgOut->addScriptFile($wgScriptPath. '/extensions/AqueductExtension/widget/js/jquery.layout.min-1.2.0.js');
				$wgOut->addScriptFile($wgScriptPath. '/extensions/AqueductExtension/widget/js/gridPlacement.js');
				$wgOut->addScript('<link type="text/css" href="'.$wgScriptPath.'/extensions/AqueductExtension/widget/js/aqueduct-layout.css" rel="Stylesheet" />' . "\n");
			}
		}
		aqProfile("mw");
		return true;
	}
	
	function aqSetHook($parser, $name, $function, $description)
	{
		global $wgAqueductWidgetTags;

		// Initiate the array
		if (!isset($wgAqueductWidgetTags) || !is_array($wgAqueductWidgetTags))
			$wgAqueductWidgetTags = array();

		// store the description
		$wgAqueductWidgetTags["$name"] = "$description";

		// Add to the parser hooks
		// if parser is null, then only initiate the descriptions!
		if ($parser !== null)
			$parser->setHook( $name, $function );
	}
	
	function aqSetAdvHook($parser, $name, $function, $description)
	{
		global $wgAqueductAdvWidgetTags;

		// Initiate the array
		if (!isset($wgAqueductAdvWidgetTags) || !is_array($wgAqueductAdvWidgetTags))
			$wgAqueductAdvWidgetTags = array();

		// store the description
		$wgAqueductAdvWidgetTags["$name"] = "$description";

		// Add to the parser hooks
		// if parser is null, then only initiate the descriptions!
		if ($parser !== null)
			$parser->setHook( $name, $function );
	}
	
	function wfAqueductSetParserHooks($parser)
	{
		global $wgEnableLayoutWidgets,$wgEnableCustomScripts;
		aqSetHook($parser, "aqProfile", "aqProfileTag", "Display profiling output for a page's Aqueduct operations." );
		aqSetHook($parser, "aqRawWidget", "aqRawWidgetTag", "Place a Raw Format widget. Each row represents an RDF triple." );
		aqSetHook($parser, "aqTableViewWidget", "aqTableViewWidgetTag", "Place a Table Format widget. Each row represents an entity, and each column contains fields about that entity." );
		aqSetHook($parser, "aqNetworkViewWidget", "aqNetworkViewWidgetTag", "Place a Google Earth widget. Displays data with geospatial information as markers." );
		aqSetHook($parser, "aqNetworkViewWidget2D", "aqNetworkViewWidget2DTag", "Place a Google Map widget. Displays data with geospatial information as markers." );
		aqSetAdvHook($parser, "aqIncludeWidgets", "aqIncludeWidgetsTag", "Takes a WikiTitle as input and includes all widgets from that page." );
		aqSetAdvHook($parser, "aqAddData", "aqAddDataTag", "Takes a WikiTitle as input and includes all data from that page into widgets on this page." );
		aqSetAdvHook($parser, "aqAddQuery", "aqAddQueryTag", "Takes a SPARQL query as input to execute." );
		aqSetAdvHook($parser, "aqWikiText", "aqWikiTextTag", "Indicates where wikitext should be displayed in a grid-mode page." );

		if ($wgEnableCustomScripts )
		{
			aqSetAdvHook($parser, "aqAddHeader", "aqHeaderTag", "Adds a Javascript file to the page." );
			aqSetAdvHook($parser, "aqAddScript", "aqScriptTag", "Adds a Javascript script to the page." );
		}
		if ($wgEnableLayoutWidgets)
		{
			aqSetAdvHook($parser, "aqLayout", "aqLayoutTag", "Inserts a layout to be used by a Layout widget." );
			aqSetHook($parser, "aqLayoutWidget", "aqLayoutWidgetTag", "Uses the layout inserted into a page via 'aqLayout' to display RDF." );
		}

		if ($parser !== null)
			$parser->setFunctionHook( 'triplemagicword', 'aqAddTripleTag' );
	}
	
	function wfAqueductExtensionSetMagicWords( &$magicWords, $langCode )
	{
        $magicWords['triplemagicword'] = array( 0, 'triple' );
        return true;
	}

	function aqProfile($newmode)
	{
		global $wgAqueductProfile;
		if ($wgAqueductProfile === TRUE)
		{
			global $aqProfileMode, $aqProfileStart, $aqProfileData;
			$endtime = microtime(TRUE);
			if ($aqProfileMode)
			{
				if (!$aqProfileData)
				{
					$aqProfileData = array();			
				}
				if (array_key_exists($aqProfileMode,$aqProfileData))
				{
					$oldtime = $aqProfileData[$aqProfileMode];
				}
				else
				{
					$oldtime = 0.0;
				}
				$totaltime = $endtime - $aqProfileStart;
				$aqProfileData[$aqProfileMode] = $oldtime + $totaltime;
			}
			$aqProfileMode = $newmode;
			$aqProfileStart = microtime(TRUE);
		}
	}
	
	function aqProfileTag()
	{
		global $aqProfileData;
		$out = print_r($aqProfileData,TRUE);
		$aqProfileData = null;
		return $out;
	}

?>
