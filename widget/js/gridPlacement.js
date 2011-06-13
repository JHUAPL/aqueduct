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
/*
Grid placement engine
Manages progressive rendering of complex layouts by managing the functionality of the jquery.layout engine
*/

/*
Widget tree
This keeps track of the layout tree and the current state of the progressive rendering
The widgetTree objects contain fields but no methods (this is a simple data structure)

Tree node fields:
parent: Parent tree node, or null if root container
isContainer: Does this cell represent a grid layout container that has children (true), or a widget instance (false)?
finalHeight: Final height of this grid cell in pixels.
finalWidth
desHeight: Desired height of this cell. Equals an integer number of pixels, "u" (unknown pending a prerender operation). Final height may end up being more.
desWidth
wantsHeight: From 0-2, how badly the node wants any extra height not taken up by anything else. (There would be a better way to do this, but it's really complicated.)
wantsWidth
givesHeight: From 0-3, how easily this node can give up desired height if there isn't enough space. There would be a better way to do this, but it's really complicated.)
givesWidth
elem: The jQuery element of this node.
elemId: The id that the element will have. Will be similar to 'aqgrid-center-north'
sizingFinished: Indicates that finalHeight/finalWidth have been set.
placed: Indicates that the object (grid container or widget) has been finally placed in the visible portion of the page. Rendering is done when whole tree has placed=true
resizable: Indicates if a slider bar should be drawn to resize this node (when applicable)

Container node fields:
childN:Another widget or container node (north)
childS: (south)
childW: (west)
childE: (east)
childC: (center)
layoutFinished: Indicates that the container's grid line positions have been computed and sizingFinished is true for all the children

Widget node fields:
props: The property array that sufficently defines the type of widget and the query to be performed
isPresizing: The widget is currently doing a presize rendering, so it cannot be placed until this is finished
isWikiText: This widget displays the wikitext instead of being an Aqueduct widget


*/

/*
GLOBAL VARIABLES
*/

var aqWidgetTree = null;
var aqGridDidPresize = false;
var aqGridPendingPresize = 0;
var aqGridResizeMode = true;
//Grid Resizer bar size (set to match CSS)
var aqGridResizerSize = 6;
//Border and padding of one side of grid cells. Grid cells should not have a margin. Set these to match what is in the CSS file
var aqGridCellBorder = 1;
var aqGridCellPadding = 10;
//Min size of visible content AND border and padding in grid cell (add double the border and padding to desired size of visible content)
var aqGridMinSize = 72;
/*
PUBLIC FUNCTIONS BELOW
*/

/*
Call this method first, to notify the engine how much height and width it has to work with
*/
function aqWidgetCreateRootNode(height,width)
{
	aqWidgetTree = {'parent':null, 'isContainer':true, desHeight:height, desWidth:width,
		finalHeight:height, finalWidth:width,
		placed:false, sizingFinished:true, layoutFinished:false, elem:jQuery('#aqgrid'),
		childN:null, childW:null, childE:null, childS:null, childC:null,  resizable:false};
}

/*
Next, call this method for each widget
Incrementally build the tree by adding all its widgets
position is a user-supplied widget positioning string (example:center-north)
height and widget are user-supplied height and width strings ('173','fit','')
props is the widget properties
*/
function aqAddWidgetToTree(position, height, width, props, isWikiText, resizable)
{
	var fHeight;
	var fWidth;
	var wHeight;
	var wWidth;
	var gHeight;
	var gWidth;
	var canResize = resizable?true:false;
	if (height == '')
	{
		fHeight = aqGridMinSize;
		wHeight = 4;
		gHeight = 2;
	}
	else if (height == 'fit')
	{
		fHeight = 'u';
		wHeight = 2;
		gHeight = 3;
	}
	else
	{
		fHeight = parseInt(height);
		wHeight = 0;	
		gHeight = 2;
	}
	if (fHeight<1) throw "Bad height string!";
	if (width == '')
	{
		wWidth = 4;
		fWidth = aqGridMinSize;
		gWidth = 2;
	}
	else if (width == 'fit')
	{
		wWidth = 2;
		fWidth = 'u';
		gWidth = 3;
	}
	else
	{
		wWidth = 0;
		fWidth = parseInt(width);
		gWidth = 2;
	}
	if (fWidth<1) throw "Bad width string!";

	var pos = position.split("-");
	if (pos.length==0) throw "Empty widget position string!";
	var currentNode = aqWidgetTree;
	var p = '';	
	for (i in pos)
	{
		if (p!='')
		{
			if (currentNode[p] == null)
				currentNode[p] = {'parent':currentNode, 'isContainer':true, desHeight:'u', desWidth:'u',
				placed:false, elem:null, sizingFinished:false, layoutFinished:false,
				childN:null, childW:null, childE:null, childS:null, childC:null, resizable:false};
			currentNode = currentNode[p];
		}
		switch(pos[i])
		{
			case 'north':
			p = 'childN';
			break;
			case 'south':
			p = 'childS';
			break;
			case 'west':
			p = 'childW';
			break;
			case 'east':
			p = 'childE';
			break;
			case 'center':
			p = 'childC';
			break;
			default:
			throw "Bad widget position string";
		}
	}
	
	if (currentNode[p] != null) 
		throw "Widgets occupy the same position";
	currentNode[p] = {'parent':currentNode, 'isContainer':false, desHeight:fHeight, desWidth:fWidth,
				wantsHeight:wHeight, wantsWidth:wWidth, placed:false,  sizingFinished:false, elem:null,
				props:props, givesHeight:gHeight, givesWidth:gWidth, isPresizing:false, isWikiText:isWikiText, resizable:canResize};
}

