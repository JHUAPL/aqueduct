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
require_once('extensions/AqueductExtension/includes/AqueductDbCalls.php');
require_once('extensions/AqueductExtension/includes/AqueductInterface.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceTest.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceEmbedded.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook28.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook30.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceMemcached.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceArc2.php');
require_once('extensions/AqueductExtension/arc/ARC2.php');

class ApiAqueductSet extends ApiBase
{
	public function __construct($main, $action)
	{
		parent :: __construct($main, $action);
	}

	public function execute()
	{
		aqProfile("aq");
		$params = $this->extractRequestParams();

		// If subject is a title, create the object, otherwise, use uritotitle.
		if (!isset($params['subjecttype']) || $params['subjecttype']== 'page')
		{
			$t = Title::newFromText($params['subject']);
		}
		else if ($params['subjecttype']== 'uri')
		{
			$t = Title::newFromText(AqueductInterface::uriToTitle($params['subject']));
			//Here we could end up with a title in the Unknown namespace. If we did, this will cause a crash soon
		}
		else
		{
			throw new Exception('Invalid subject type');
		}	
		$result = $this->doHardSet($t,$params['predicateURI'], $params['object'], $params['objecttype']);
		aqProfile("mw");
		return $result;
	}
	
	protected function doHardSet($title, $pred, $obj, $objtype)
	{
		$basicrow = NULL;
		$advancedrow = NULL;
		AqueductUtility::getRowsForAqueductTitle($title, $basicrow, $advancedrow);
		if ($basicrow===NULL || $advancedrow!==NULL)
		{
			//Not a valid hard set
			throw new Exception('The namespace => datasource entry for the hard set was not found.');
		}
		$interface = AqueductUtility::getInterfaceForRow($basicrow);
		$interface->hardSet($title, $pred, $obj, $objtype);
	}
			
	public function getAllowedParams()
	{
		return array ('subject' => null, 'subjecttype' => 'page', 'predicateURI' => null, 'object' => null, 'objecttype' => 'literal');
	}
	
	public function getParamDescription()
	{
		return array ('subject' => 'RDF subject to add the triple to. Formatted only as a wiki title for now.',
			'subjecttype' => 'RDF subject type, either "page" for a wiki title (default), or "uri" for a datasource uri. Currently ignored until subject uri functionality is added.',
			'predicateURI' => 'URI to the RDF predicate to replace the triple for.',
			'object' => 'Either a literal or a datasource uri that will be the object for this subjects new predicate.',
			'objecttype' => 'RDF object type, either "literal" for a literal (default), or "uri" for a datasource uri.');
	}
	
	public function getDescription()
	{
		return array('Aqueduct RDF hard set API.');
	}

	public function getVersion()
	{
		return __CLASS__ . '1.0i';
	}
}
