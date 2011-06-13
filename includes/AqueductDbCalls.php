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

	function aqDbChooseTable($whichtable)
	{
		global $wgAqueductTblname, $wgAqueductQueryTblname, $wgAqueductOldNSTblname;
		if ($whichtable == 'rdfsource')
		{
			return $wgAqueductTblname;
		}
		else if ($whichtable == 'query')
		{
			return $wgAqueductQueryTblname;
		}
		else if ($whichtable == 'oldns')
		{
			return $wgAqueductOldNSTblname;
		}
		else
		{
			throw new Exception('Invalid table identifier');
		}
	}
	
	// Simple function to get all rows from a table.
	// Takes a table name as an argument.
	// Returns an array of the rows.
	function aqDbTableGetAll ($whichtable = 'rdfsource')
	{
		$result = array();
		$db =& wfGetDB( DB_SLAVE );
		$res = $db->select(aqDbChooseTable($whichtable), '*');
		while($row = $db->fetchRow($res))
		{
			$result[] = $row;
		}
		$db->freeResult($res);
		
		// XXX: Please note that the $db object gives a result that is DOUBLE the size of the row. 
		//      Storing each value under both its column name as a hash value AND under the column number.
		//	This results in an array which is DOUBLE its true length, and if it is used in a loop
		//	trying to iterate over the array, duplicates will appear. The following loop will
		//	REMOVE the numbered duplicates and leave the associated array keyed by column name only.
		/*
		foreach ($result as $rows => $vals)
		{
			for ($i = 0; $i < count($vals) / 2; $i++)
			{
				unset($result[$rows][$i]);
			}
		}
		*/

		return $result;
	}

	// Simple function to insert a row into a table.
	// Takes the table to be modified and the column names and their values as an associated array.
	function aqDbInsertRow ($query, $whichtable = 'rdfsource')
	{
		$db =& wfGetDB( DB_WRITE );
		$db->insert(aqDbChooseTable($whichtable), $query);
	}

	// Simple function to delete a row from a table.
	// Takes the table to be modified and the conditions and their values as an associated array.
	function aqDbDeleteRow ($cond, $whichtable = 'rdfsource')
	{
		$db =& wfGetDB( DB_WRITE );
		$db->delete(aqDbChooseTable($whichtable), $cond);
	}

	// Simple function to replace a row from a table.
	// Takes the table to be modified,
	//	 the unique key of the row to replace,
	//	 and the row columns and values as an associated array.
	function aqDbReplaceRow ($unique, $row, $whichtable = 'rdfsource')
	{
		$db =& wfGetDB( DB_WRITE );
		$db->update(aqDbChooseTable($whichtable), $row, $unique);
	}
	
	//Returns the commonly-used transtable, using an in-memory cache so it is not loaded from the database more than once
	function aqDbGetTransTable()
	{
		global $wgAqueductTransTable;
		if (!isset($wgAqueductTransTable))
			$wgAqueductTransTable = aqDbTableGetAll();
		return $wgAqueductTransTable;
	}

	// Returns an array of the pages that exist in a NS.
	function aqNSHasPages ($ns)
	{
		$result = array();
		$db =& wfGetDB( DB_SLAVE );
		$res = $db->select('page', // from
			'page_title', // what
			array('page_namespace' => $ns)); // where

		while($row = $db->fetchRow($res))
		{
			$result[] = $row['page_title'];
		}
		$db->freeResult($res);

		return $result;
	}
?>