/*
The user may ask to place widgets with no explicit position setting, but this API requires the position to always be specified
Find places to put the requested number of widgets
*/
function aqFindEmptyPositions(howmany)
{
	var emptycells = [];
	var atdepth = 0;
	while (emptycells.length < howmany)
	{
		atdepth++;
		var oldlen = emptycells.length;
		emptycells = aqFindEmptyCells(aqWidgetTree,'',atdepth);
		if (emptycells.length == oldlen)
			break;
	}
	while (emptycells.length < howmany)
	{
		//Not enough cells remain -- we must split a top-level empty cell into a container and retry.
		if (emptycells.length == 0)
			throw 'There is no room to auto-position any widgets';
		var ccell = emptycells.shift();
		if (ccell.indexOf('-') >= 0)
			throw 'There is not enough room to auto-position all the widgets';
		//Split ccell
		emptycells.push(ccell+'-center');
		emptycells.push(ccell+'-north');
		emptycells.push(ccell+'-south');
		emptycells.push(ccell+'-west');
		emptycells.push(ccell+'-east');
	}
	return emptycells;		
}

/*
Last, call this method to load the widgets and build the grid
*/
function aqBuildWidgetGrid()
{
	jQuery('body').css('overflow','hidden');

	aqWidgetVerifyAndFixTree(aqWidgetTree, '');
	aqWidgetDesiredSizeUp(aqWidgetTree);
	aqWidgetUpdateTree(aqWidgetTree);
	//At this point, some parts of the grid may be built and some may be not
	//Begin the presizing/prerendering process to determine actual sizes of some of the other nodes. Events will be fired
	//as the presizing completes which will call desiredsize/updatetree as needed to finish the rendering
	aqWidgetBeginPresizing(aqWidgetTree);

	//Destroy presizing container if nothing else needs to be async presized
	if (aqGridPendingPresize == 0)
	{
		jQuery('#gridPresizer').remove();
		//Break out of unnecessary scrollbars
		jQuery('body').css('overflow','auto');
	}
	//If things are async presizing, this line will allow the container to be destroyed later
	aqGridDidPresize = true;
}

/*
Call this method before resizing, to remove the widget grid
The widgets will be saved in a container for later
*/
function aqRemoveWidgetGrid()
{
	var tempcontainer = jQuery('<div id="aqgridtemp" style="display:none"></div>');
	aqWidgetTree.elem.after(tempcontainer);
	//Save all of the old widgets
	aqRemoveWidgetsFromGrid(aqWidgetTree,tempcontainer);
	aqWidgetTree.elem.remove();
}

/*
Call this method when the grid is complete (all widgets loaded) but the browser window is resized
Only call the method after the old widget grid has been removed
*/
function aqResizeWidgetGrid(height,width)
{
	//Because jquery-layout does not handle this well, we must tear down the entire layout and build it again
	var tempcontainer = jQuery('#aqgridtemp');
	var newgrid = jQuery('<div id="aqgrid"></div>');
	newgrid.css('width',width).css('height',height);
	aqWidgetTree.finalWidth = width;
	aqWidgetTree.finalHeight = height;
	aqWidgetTree.elem = newgrid;
	tempcontainer.before(newgrid);
	//Build the new grid, which will reuse the old widgets because aqGridResizeMode=true
	aqGridResizeMode = true;
	aqWidgetUpdateTree(aqWidgetTree);
	aqGridResizeMode = false;
	tempcontainer.remove();
}


