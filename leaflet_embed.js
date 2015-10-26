var map;
var mapLayer;
var ajaxRequest;
var popup;
var debug = 0;
var pathLayer;
var lastGeoJsonData;

var minZoom = 3;
var maxZoom = 18;
var minDrawingZoom = 16;
var settings = [];

var pruneCluster;

var categoryColors = [
	'#ff4b00', 
	'#bac900', 
	'#004bff',
];

function initmap() 
{
	settings['positionLat'] = 50.05256;
	settings['positionLon'] = 14.40312;
	settings['positionZoom'] = 15;
	
	// init map popups
	popup = L.popup();

	// set up the map
	map = new L.Map('map', { minZoom: minZoom, maxZoom: maxZoom });
	map.addLayer(
				L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				{
					attribution: 'Map data &copy <a href="http://openstreetmap.org">OpenStreetMap</a> contributors'
				})
			);

	map.setView( new L.LatLng( settings['positionLat'], settings['positionLon'] ), settings['positionZoom'] );

	addPruneCluster();
	addPruneClusterMarkers();
	
	// testAnonymize();
}

function onMapClick(e) {
     //    alert("You clicked the map at " + e.latlng);
    popup
        .setLatLng(e.latlng)
        .setContent("You clicked the map at " + e.latlng.toString())
        .openOn(map);
}

function addPruneCluster()
{
	pruneCluster = new PruneClusterForLeaflet();
	map.addLayer(pruneCluster);
	
	pruneCluster.BuildLeafletClusterIcon = function(cluster) {
			var e = new L.Icon.MarkerCluster();

			e.stats = cluster.stats;
			e.population = cluster.population;
			return e;
	};	
	
    var pi2 = Math.PI * 2;

    L.Icon.MarkerCluster = L.Icon.extend({
        options: {
            iconSize: new L.Point(44, 44),
            className: 'prunecluster leaflet-markercluster-icon'
        },

        createIcon: function () {
            // based on L.Icon.Canvas from shramov/leaflet-plugins (BSD licence)
            var e = document.createElement('canvas');
            this._setIconStyles(e, 'icon');
            var s = this.options.iconSize;
            e.width = s.x;
            e.height = s.y;
            this.draw(e.getContext('2d'), s.x, s.y);
            return e;
        },

        createShadow: function () {
            return null;
        },

        draw: function(canvas, width, height) {

            var lol = 0;

            var start = 0;
            for (var i = 0, l = categoryColors.length; i < l; ++i) {

                var size = this.stats[i] / this.population;


                if (size > 0) {
                    canvas.beginPath();
                    canvas.moveTo(22, 22);
                    canvas.fillStyle = categoryColors[i];
                    var from = start + 0.14,
                        to = start + size * pi2;

                    if (to < from) {
                        from = start;
                    }
                    canvas.arc(22,22,22, from, to);

                    start = start + size*pi2;
                    canvas.lineTo(22,22);
                    canvas.fill();
                    canvas.closePath();
                }

            }

            canvas.beginPath();
            canvas.fillStyle = 'white';
            canvas.arc(22, 22, 14, 0, Math.PI*2);
            canvas.fill();
            canvas.closePath();

            canvas.fillStyle = '#555';
            canvas.textAlign = 'center';
            canvas.textBaseline = 'middle';
            canvas.font = 'bold 12px sans-serif';

            canvas.fillText(this.population, 22, 22, 40);
        }
    });
}

function addPruneClusterMarkers()
{
	/*
	for ( var i = 0; i < 20; ++i )
	{
		var marker = new PruneCluster.Marker(
			settings['positionLat'] + ((Math.random() - 0.5) * 0.01), 
			settings['positionLon'] + ((Math.random() - 0.5) * 0.01));
		marker.category = i % 3;
		pruneCluster.RegisterMarker(marker);
	}
	*/
	
	$.getJSON('data/data.gjson', function(response){
		
		for ( var catIndex = 0; catIndex < response.geometries.length; ++catIndex )
		{
				var cat = response.geometries[ catIndex ];
				var catNumber = Number( cat.voteType );
				
				// create a colored marker icon for the single voter
				// colors for categories are defined in 'categoryColors'
				var categoryMarkerIcon = L.MakiMarkers.icon({
						icon: "triangle",
						color: categoryColors[ catNumber ],
						size: "m"
				});
				
				// Create markers for all points of category. Create them through pruneCluser to support clustering
				for ( var pointIndex = 0; pointIndex < cat.coordinates.length;  ++pointIndex )
				{
					var marker = new PruneCluster.Marker(
						cat.coordinates[ pointIndex ][0], 
						cat.coordinates[ pointIndex ][1]);
					marker.category = catNumber;
					marker.data.icon = categoryMarkerIcon;
					
					pruneCluster.RegisterMarker(marker);
					
				}
		}

		pruneCluster.ProcessView();
	})
	
}

/*
function Anonymize( coord )
{
	// 1km = roughly 0.008
	var rr = Math.random() * 41;  // rand( 0, 41 )
	var r = (rr - 20) / 10;
	var difference = r > 0 ? 0.008 : -0.008;
	var difference = difference + (difference * Math.abs(r));
	
	return coord + difference;
}

function testAnonymize()
{
	var categoryMarkerIcon = L.MakiMarkers.icon({
			icon: "triangle",
						color: categoryColors[ 0 ],
			size: "m"
	});
	var marker = new PruneCluster.Marker(
		settings['positionLat'], 
		settings['positionLon']);
	marker.category = 0;
	marker.data.icon = categoryMarkerIcon;
	
	pruneCluster.RegisterMarker(marker);
	

	categoryMarkerIcon = L.MakiMarkers.icon({
			icon: "triangle",
						color: categoryColors[ 1 ],
			size: "m"
	});
	
	for ( var i =0; i<15; ++i )
	{
		marker = new PruneCluster.Marker(
			Anonymize( settings['positionLat'] ), 
			Anonymize( settings['positionLon'] ));
		marker.category = 1;
		marker.data.icon = categoryMarkerIcon;
		
		pruneCluster.RegisterMarker(marker);
	}
	
		pruneCluster.ProcessView();
}
*/
