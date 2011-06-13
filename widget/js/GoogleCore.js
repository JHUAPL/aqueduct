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
	// Shared Google API
	function __global_googleInit()
	{

		jQuery(document).ready(function() {
			// init widgets
			if (___CisternWidgetInstances)
				{
				___CisternWidgetInstances[0].OnLoadAll()
			}
		});

		for (var x in gm_registry)
		{
			gm_registry[x].map = new google.maps.Map2(gm_registry[x].innerDiv.attr("id"))
			gm_registry[x].map.setCenter(new google.maps.LatLng(gm_registry[x].lat, gm_registry[x].lon), 13)
			gm_registry[x].gmInitCallback()
		}

		for (var x in ge_registry)
		{
			// doesn't perserve the object association with the init callback here
			//google.earth.createInstance(ge_registry[x].innerDiv.attr("id"), ge_registry[x].geInitCallback, global_geFailureCallback)
			google.earth.createInstance(ge_registry[x].innerDiv.attr("id"), ge_registry[x].initcallback, global_geFailureCallback)
			//google.earth.createInstance(ge_registry[x].innerDiv.attr("id"), global_geInitCallback, global_geFailureCallback)
		}
	}

	//google.load("jquery", "1.2.6");

	google.setOnLoadCallback(__global_googleInit)

	var ge_registry = []
	var gm_registry = []

	function global_geInitCallback(instance) {
		for (x in ge_registry)
		{
			ge_registry[x].geInitCallback(instance)
		}
	}
	
	function global_geFailureCallback(instance) {
		//console.debug("in geFailureCallback")
	}