/*
PRIVATE FUNCTIONS BELOW
*/

function aqRemoveWidgetsFromGrid(node, moveto)
{
	if (node == null)
		return;
	if (node.isContainer)
	{
		aqRemoveWidgetsFromGrid(node.childN, moveto);
		aqRemoveWidgetsFromGrid(node.childW, moveto);
		aqRemoveWidgetsFromGrid(node.childE, moveto);
		aqRemoveWidgetsFromGrid(node.childS, moveto);
		aqRemoveWidgetsFromGrid(node.childC, moveto);
	}
	else
	{
		//Create a new jQuery object too, to ensure no interference with old container
		var baseelem = document.getElementById(node.elemId);
		baseelem.parentNode.removeChild(baseelem);
		node.elem = jQuery(baseelem);
		moveto.append(node.elem);
	}
}

function aqFindEmptyCells(cell, name, maxdepth)
{
	if (cell == null)
	{
		return [name];
	}
	var result = [];
	if (cell.isContainer && maxdepth>0)
	{
		var newname = name;
		if (name!="")
			newname = name + "-";
		result=result.concat(aqFindEmptyCells(cell.childC, newname+'center',maxdepth-1));
		result=result.concat(aqFindEmptyCells(cell.childN, newname+'north',maxdepth-1));
		result=result.concat(aqFindEmptyCells(cell.childS, newname+'south',maxdepth-1));
		result=result.concat(aqFindEmptyCells(cell.childW, newname+'west',maxdepth-1));
		result=result.concat(aqFindEmptyCells(cell.childE, newname+'east',maxdepth-1));
	}
	return result;
}

/*
Call after all widgets placed on tree. Performs the following functions:
If there is no "center" cell in a grid, attempt to make one
Set IDs
Set wantsHeight, wantsWidth, fittingHeight, fittingWidth
*/
function aqWidgetVerifyAndFixTree(node, position)
{
	if (node==null)
		return;
	node.elemId = 'aqgrid';
	if (node.parent)
		node.elemId = node.parent.elemId + '-' + position;
	if (node.isContainer)
	{
		node.clientsW = 0;
		node.clientsH = 0;
		if (!node.childC)
		{
			if (!node.childN && !node.childS)
			{
				if (node.childW)
				{
					node.childC = node.childW;
					node.childW = null;
				}
				else if (node.childE)
				{
					node.childC = node.childE;
					node.childE = null;
				}
			}
			else if (!node.childW && !node.childE)
			{
				if (node.childN)
				{
					node.childC = node.childN;
					node.childN = null;
				}
				else if (node.childS)
				{
					node.childC = node.childS;
					node.childS = null;
				}
			}
		}
		if (!node.childC)
			throw "Nodes were placed such that the center of the grid is empty";
		aqWidgetVerifyAndFixTree(node.childC, 'center');
		aqWidgetVerifyAndFixTree(node.childN, 'north');
		aqWidgetVerifyAndFixTree(node.childS, 'south');
		aqWidgetVerifyAndFixTree(node.childW, 'west');
		aqWidgetVerifyAndFixTree(node.childE, 'east');
		node.wantsHeight = node.childC.wantsHeight;
		node.wantsWidth = node.childC.wantsWidth;
		node.givesHeight = node.childC.givesHeight & 1;
		node.givesWidth = node.childC.givesWidth & 1;
		if (node.childN)
		{
			node.wantsHeight = Math.max(node.wantsHeight,node.childN.wantsHeight|1);
			node.givesHeight = Math.max(node.givesHeight,node.childN.givesHeight&1);
			node.wantsWidth = Math.max(node.wantsWidth,node.childN.wantsWidth|1);
			node.givesWidth = Math.max(node.givesWidth,node.childN.givesWidth&1);
		}
		if (node.childS)
		{
			node.wantsHeight = Math.max(node.wantsHeight,node.childS.wantsHeight|1);
			node.givesHeight = Math.max(node.givesHeight,node.childS.givesHeight&1);
			node.wantsWidth = Math.max(node.wantsWidth,node.childS.wantsWidth|1);
			node.givesWidth = Math.max(node.givesWidth,node.childS.givesWidth&1);
		}
		if (node.childW)
		{
			node.wantsHeight = Math.max(node.wantsHeight,node.childW.wantsHeight|1);
			node.givesHeight = Math.max(node.givesHeight,node.childW.givesHeight&1);
			node.wantsWidth = Math.max(node.wantsWidth,node.childW.wantsWidth|1);
			node.givesWidth = Math.max(node.givesWidth,node.childW.givesWidth&1);
		}
		if (node.childE)
		{
			node.wantsHeight = Math.max(node.wantsHeight,node.childE.wantsHeight|1);
			node.givesHeight = Math.max(node.givesHeight,node.childE.givesHeight&1);
			node.wantsWidth = Math.max(node.wantsWidth,node.childE.wantsWidth|1);
			node.givesWidth = Math.max(node.givesWidth,node.childE.givesWidth&1);
		}
	}
}

