<?php

// Talks to the ČÚZK WFS service: builds the GetFeatureByPoint request and
// executes it. Knows nothing about parsing the response or the app's own
// business rules (e.g. which cadastral territories are supported).
final class CuzkClient
{
    private const WFS_URL = 'https://services.cuzk.gov.cz/wfs/inspire-CP-wfs.asp';

    /**
     * Fetches the raw XML response for the CadastralParcel feature at the
     * given point.
     *
     * @throws RuntimeException if the HTTP request itself fails.
     */
    public function getFeatureByPoint(float $lat, float $lng): string
    {
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

        $url = self::WFS_URL . '?' . http_build_query($params);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new RuntimeException(curl_error($ch));
        }

        return $response;
    }
}
