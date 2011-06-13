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


// Include the aqDB calls to get to the aqTransTable, and the interfaces for materialization.
require_once('extensions/AqueductExtension/includes/AqueductDbCalls.php');
require_once('extensions/AqueductExtension/includes/AqueductInterface.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceTest.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook28.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook30.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceEmbedded.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceMemcached.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceArc2.php');
require_once('extensions/AqueductExtension/includes/AqueductUtility.php');
require_once('extensions/AqueductExtension/arc/ARC2.php');

// Prepopulates a page when you follow a red link to it. Currently only fires when a red link is used.
function aqPopulateNewPage($editpage)
{
	global $wgOut, $wgRequest;
	aqProfile("aq");

	// Check if this is a page created by following a red link to a non-existant page.
	if (!$editpage->mTitle->exists())
	{
		// Grab the title for the page we're going to populate
		$newArticle = $editpage->getArticle();
		$newTitle = $newArticle->getTitle();

		$basicrow = NULL;
		$advancedrow = NULL;
		AqueductUtility::getRowsForAqueductTitle($newTitle, $basicrow, $advancedrow);

		// Actually do the ignoring.
		if ($basicrow === NULL)
		{
			return true;
		}

		// Decide what article to use as a template for the new page.
		if ($advancedrow !== NULL)
		{
			$insertTitle = Title::newFromText('Template:' . $advancedrow['aq_wiki_parent_namespace'] . '_' . $advancedrow['aq_wiki_namespace_tag']);
			if (!$insertTitle || !$insertTitle->exists())
			{
				$insertTitle = null;
			}
		}
		else
		{
			$insertTitle = Title::newFromText('Template:' . $basicrow['aq_wiki_namespace']);
			if (!$insertTitle || !$insertTitle->exists())
			{
				$insertTitle = null;
			}
		}
		
		if ($insertTitle)
		{
			try
			{
				// Materialize the entity to check the RDF type, we may be able to use a more specific template.
				if ($advancedrow === NULL)
				{
					$interface = AqueductUtility::getInterfaceForRow($basicrow);
			
					// This $result contains the RDF from which we need to collect the RDF type to compare against templates.
					$result = $interface->materialize($newTitle);
	
					foreach ($result as $r => $record)
					{
						foreach ($record as $s => $subj)
							{
							if (AqueductInterface::uriToTitle($s) == $newTitle)
							{
								if ($result[$r][$s]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])
								{
									$newInsertTitle = $result[$r][$s]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][0]['value']; 
									$newInsertTitle = AqueductInterface::uriToTitle($newInsertTitle); 
									$newInsertTitle = Title::newFromText('Template:' . $newInsertTitle);
								}
							}
						}
						break;
					}
				}			
			}
			catch (Exception $e)
			{
				unset($newInsertTitle);
			}

			if ($newInsertTitle && $newInsertTitle->exists())
			{
				$insertTitle = $newInsertTitle;
			}
			
			// Collect the article to use as a template for the new page.
			$insertArticle = new Article($insertTitle);

			// Get the template used to populate this page and write it to the new page.
			$newArticle->doEdit($insertArticle->getRawText(), "Page Pre-populated using the Aqueduct Page Population Extension.");	

			// Redirect to the new page so the user doesn't even know it didn't exist.
			$wgOut->redirect($newTitle->getFullURL());

			// Make sure the user doesn't ever see the edit window.
			aqProfile("mw");
			return false;
		}
		else
		{
			aqProfile("mw");
			return true;
		}
	}
	// Any other edits should continue normally.
	aqProfile("mw");
	return true;
}
?>