/*Start at a leaf and compute desired sizes down to the tree root
When you only need one path from the leaf to the root to be computed
*/
function aqWidgetDesiredSizeDown(node)
{
	aqWidgetDesiredSize(node);
	if (node.parent)
		aqWidgetDesiredSizeDown(node.parent);
}

/*Compute desired sizes for the entire tree
postorder tree traversal
*/
function aqWidgetDesiredSizeUp(node)
{
	if (!node)
		return;
	aqWidgetDesiredSizeUp(node.childN);
	aqWidgetDesiredSizeUp(node.childS);
	aqWidgetDesiredSizeUp(node.childE);
	aqWidgetDesiredSizeUp(node.childW);
	aqWidgetDesiredSizeUp(node.childC);
	aqWidgetDesiredSize(node);
}

/*
Internal function: Attempt to compute the desired height and width of this container node
*/
function aqWidgetDesiredSize(node)
{
	if (node.isContainer)
	{
		//If all children can compute their desired height and width, then this container can
		if (node.desHeight == 'u')
		{
			var knowHeight = true;
			var totHeight = 0;
			var centerHeight = 0;
			
			if (node.childN)
				if (node.childN.desHeight != 'u') totHeight += node.childN.desHeight; else knowHeight = false;
			if (node.childS)
				if (node.childS.desHeight != 'u') totHeight += node.childS.desHeight; else knowHeight = false;
			if (node.childC.desHeight != 'u') centerHeight = node.childC.desHeight; else knowHeight = false;
			if (node.childW)
				if (node.childW.desHeight != 'u') centerHeight = Math.max(centerHeight,node.childW.desHeight); else knowHeight = false;
			if (node.childE)
				if (node.childE.desHeight != 'u') centerHeight = Math.max(centerHeight,node.childE.desHeight); else knowHeight = false;
			if (knowHeight)
				node.desHeight = totHeight + centerHeight;
		}
		if (node.desWidth == 'u')
		{
			var knowWidth = true;
			var totWidth = 0;
			
			if (node.childW)
				if (node.childW.desWidth != 'u') totWidth += node.childW.desWidth; else knowWidth = false;
			if (node.childE)
				if (node.childE.desWidth != 'u') totWidth += node.childE.desWidth; else knowWidth = false;
			if (node.childC.desWidth != 'u') totWidth += node.childC.desWidth; else knowWidth = false;
			if (node.childN)
				if (node.childN.desWidth != 'u') totWidth = Math.max(totWidth,node.childN.desWidth); else knowWidth = false;
			if (node.childS)
				if (node.childS.desWidth != 'u') totWidth = Math.max(totWidth,node.childS.desWidth); else knowWidth = false;
			if (knowWidth)
				node.desWidth = totWidth;
		}
	}
}

