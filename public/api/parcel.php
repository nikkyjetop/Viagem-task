<?php

// Get coordinates from the Leaflet map.
$lat = $_GET['lat'] ?? null;
$lng = $_GET['lng'] ?? null;

header('Content-Type: application/json');

// Both coordinates are required to find a pracel.
if ($lat === null || $lng === null) {
    http_response_code(400);

    echo json_encode([
        'error' => 'Missing coordinates'
    ]);

    exit;
}

// GetFeatureByPoint expects a point in GML format. Leaflet provides coordinates in EPSG:4326.
$point = sprintf(
    '<gml:Point xmlns:gml="http://www.opengis.net/gml/3.2" srsName="http://www.opengis.net/def/crs/EPSG/0/4326">
        <gml:pos>%s %s</gml:pos>
    </gml:Point>',
    $lat,
    $lng
);

// Parameters required by the ČÚZK WFS GetFeatureByPoint query.
$params = [
    'service' => 'WFS',
    'version' => '2.0.0',
    'request' => 'GetFeature',
    'storedQuery_id' => 'GetFeatureByPoint',
    'POINT' => $point,
    'FEATURE_TYPE' => 'CadastralParcel',
    'srsName' => 'http://www.opengis.net/def/crs/EPSG/0/4326'
];

$url = 'https://services.cuzk.gov.cz/wfs/inspire-CP-wfs.asp?' .
    http_build_query($params);

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if ($response === false) {
    http_response_code(500);

    echo json_encode([
        'error' => 'ČÚZK request failed',
        'message' => curl_error($ch)
    ]);

    exit;
}

// Parse the XML response returned by ČÚZK.
$xml = simplexml_load_string($response);

if ($xml === false) {
    http_response_code(500);

    echo json_encode([
        'error' => 'Failed to parse ČÚZK response'
    ]);

    exit;
}

// Get the namespaces used in the ČÚZK XML response.
$namespaces = $xml->getDocNamespaces(true);

// When the click didn't hit a parcel (water, gap in the cadastre, outside
// CZ, ...), ČÚZK returns an empty <FeatureCollection/> with no "cp"
// namespace instead of a <cp:CadastralParcel>.
if (!isset($namespaces['cp'])) {
    http_response_code(404);

    echo json_encode([
        'error' => 'No parcel found at this location'
    ]);

    exit;
}

// Namespace used for cadastral parcel elements.
$cp = $namespaces['cp'];

// Namespace used for GML geometry elements.
$gml = $namespaces['gml'];

// Namespace used for xlink attributes (parcel/zoning titles).
$xlink = $namespaces['xlink'];

// Access the cadastral parcel elements.
$parcel = $xml->children($cp);

// The katastrální území (cadastral territory) this parcel belongs to,
// taken from the "zoning" reference's human-readable title.
$cadastralTerritory = (string) $parcel->zoning->attributes($xlink)->title;

// Application scope: povinné minimum je k.ú. Jičín, doplněné o 3 další
// sousední katastrální území (viz README).
$supportedTerritories = ['Jičín', 'Valdice', 'Holín', 'Železnice'];

if (!in_array($cadastralTerritory, $supportedTerritories, true)) {
    http_response_code(422);

    echo json_encode([
        'error' => 'Parcel is outside the covered area',
        'cadastralTerritory' => $cadastralTerritory
    ]);

    exit;
}

// Access the parcel geometry.
$geometry = $parcel->geometry->children($gml);

// Get the polygon's exterior ring.
$polygon = $geometry->Polygon;
$exterior = $polygon->exterior->children($gml);
$linearRing = $exterior->LinearRing;

// Get the coordinate list of the polygon.
$posList = trim((string) $linearRing->posList);

// Split the coordinate list into individual values.
$coordinates = preg_split('/\s+/', $posList);

// Convert the coordinate values into latitude/longitude pairs.
$polygonCoordinates = [];

for ($i = 0; $i < count($coordinates); $i += 2) {
    $polygonCoordinates[] = [
        (float) $coordinates[$i],
        (float) $coordinates[$i + 1]
    ];
}

// Get the parcel label from the XML response.
$label = (string) $parcel->label;

// Get the national cadastral reference of the parcel.
$nationalReference = (string) $parcel->nationalCadastralReference;

// Get the registered area of the parcel in square meters.
$area = (float) $parcel->areaValue;

echo json_encode([
    'label' => $label,
    'nationalReference' => $nationalReference,
    'area' => $area,
    'cadastralTerritory' => $cadastralTerritory,
    'geometry' => $polygonCoordinates
]);