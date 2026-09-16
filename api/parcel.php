<?php

require __DIR__ . '/../src/CuzkClient.php';
require __DIR__ . '/../src/ParcelParser.php';

// Get coordinates from the Leaflet map.
$lat = $_GET['lat'] ?? null;
$lng = $_GET['lng'] ?? null;

header('Content-Type: application/json');

// Both coordinates are required to find a parcel.
if ($lat === null || $lng === null) {
    http_response_code(400);

    echo json_encode([
        'error' => 'Missing coordinates'
    ]);

    exit;
}

$cuzkClient = new CuzkClient();

try {
    $response = $cuzkClient->getFeatureByPoint((float) $lat, (float) $lng);
} catch (RuntimeException $e) {
    http_response_code(500);

    echo json_encode([
        'error' => 'ČÚZK request failed',
        'message' => $e->getMessage()
    ]);

    exit;
}

try {
    $parcel = ParcelParser::parse($response);
} catch (RuntimeException $e) {
    http_response_code(500);

    echo json_encode([
        'error' => $e->getMessage()
    ]);

    exit;
}

if ($parcel === null) {
    http_response_code(404);

    echo json_encode([
        'error' => 'No parcel found at this location'
    ]);

    exit;
}

// Application scope: povinné minimum je k.ú. Jičín, doplněné o 3 další
// sousední katastrální území (viz README).
$supportedTerritories = ['Jičín', 'Valdice', 'Holín', 'Železnice'];

if (!in_array($parcel['cadastralTerritory'], $supportedTerritories, true)) {
    http_response_code(422);

    echo json_encode([
        'error' => 'Parcel is outside the covered area',
        'cadastralTerritory' => $parcel['cadastralTerritory']
    ]);

    exit;
}

echo json_encode($parcel);