//Searches the tree for widgets that are not placed and not presizing with unknown desired or final size, and begins presizing them
function aqWidgetBeginPresizing(node)
{
	if (!node)
		return;
	if (node.isContainer)
	{
		aqWidgetBeginPresizing(node.childN);
		aqWidgetBeginPresizing(node.childS);
		aqWidgetBeginPresizing(node.childW);
		aqWidgetBeginPresizing(node.childE);
		aqWidgetBeginPresizing(node.childC);
	}
	else
	{
		if (!node.placed && !node.isPresizing && !node.sizingFinished && (node.desHeight == 'u' || node.desWidth == 'u'))
		{
			node.isPresizing = true;
			node.elem = jQuery('<div style="overflow:visible;margin:0px;padding:0px" id="'+node.elemId+'"></div>');
			node.elem.css('width',node.desWidth=='u'?'auto':node.desWidth)
			.css('height',node.desWidth=='u'?'auto':node.desHeight)
			//Add a nesting container with a border to measure collapsed-out margins from widget content
			var outerelem = jQuery('<div style="overflow:visible;border:1px solid;padding:0px;clear:both;float:left"></div>');
			outerelem.append(node.elem);
			jQuery('#gridPresizer').append(outerelem);
			var w = node.props;
			aqGridPendingPresize++;
			var fnComplete = function(o)
			{
				if (node.desWidth == 'u')
				{
					node.desWidth = Math.ceil(node.elem.attr('scrollWidth'))+(aqGridCellPadding+aqGridCellBorder)*2;
				}
				//For height, compensate for collapsing margins
				//Don't  do this for width, horizontal text margins are less common and horizontal flow works differently anyway
				if (node.desHeight == 'u')
				{
					var vertPadding = Math.max(0,Math.ceil(
						aqGridCellPadding-(outerelem.attr('scrollHeight') - node.elem.attr('scrollHeight'))/2));
					node.desHeight = Math.ceil(outerelem.attr('scrollHeight'))+(vertPadding+aqGridCellBorder)*2;
					node.elem.css('padding-top',vertPadding + 'px');
					node.elem.css('padding-bottom',vertPadding + 'px');
				}
				node.elem.css('padding-left',aqGridCellPadding + 'px');
				node.elem.css('padding-right',aqGridCellPadding + 'px');
				node.isPresizing = false;
				//Prevent memory leak
				if (o)
					o.gridNotifier = null;
				//See if more layout can be done in response to the presizing
				aqWidgetDesiredSizeDown(node);
				aqWidgetUpdateTree(aqWidgetTree);
				//Destroy empty grid container if I was the last one to use it
				aqGridPendingPresize--;
				if (aqGridDidPresize && aqGridPendingPresize == 0)
				{
					jQuery('#gridPresizer').remove();
					//Break out of unnecessary scrollbars
					jQuery('body').css('overflow','auto');
				}
			};
			if (node.isWikiText)
			{
				node.elem.append(jQuery('#bodyContent'));
				//Sync presize
				fnComplete(null);
			}
			else
			{
				//Async presize
				new CisternWidget(node.elemId,w[0],w[1],w[2],w[3],w[4],fnComplete);
			}
		}	
	}
}


