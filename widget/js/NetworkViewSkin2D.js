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
// USE: The Tableview Skin dumps entities into their own row, and 

// Constructor
function NetworkViewSkin2D(div, baseWidget, useInitialQuery){
	this.map = "";
	this.lat = 40.7494
	this.lon = -73.9681
	this.data = ""
	this.page_subject = baseWidget.initialQuery
	this.placemarks = []

	this.placemarksToAdd = []
	this.linesToAdd = []

	this.icons = {}
	this.visitedNodes = {}

	this.div = jQuery("#"+div)
	this.baseWidget = baseWidget
	this.useInitialQuery = useInitialQuery

	this.div.width(712)
	this.div.height(400)

	this.innerDiv = jQuery('<div id="' + baseWidget.divName + '_innerdiv"></div>')

	this.innerDiv.width(712)
	this.innerDiv.height(400)

	this.div.append(this.innerDiv)

	this.inQuery = false
	
	this.gmInitCallback()
}

NetworkViewSkin2D.prototype.gmInitCallback = function() {
	this.map = new GMap2(this.innerDiv[0]);
	this.map.setCenter(new GLatLng(45.828799,-105.292969), 2);
	this.map.addControl(new GSmallMapControl());

	this.TryRender()
}

NetworkViewSkin2D.prototype.constructLabel = function(entity, preds)
{
	var label = "";


	var uriLink = uriToTitle(entity)

	// root wiki directory
	// XXX: Should probably use wgScript
	var temp = document.location.href.split("index.php")

	label += '<a href="' + temp[0] + "index.php/" + uriLink + '">' + uriLink + '</a><br/>'


	// build up tooltip label here
	if ("http://www.w3.org/2000/01/rdf-schema#label"  in this.data[subject])
	{
		label += "Label: " +String(this.data[subject]["http://www.w3.org/2000/01/rdf-schema#label"].object[0]) + "<br/>"
	}
	return label;
}
NetworkViewSkin2D.prototype.placeAndDraw = function(subject){
	var place
	var plat = false
	var plon = false
	var color = "ffffffff"
	var width = 2
	var label = ""

	if (this.visitedNodes[subject]){
		if (this.placemarks[subject])
			return this.placemarks[subject]
		else
			return false
	}
	else
	{
		if ("http://www.w3.org/2003/01/geo/wgs84_pos#long" in this.data[subject])
		{
			if (!plon)
				plon = []

			for (var index in this.data[subject]["http://www.w3.org/2003/01/geo/wgs84_pos#long"].object)
			{
				this.lon = parseFloat(this.data[subject]["http://www.w3.org/2003/01/geo/wgs84_pos#long"].object[index]);
				plon.push(this.lon);
			}
		}
		if ("http://www.w3.org/2003/01/geo/wgs84_pos#lat" in this.data[subject])
		{
			if (!plat)
				plat = []

			for (var index in this.data[subject]["http://www.w3.org/2003/01/geo/wgs84_pos#lat"].object)		
			{
				this.lat = parseFloat(this.data[subject]["http://www.w3.org/2003/01/geo/wgs84_pos#lat"].object[index]);
				plat.push(this.lat)
			}
		}
		if ("scar:color" in this.data[subject])
			color = String(this.data[subject]["scar:color"].object[0])
		if ("scar:edgewidth" in this.data[subject])
			width = String(this.data[subject]["scar:edgewidth"].object[0])

		label = this.constructLabel(subject, this.data[subject])

		if (plat && plon ){
			if (plat.length == plon.length){

				for(var index in plon){
					
					if (subject in this.placemarks)
						place = this.placemarks[subject][0]
					else{
						// This also seems to do nothing, as no value is returned from this function. 
						place = this.addPlace(plat[index], plon[index], subject, label)
					}
				}

				this.placemarks[subject] = [place, {lat:plat, lon:plon}]
			}
		}

		this.visitedNodes[subject] = subject
	}

	// find resource links and follow them
	for (var predicate in this.data[subject])
	{
		if (this.data[subject][predicate].objType == "uri")
		{
			// could be multiple uris
			for (var linkedIndex in this.data[subject][predicate].object)
			{

				var linkeduri = this.data[subject][predicate].object[linkedIndex]
				
				if (linkeduri in this.data)
				{
					// if the link had lat/long draw it
					var ret = this.placeAndDraw(linkeduri)
					if (ret)
					{
						if (plat.length == plon.length)
						{
							for(var index in plon)
							{
								for (var index2 in ret[1].lat)
								{
									// Connect the dots
									this.addConnect({lat:plat[index], lon:plon[index]}, {lat:ret[1].lat[index2],lon:ret[1].lon[index2]}, width, color)
								}
							}
						}

					}
				}
			}
		}
	}

	if (this.placemarks[subject])
		return this.placemarks[subject]
	else
		return false
}

NetworkViewSkin2D.prototype.makePlace = function (placeLat, placeLon, placeName, placeDesc)
{
	return this.makePlaceIcon(placeLat, placeLon, placeName, placeDesc, "http://maps.google.com/mapfiles/kml/paddle/red-circle.png", 1.0)
}
NetworkViewSkin2D.prototype.makePlaceIcon = function (placeLat, placeLon, placeName, placeDesc, iconHref)
{
	return this.makePlaceIconStyle(placeLat, placeLon, placeName, placeDesc, iconHref, 1.0)
}

