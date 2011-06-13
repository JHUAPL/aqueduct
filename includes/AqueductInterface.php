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


abstract class AqueductInterface 
{
	protected $mRow;
	
	public function __construct($row)
	{
		$this->mRow = $row;
	}
	
	public abstract function materialize($title);
	
	public abstract function advancedOperation($title,$advancedrow);
	
	//Sets one triple, doesn't disturb any triples with the same subject
	public abstract function hardSet($title, $predicateURI, $object, $objecttype);
	
	public function titleToURI($title)
	{
		//1. Get the title text
		$t = $title->getDBkey();
		//Flip the title case if needed
		if ($this->mRow['aq_initial_lowercase'])
		{
			$t = strtolower(substr($t,0,1)) . substr($t,1);
		}
		
		//2. If the URI begins with a backslash \, remove it. If the URI ends with a caret ^ , remove it.
		if ($t[0] == '\\')
		{
			$t = substr($t,1);
		}
		if ($t[strlen($t)-1] == '^')
		{
			$t = substr($t,0,strlen($t)-1);
		}
		//3. Process caret ^ escape sequences. A caret-period ^. is converted to a period. A caret-a ^a is converted to an ampersand & . A caret-underscore ^_ is converted to an underscore. A caret-tilde ^~ is converted to a tilde. Other such sequences are illegal.
		//4. Convert backticks ` to colons :
		//5. Convert backslashes \ to hash marks #
		$t = str_replace(array('^.','^a','^_','^~','`','\\'),array('.','&','_','~',':','#'),$t);
		if (strpos($t,'^') !== FALSE)
		{
			throw new Exception('Title has an illegal caret escape sequence');
		}
		
		//6. Convert characters that are illegal in URIs to UTF-8 octet sequences (with the percent sign). Convert one character at a time. If a percent-dash octet sequence is encountered (as described above), keep it as a single-percent octet sequence.
		$legalcharpattern = '|[-[:alnum:];/?:@&=+$_.!~*(),\'#]|S';
		$result = '';
		$pos = 0;
		$length = strlen($t);
		while ($pos < $length)
		{
			if ($t[$pos] == '%')
			{
				//Percent dash encoded octet?
				if ($pos+4 > $length || $t[$pos+1] != '-')
				{
					throw new Exception('Unescaped title illegally has a percent-encoded octet sequence. This usually happens because the title was escaped multiple times.');
				}
				else
				{
					//Convert percent-dash encoded octet to a normally encoded octet.
					$result .= $t[$pos] . $t[$pos+2] . $t[$pos+3];
				}
				$pos += 4;
			}
			else
			{
				if (preg_match($legalcharpattern,$t[$pos])>0)
				{
					//Do not escape legal URI character.
					$result .= $t[$pos];
				}
				else
				{
					//Escape illegal URI character
					//The title object is already a UTF-8 encoded mbstring, so this will handle Unicode characters properly
					$result .= '%' . strtoupper(bin2hex($t[$pos]));
				}
				$pos ++;
			}		
		}
		//Add URI prefix
		$result = $this->mRow['aq_source_uri'] . $result;
		
		return $result;
	}
	