//Searches the tree for things can be updated, such as:
//Lay out containers
//Place anything that can be placed.
//Things can be placed if the parent node has been placed (in the case of containers), and
//widget: Widget is not prerendering and sizingFinished is true
//container: there are no widget children presizing, and layoutFinished=true
//widgets are always placed at the same time as their parent nodes
function aqWidgetUpdateTree(node)
{
	//Can never do anything to this node or the children if it can't be sized
	if (!node || !node.sizingFinished || !node.isContainer)
		return;
	if (!node.placed || aqGridResizeMode)
	{
		if (!node.layoutFinished || aqGridResizeMode)
			aqWidgetLayoutContainer(node);
		if (node.layoutFinished && node.elem)
		{
			//See if we can place container and its widgets
			if ((!node.childN || node.childN.isContainer || !node.childN.isPresizing) &&
				(!node.childS || node.childS.isContainer || !node.childS.isPresizing) &&
				(!node.childW || node.childW.isContainer || !node.childW.isPresizing) && 
				(!node.childE || node.childE.isContainer || !node.childE.isPresizing) &&
				(!node.childC || node.childC.isContainer || !node.childC.isPresizing))
			{
				//We can place this container and its widgets
				var place = function(child)
				{
					if (child)
					{
						if (child.isContainer)
						{
							//If child.elem exists, it's a reference to a pre-resizing grid cell that is no longer needed
							child.elem = jQuery('<div id="'+child.elemId+'" style="padding:0px;border:0px"></div>');
							node.elem.append(child.elem);
								child.callme = function ()
								{
									//Currently do nothing
								}
						}
						else
						{
							//Placing a widget. Does it already exist from prerendering or because we are resizing?
							if (child.elem)
							{
								//Move the widget from the prerendering/resizing area into the grid
								//Resize it first so it doesn't mess up the size of the grid container
								child.elem.css('overflow','auto');
								child.elem.css('width',child.finalWidth);
								child.elem.css('height',child.finalHeight);
								child.callme = function ()
								{
									//Currently do nothing
								}
							}
							else
							{
								child.elem = jQuery('<div id="'+child.elemId+'"></div>');
								var w = child.props;
								child.callme = function()
								{
									if (child.isWikiText)
										child.elem.append(jQuery('#bodyContent'));
									else
										new CisternWidget(child.elemId,w[0],w[1],w[2],w[3],w[4],null);
									//Prevent memory leaks
									child.callme = null;
								};
							}
							node.elem.append(child.elem);
							child.placed = true;
						}
					}				
				};
				//Create the grid cells and their content
				place(node.childN);
				place(node.childS);
				place(node.childW);
				place(node.childE);
				place(node.childC);
				//Create the grid itself
				var layoutOptions = {defaults:{closable:false,resizable:false,slidable:false},
									center:{paneSelector:'#'+node.childC.elemId}};
				if (node.childN)
					layoutOptions['north'] = 
					{paneSelector:'#'+node.childN.elemId, size:node.childN.finalHeight, resizable:node.childN.resizable};
				if (node.childS)
					layoutOptions['south'] = 
					{paneSelector:'#'+node.childS.elemId, size:node.childS.finalHeight, resizable:node.childS.resizable};
				if (node.childW)
					layoutOptions['west'] = 
					{paneSelector:'#'+node.childW.elemId, size:node.childW.finalWidth, resizable:node.childW.resizable};
				if (node.childE)
					layoutOptions['east'] = 
					{paneSelector:'#'+node.childE.elemId, size:node.childE.finalWidth, resizable:node.childE.resizable};
				node.elem.layout(layoutOptions);
				//Now that the pane is created, allow the widgets to construct themselves
				if (node.childN)
					node.childN.callme();
				if (node.childS)
					node.childS.callme();
				if (node.childE)
					node.childE.callme();
				if (node.childW)
					node.childW.callme();
				node.childC.callme();
				node.placed = true;
			}
		}
	}
	//If we could place this element or not, still update the child nodes
	aqWidgetUpdateTree(node.childN);
	aqWidgetUpdateTree(node.childS);
	aqWidgetUpdateTree(node.childW);
	aqWidgetUpdateTree(node.childE);
	aqWidgetUpdateTree(node.childC);
}