NetworkViewSkin2D.prototype.addPlace = function (placeLat, placeLon, placeName, placeDesc){
	return this.addPlaceIcon(placeLat, placeLon, placeName, placeDesc, "http://maps.google.com/mapfiles/kml/paddle/red-circle.png", 1.0)
}

NetworkViewSkin2D.prototype.addPlaceIcon = function (placeLat, placeLon, placeName, placeDesc, iconHref)
{
	return this.addPlaceIconStyle(placeLat, placeLon, placeName, placeDesc, iconHref, 1.0)
}

NetworkViewSkin2D.prototype.addPlaceIconStyle = function (placeLat, placeLon, placeName, placeDesc, iconHref, iconScale) {
	this.placemarksToAdd.push([placeLat, placeLon, placeName, placeDesc, iconHref, iconScale])
}

NetworkViewSkin2D.prototype.batchDraw = function (){

	// placemarks
	for (var p in this.placemarksToAdd){
		this.makePlaceIconStyle2D(this.placemarksToAdd[p][0], this.placemarksToAdd[p][1], this.placemarksToAdd[p][2], this.placemarksToAdd[p][3], this.placemarksToAdd[p][4], this.placemarksToAdd[p][5])
	}

	// lines
	for (var l in this.linesToAdd)
	{
		this.connect2D(this.linesToAdd[l][0], this.linesToAdd[l][1], this.linesToAdd[l][2], this.linesToAdd[l][3])
	}
	
}

NetworkViewSkin2D.prototype.makePlaceIconStyle2D = function makePlaceIconStyle(placeLat, placeLon, placeName, placeDesc, iconHref, iconScale) {

	var icon = new GIcon(G_DEFAULT_ICON);
	// friendlier setup for maps
	if (iconHref == "http://maps.google.com/mapfiles/kml/paddle/red-circle.png")
		icon.image = "http://maps.gstatic.com/intl/en_us/mapfiles/marker.png"

                
	// Set up our GMarkerOptions object
	var markerOptions = { icon:icon };


	this.map.addOverlay(new google.maps.Marker(new google.maps.LatLng(placeLat, placeLon), markerOptions))
}


NetworkViewSkin2D.prototype.addConnect = function (start, stop, w, color){
	this.linesToAdd.push([start, stop, w, color])
}

NetworkViewSkin2D.prototype.connect2D = function (start, stop, w, color){
	var polyline = new GPolyline([
		new GLatLng(start.lat, start.lon),
		new GLatLng(stop.lat, stop.lon)
		], "#" + color, w);
	
	this.map.addOverlay(polyline);
}

NetworkViewSkin2D.prototype.Destroy = function(){
	this.div = null
	this.baseWidget = null
	this.loadingDiv = null
	this.innerDiv = null
	this.selectbox = null
}

NetworkViewSkin2D.prototype.OnOutputTypeChange = function(){
}


NetworkViewSkin2D.prototype.QueryStarted = function(){
}

NetworkViewSkin2D.prototype.QueryError = function(error)
{
	this.innerDiv.text("There was an error while making the AJAX call:" + error);
	// leave this.inQuery mode
	this.inQuery = false;
	//this.loadingDiv.css("display", "none");
	this.div.css("background-color", "white");
}

NetworkViewSkin2D.prototype.QuerySuccess = function(isInitial, response)
{
	this.data = response
	
	// make a record object that traverses easier
	this.data = this.CreateIndexedJSONRDF()

	this.TryRender()
}

NetworkViewSkin2D.prototype.CreateIndexedJSONRDF = function()
{

	var indexedJSONRDF = {}

	for (record in this.data['records'])
	{
		var subject = {}
		for(entityName in this.data['records'][record]['entityNames'])
		{
			indexedJSONRDF[this.data['records'][record]['entityNames'][entityName]] = subject
		}
		for(predicate in this.data['records'][record]['fieldValues'])
		{
			var statement = {}

			var urisInPath = predicate.split(" ");
			subject[urisInPath[urisInPath.length - 1]] = statement;

			statement.object = this.data['records'][record]['fieldValues'][predicate]

			if (this.data['records'][record]['fieldValues'][predicate].objType == "uri")
			{
				// I have a hunch uriToTitle will break if object is an Array
				var uriLink = uriToTitle(statement.object)
				statement.objectlink = decodeURIComponent(uriLink).link(uriLink)
			}

			statement.role = this.data['records'][record]['fieldValues'][predicate].role
		}
	}

	return indexedJSONRDF
}

NetworkViewSkin2D.prototype.TryRender = function(obj) {
	
	if (this.map != "" && this.data != ""){
		// good ol' loop for unconnected segments graph traversal
		for (subject in this.data)
		{
			this.placeAndDraw(subject)
		}

		// suspend rendering of globe while we draw
		this.batchDraw()
		this.map.setCenter(new GLatLng(this.lat, this.lon), 2);
	}
}
