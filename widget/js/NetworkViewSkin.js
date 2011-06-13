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
// Constructor
function NetworkViewSkin(div, baseWidget, useInitialQuery){
	this.ge = false
	this.lat = 40.7494
	this.lon = -73.9681
	this.data = false
	this.page_subject = baseWidget.initialQuery
	this.placemarks = []

	this.placemarksToAdd = []
	this.linesToAdd = []

	this.icons = {}
	this.visitedNodes = {}

	this.div = jQuery("#"+div)
	this.baseWidget = baseWidget
	this.useInitialQuery = useInitialQuery

	this.loadingDiv = jQuery('<div><img src="'+wgScriptPath+'/extensions/AqueductExtension/widget/js/ajax-loader.gif" alt="Loading..." /></div>').css("display", "none")
	this.loadingDiv.css("position", "absolute").css("float", "left").css("z-index", 1000)
	this.div.before(this.loadingDiv)

	this.div.width(500)
	this.div.height(500)

	this.innerDiv = jQuery('<div id="' + baseWidget.divName + '_innerdiv"></div>')

	this.innerDiv.width(500)
	this.innerDiv.height(500)

	this.div.append(this.innerDiv)

	this.inQuery = false
	
	ge_registry.push(this)

	var me = this;

	// closure to stop the google api from losing a reference to our class
	this.initcallback = function(instance) {
		me.ge = instance;
		me.ge.getWindow().setVisibility(true);
		
		// nav controls
		me.ge.getNavigationControl().setVisibility(me.ge.VISIBILITY_AUTO);

		// set layers
		me.ge.getLayerRoot().enableLayerById(me.ge.LAYER_BORDERS, true);
		me.ge.getLayerRoot().enableLayerById(me.ge.LAYER_ROADS, false);
		
		
		me.ge.getOptions().setStatusBarVisibility(true);
		me.ge.getOptions().setScaleLegendVisibility(true);

		// Get the current view
		var lookAt = me.ge.getView().copyAsLookAt(me.ge.ALTITUDE_RELATIVE_TO_GROUND);
		
		// Set lat and long
		lookAt.setLatitude(me.lat);
		lookAt.setLongitude(me.lon);
		lookAt.setTilt(lookAt.getTilt() + 20.0);

		lookAt.setRange(lookAt.getRange() * 0.4);

		// Update the view in Google Earth
		me.ge.getView().setAbstractView(lookAt);
		
		me.TryRender()
	}
}


NetworkViewSkin.prototype.InitGE = function() {
	google.earth.createInstance('map3d', this.geInitCallback, this.geFailureCallback);
}


NetworkViewSkin.prototype.constructLabel = function(entity, preds)
{
	var label = "";


	var uriLink = uriToTitle(entity)

	// hack to get root wiki directory
	var temp = document.location.href.split("index.php")

	label += '<a href="' + temp[0] + "index.php/" + uriLink + '">' + uriLink + '</a><br/>'


	// build up tooltip label here
	if ("http://www.w3.org/2000/01/rdf-schema#label"  in this.data[subject])
	{
		label += "Label: " +String(this.data[subject]["http://www.w3.org/2000/01/rdf-schema#label"].object[0]) + "<br/>"
	}
	return label;
}