/*
Internal function: Attempt to compute the final height and width of this cell
Optimization strategy: If all children except one have a desired height/width and one child wants height/width more than the others, then assign it all to that child
*/
function aqWidgetLayoutContainer(node)
{
	var rowDesHeightN = node.childN?node.childN.desHeight:0;
	var rowDesHeightS = node.childS?node.childS.desHeight:0;
	var rowDesHeightC = ((node.childW && node.childW.desHeight=='u')
						|| (node.childE && node.childE.desHeight=='u')
						|| (node.childC.desHeight=='u'))?'u':
							Math.max((node.childW?node.childW.desHeight:0) , 
							(node.childE?node.childE.desHeight:0) , 
							node.childC.desHeight);
							
	var rowWantsHeightN = node.childN?node.childN.wantsHeight:-1;
	var rowWantsHeightS = node.childS?node.childS.wantsHeight:-1;
	var rowWantsHeightC = Math.max(node.childW?node.childW.wantsHeight:-1
									,node.childE?node.childE.wantsHeight:-1
									,node.childC.wantsHeight);
	var rowGivesHeightN = node.childN?node.childN.givesHeight:-1;
	var rowGivesHeightS = node.childS?node.childS.givesHeight:-1;
	var rowGivesHeightC = Math.max(node.childW?node.childW.givesHeight:-1
									,node.childE?node.childE.givesHeight:-1
									,node.childC.givesHeight);
	var colDesWidthW = node.childW?node.childW.desWidth:0;
	var colDesWidthE = node.childE?node.childE.desWidth:0;
	var colDesWidthC = node.childC.desWidth;
	var colWantsWidthW = node.childW?node.childW.wantsWidth:-1;
	var colWantsWidthE = node.childE?node.childE.wantsWidth:-1;
	var colWantsWidthC = node.childC.wantsWidth;
	var colGivesWidthW = node.childW?node.childW.givesWidth:-1;
	var colGivesWidthE = node.childE?node.childE.givesWidth:-1;
	var colGivesWidthC = node.childC.givesWidth;
	
	var availHeight = node.finalHeight - (node.childN?aqGridResizerSize:0) - (node.childS?aqGridResizerSize:0);
	var availWidth = node.finalWidth - (node.childW?aqGridResizerSize:0) - (node.childE?aqGridResizerSize:0);
	
	var makeLayout = function(total, needs1, needs2, needs3, wants1, wants2, wants3, gives1, gives2, gives3)
	{
		var maxRequest = Math.max(wants1, wants2, wants3);
		var distMask = (maxRequest==wants1?1:0) +
						(maxRequest==wants2?2:0) +
						(maxRequest==wants3?4:0);
		var distCount = (maxRequest==wants1?1:0) + (maxRequest==wants2?1:0) + (maxRequest==wants3?1:0);
		var unkMask = (needs1=='u'?1:0) +
						(needs2=='u'?2:0) +
						(needs3=='u'?4:0);
		if (!(unkMask == 0 || (distCount==1 && unkMask==distMask)))
			return false;
		
		//Either all desHeight are not 'u', or one is 'u' and it is the one indicated for flexible height by distMask
		//(or width)
		var totalDes = (needs1=='u'?aqGridMinSize:needs1) +
							(needs2=='u'?aqGridMinSize:needs2) +
							(needs3=='u'?aqGridMinSize:needs3);
		var extra = Math.max(0,total - totalDes);
		var extraPerItem = Math.floor(extra / distCount);
		var final1 = ((distMask & 1)?extraPerItem:0) +
							(needs1=='u'?aqGridMinSize:needs1);
		var final2 = ((distMask & 2)?extraPerItem:0) +
							(needs2=='u'?aqGridMinSize:needs2);
		var final3 = ((distMask & 4)?extraPerItem:0) +
							(needs3=='u'?aqGridMinSize:needs3);
		//Final grid cell sizes calculated for normal situations. Now handle these exceptional situations:
		//Not all width/height used due to rounding errors
		//Too much height/width used
		var error = final1+final2+final3 - total;
		if (error > 0)
		{
			for (var x=3;(x>=0 && error>0);x--)
			{
				for (var tries=0;(tries<3 && error>0);tries++)
				{
					var try1 = (gives1 == x && final1>aqGridMinSize)?1:0;
					var try2 = (gives2 == x && final2>aqGridMinSize)?1:0;
					var try3 = (gives3 == x && final3>aqGridMinSize)?1:0;
					var tryCount = try1+try2+try3;
					var subtract = Math.ceil(error/tryCount)
					if (tryCount>0 && error>0)
					{
						if (try1)
							final1 = Math.max(aqGridMinSize,final1-subtract);
						if (try2)
							final2 = Math.max(aqGridMinSize,final2-subtract);
						if (try3)
							final3 = Math.max(aqGridMinSize,final3-subtract);
						error = final1+final2+final3 - total;
					}
				}
			}				
		}
		if (error > 0)
		{
			//With better algorithms we could often avoid this condition
			return [final1,final2,final3,null,true];
		}
		else
		{
			final2 -= error;
			return [final1,final2,final3,false];
		}
	}
	
	var heightLayout = makeLayout(availHeight, 
		rowDesHeightN, rowDesHeightC, rowDesHeightS,
		rowWantsHeightN, rowWantsHeightC, rowWantsHeightS,
		rowGivesHeightN, rowGivesHeightC, rowGivesHeightS);
	var widthLayout = false;
	if (heightLayout)
		widthLayout = makeLayout(availWidth, 
			colDesWidthW, colDesWidthC, colDesWidthE,
			colWantsWidthW, colWantsWidthC, colWantsWidthE,
			colGivesWidthW, colGivesWidthC, colGivesWidthE);
	if (widthLayout)
	{
		var setDims = function(child, h, w)
		{
			if (child)
			{
				child.sizingFinished = true;
				child.finalHeight = h;
				child.finalWidth = w;
			}
		}
		//Height and width of every cell in this grid has finally been determined
		setDims(node.childN, heightLayout[0], node.finalWidth);
		setDims(node.childS, heightLayout[2], node.finalWidth);
		setDims(node.childW, heightLayout[1], widthLayout[0]);
		setDims(node.childC, heightLayout[1], widthLayout[1]);
		setDims(node.childE, heightLayout[1], widthLayout[2]);
		if (heightLayout[3] || widthLayout[3])
			alert("Warning: Window is too small to display all data");
		node.layoutFinished = true;
	}
}