	public static function uriToTitle($uri)
	{
		$transtable = aqDbGetTransTable();
		$legalchars = '[-[:alnum:];/?:@&=+$_.!~*(),\'#]';
		$legalcharpattern = '|' . $legalchars . '|S';
		$legalstringpattern = '|^' . $legalchars . '+$|SD';
		//1. Check for illegal characters
		if (preg_match($legalstringpattern,$uri)==0)
		{
			throw new Exception("Illegal URI: $uri");
		}
		//2. Detect which URI prefix is being used to select the configuration row, and remove the URI prefix
		$matchingrow = NULL;
		foreach ($transtable as $row)
		{
			//Use a configuration row if the URI prefix matches and another configuration row with a better (longer) prefix was not found
			if (strpos($uri,$row['aq_source_uri']) === 0)
			{
				if (!$matchingrow || strlen($matchingrow['aq_source_uri']) < strlen($row['aq_source_uri']))
				{
					$matchingrow = $row;
				}
			}
		}
		if ($matchingrow)
		{
			$uri = substr($uri,strlen($matchingrow['aq_source_uri']));
		}
		//3. Convert the sequences of octets into unicode characters.
		$decodeduri = '';
		$currentchar = 0;
		while ($currentchar < strlen($uri))
		{
			$c = $uri[$currentchar];
			if ($c == '%')
			{
				if ($currentchar + 3 > strlen($uri))
				{
					throw new Exception('Malformed escape sequence in URI: $uri');
				}
				$currentseqhex = $uri[$currentchar+1] . $uri[$currentchar+2];
				$currentseqdec = hexdec($currentseqhex);
				$currentseqbin = pack('H*', $currentseqhex);
				if ($currentseqdec < 128)
				{
					//This is a "non-encoded" character (code point <128)
					$encodeme = FALSE;
					if (preg_match($legalcharpattern,$currentseqbin)>0)
					{
						//URL-legal character was unnecessarily encoded. Output this in encoded form to preserve canonicalization
						$encodeme = TRUE;
					}
					else if ($currentseqdec < 32)
					{
						//Keep control characters encoded
						$encodeme = TRUE;
					}
					else if (strpos('<>[]|{}\`^% ',$currentseqbin) !== FALSE)
					{
						//Keep a character that we will not want to use in a wiki title encoded
						$encodeme = TRUE;
					}
					if ($encodeme)
					{
						$decodeduri = $decodeduri . '%-' . $currentseqhex;
					}
					else
					{
						$decodeduri = $decodeduri . $currentseqbin;
					}					
				}
				else
				{
					//This is part of a UTF-8 encoded sequence
					//Just output the byte to the wiki title, because we have nothing else to do with these characters
					$decodeduri = $decodeduri . $currentseqbin;
				}
				$currentchar+=3;
			}
			else
			{
				$decodeduri = $decodeduri . $c;
				$currentchar++;
			}
		}
		//4. The hash mark # could have been present in an unescaped form the URI, which would cause it to remain in the title at this point. 
		//This character is illegal in the wiki. Convert it to a backslash \. If the hash mark was at the beginning of the title, convert it to a double backslash instead
		//5. Colons can be confused for namespace prefixes, so convert them to a backtick `
		$decodeduri = str_replace(array('#',':'),array('\\','`'),$decodeduri);
		if ($decodeduri[0] == '\\')
		{
			$decodeduri = '\\' . $decodeduri;
		}
		//6. Some characters will be conditionally escaped if they will cause problems.
		//Any period not adjacent with a character other than another period or the forward slash is escaped to ^
		//In a sequence of underscores, insert a ^ between all underscores, because Mediawiki will collapse sequences of underscores. Example: ___ turns into _caret_caret_ (caret means ^ )
		//Do the same in a sequence of tildes.
		$currentchar = 0;
		$printedunderscore = FALSE;
		$printedtilde = FALSE;
		$periodokay = FALSE;
		$escapeduri = '';
		for ($currentchar=0; $currentchar<strlen($decodeduri); $currentchar++)
		{
			$c = $decodeduri[$currentchar];
			if ($c == '_')
			{
				if ($printedunderscore)
				{
					$escapeduri .= '^';
				}
				$escapeduri .= '_';
				$printedunderscore = TRUE;
				$printedtilde = FALSE;
				$periodokay = TRUE;
			}
			else if ($c == '~')
			{
				if ($printedtilde)
				{
					$escapeduri .= '^';
				}
				$escapeduri .= '~';
				$printedunderscore = FALSE;
				$printedtilde = TRUE;
				$periodokay = TRUE;
			}
			else if ($c == '.')
			{
				if ($periodokay)
				{
					//The period is "armored" by the character on the left, so just print it
					$escapeduri .= '.';
				}
				else if ($currentchar+1<strlen($decodeduri))
				{
					$n = $decodeduri[$currentchar+1];
					if ($n!='.' && $n!='/')
					{
						//Period armored by the character on the right
						$escapeduri .= '.';
					}
					//Unarmored period
					$escapeduri .= '^.';
				}
				else
				{
					//Unarmored period at the end of the string
					$escapeduri .= '^.';
				}
				$printedunderscore = FALSE;
				$printedtilde = FALSE;
				$periodokay = FALSE;
			}
			else
			{
				$printedunderscore = FALSE;
				$printedtilde = FALSE;
				//Not an underscore or period or tilde, so we don't handle it in this loop.
				$escapeduri .= $c;
				if ($c != '/')
				{
					$periodokay = TRUE;
				}
			}
		}
		//If the URI contains any semicolons ;, escape all ampersands & to ^a
		if (strpos($escapeduri, ';') !== FALSE)
		{
			$escapeduri = str_replace('&','^a',$escapeduri);
		}
		//If the string ends with an underscore at this point, put a caret at the end so Mediawiki does not get rid of the underscore. 
		if ($escapeduri[strlen($escapeduri)-1] == '_')
		{
			$escapeduri .= '^';
		}
		//7.  Logic for characters that Mediawiki will modify only at the beginning of a string
		$prependbackslash = FALSE;
		if (strtoupper($escapeduri[0]) == $escapeduri[0])
		{
			if ($matchingrow && $matchingrow['aq_initial_lowercase'])
			{
				$prependbackslash = TRUE;
			}
		}
		else if (strtolower($escapeduri[0]) == $escapeduri[0])
		{
			if (!$matchingrow || !$matchingrow['aq_initial_lowercase'])
			{
				$prependbackslash = TRUE;
			}
		}
		else if ($escapeduri[0] == '_')
		{
			$prependbackslash = TRUE;
		}
		$escapeduri[0] = strtoupper($escapeduri[0]);
		if ($prependbackslash)
		{
			$escapeduri = '\\' . $escapeduri;
		}
		//8. Prepend the namespace
		if ($matchingrow)
		{
			if ($matchingrow['aq_wiki_namespace_id'] > 0)
			{
				$escapeduri = $matchingrow['aq_wiki_namespace'] . ':' . $escapeduri;
			}
		}
		else
		{
			$escapeduri = 'Unknown:' . $escapeduri;
		}
		return $escapeduri;
	}
}