NetworkViewSkin.prototype.placeAndDraw = function(subject){
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
				plon.push(parseFloat(this.data[subject]["http://www.w3.org/2003/01/geo/wgs84_pos#long"].object[index]))
			}
		}
		if ("http://www.w3.org/2003/01/geo/wgs84_pos#lat" in this.data[subject])
		{
			if (!plat)
				plat = []

			for (var index in this.data[subject]["http://www.w3.org/2003/01/geo/wgs84_pos#lat"].object)		
			{
				plat.push(parseFloat(this.data[subject]["http://www.w3.org/2003/01/geo/wgs84_pos#lat"].object[index]))
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
						// This also seems to do nothing, as no value is returned from this function. I hope this.placemarks is not being used to render, because it has a big undefined where the place should be.
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

NetworkViewSkin.prototype.geFailureCallback = function (errorCode) {
}

NetworkViewSkin.prototype.makePlace = function (placeLat, placeLon, placeName, placeDesc)
{
	return this.makePlaceIcon(placeLat, placeLon, placeName, placeDesc, "http://maps.google.com/mapfiles/kml/paddle/red-circle.png", 1.0)
}
NetworkViewSkin.prototype.makePlaceIcon = function (placeLat, placeLon, placeName, placeDesc, iconHref)
{
	return this.makePlaceIconStyle(placeLat, placeLon, placeName, placeDesc, iconHref, 1.0)
}

NetworkViewSkin.prototype.addPlace = function (placeLat, placeLon, placeName, placeDesc)
{
	return this.addPlaceIcon(placeLat, placeLon, placeName, placeDesc, "http://maps.google.com/mapfiles/kml/paddle/red-circle.png", 1.0)
}

NetworkViewSkin.prototype.addPlaceIcon = function (placeLat, placeLon, placeName, placeDesc, iconHref)
{
	return this.addPlaceIconStyle(placeLat, placeLon, placeName, placeDesc, iconHref, 1.0)
}

NetworkViewSkin.prototype.addPlaceIconStyle = function (placeLat, placeLon, placeName, placeDesc, iconHref, iconScale) {
	this.placemarksToAdd.push([placeLat, placeLon, placeName, placeDesc, iconHref, iconScale])
}

NetworkViewSkin.prototype.batchDraw = function (){
	for (var skin in ge_registry)
	{
		// placemarks
		for (var p in ge_registry[skin].placemarksToAdd)
		{
			ge_registry[skin].makePlaceIconStyle(ge_registry[skin].placemarksToAdd[p][0], ge_registry[skin].placemarksToAdd[p][1], ge_registry[skin].placemarksToAdd[p][2], ge_registry[skin].placemarksToAdd[p][3], ge_registry[skin].placemarksToAdd[p][4], ge_registry[skin].placemarksToAdd[p][5])
		}
		
		// lines
		for (var l in ge_registry[skin].linesToAdd)
		{
			ge_registry[skin].connect(ge_registry[skin].linesToAdd[l][0], ge_registry[skin].linesToAdd[l][1], ge_registry[skin].linesToAdd[l][2], ge_registry[skin].linesToAdd[l][3])
		}
	}
}

NetworkViewSkin.prototype.makePlaceIconStyle = function makePlaceIconStyle(placeLat, placeLon, placeName, placeDesc, iconHref, iconScale) {
	var placemark = this.ge.createPlacemark('');
	placemark.setName(placeName);
	placemark.setDescription(placeDesc);
	this.ge.getFeatures().appendChild(placemark);

	var style
	if (this.icons[iconHref])
		style = this.icons[iconHref]
	else{
		// Define a custom icon.
		var icon = this.ge.createIcon('');
		icon.setHref(iconHref);
		var style = this.ge.createStyle('');
		style.getIconStyle().setIcon(icon);
		style.getIconStyle().setScale(iconScale);
		this.icons[iconHref] = style
	}
	
	placemark.setStyleSelector(style);
	
	var point = this.ge.createPoint('');
	point.setLatitude(placeLat);
	point.setLongitude(placeLon);
	placemark.setGeometry(point);

	return placemark
}


NetworkViewSkin.prototype.addConnect = function (start, stop, width, color){
	this.linesToAdd.push([start, stop, width, color])
}

NetworkViewSkin.prototype.connect = function (start, stop, width, color){
	var placemark = this.ge.createPlacemark('')
	placemark.setStyleSelector(this.ge.createStyle(''));

	var lineStyle = placemark.getStyleSelector().getLineStyle();
	lineStyle.setWidth(width);
	lineStyle.getColor().set(color);

	placemark.setGeometry(this.shootLine(start, stop))
	
	this.ge.getFeatures().appendChild(placemark);
}

NetworkViewSkin.prototype.shootLine = function (start, stop){
	var ring = this.ge.createLineString('');

	ring.setTessellate(true);
	ring.setExtrude(false);
	
	var steps = 50;
	
	// clamp to ground	
	ring.setAltitudeMode(this.ge.ALTITUDE_CLAMP_TO_GROUND);
	ring.getCoordinates().pushLatLngAlt(start.lat, start.lon, 0)
	ring.getCoordinates().pushLatLngAlt(stop.lat, stop.lon, 0)

	return ring;
}

NetworkViewSkin.prototype.Destroy = function(){
	this.div = null
	this.baseWidget = null
	this.loadingDiv = null
	this.innerDiv = null
	this.selectbox = null
}

NetworkViewSkin.prototype.OnOutputTypeChange = function(){

}


NetworkViewSkin.prototype.QueryStarted = function(){
}

NetworkViewSkin.prototype.QueryError = function(error)
{
	this.innerDiv.text("There was an error while making the AJAX call:" + error);
	// leave this.inQuery mode
	this.inQuery = false;
	this.loadingDiv.css("display", "none");
	this.div.css("background-color", "white");
}

NetworkViewSkin.prototype.QuerySuccess = function(isInitial, response)
{
	this.data = response
	
	// make a record object that traverses easier
	this.data = this.CreateIndexedJSONRDF()

	this.TryRender()
}

NetworkViewSkin.prototype.CreateIndexedJSONRDF = function()
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

NetworkViewSkin.prototype.TryRender = function(obj) {
	if (this.ge && this.data ){
		// good ol' loop for unconnected segments graph traversal
		for (subject in this.data)
		{
			this.placeAndDraw(subject)
		}

		// mega hack to suspend rendering of globe while we draw
		// its a performance booster, believe it or not. (batches the inserts)
		google.earth.fetchKml(this.ge, '', this.batchDraw); 
	}
}