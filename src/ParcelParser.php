<?php

// Parses the GML/XML response returned by the ČÚZK WFS GetFeatureByPoint
// query into a plain array. Has no opinion on which cadastral territories
// the app supports — that's an application-level decision made by the caller.
final class ParcelParser
{
    /**
     * @return array{
     *     label: string,
     *     nationalReference: string,
     *     area: float,
     *     cadastralTerritory: string,
     *     geometry: array<int, array{0: float, 1: float}>
     * }|null Parsed parcel data, or null if the response contains no parcel
     *        (e.g. the clicked point is outside the cadastre).
     *
     * @throws RuntimeException if the XML itself can't be parsed.
     */
    public static function parse(string $xml): ?array
    {
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            throw new RuntimeException('Failed to parse ČÚZK response');
        }

        // Get the namespaces used in the ČÚZK XML response.
        $namespaces = $doc->getDocNamespaces(true);

        // When the click didn't hit a parcel (water, gap in the cadastre, outside
        // CZ, ...), ČÚZK returns an empty <FeatureCollection/> with no "cp"
        // namespace instead of a <cp:CadastralParcel>.
        if (!isset($namespaces['cp'])) {
            return null;
        }

        // Namespace used for cadastral parcel elements.
        $cp = $namespaces['cp'];

        // Namespace used for GML geometry elements.
        $gml = $namespaces['gml'];

        // Namespace used for xlink attributes (parcel/zoning titles).
        $xlink = $namespaces['xlink'];

        // Access the cadastral parcel elements.
        $parcel = $doc->children($cp);

        // The katastrální území (cadastral territory) this parcel belongs to,
        // taken from the "zoning" reference's human-readable title.
        $cadastralTerritory = (string) $parcel->zoning->attributes($xlink)->title;

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

        return [
            // Get the parcel label from the XML response.
            'label' => (string) $parcel->label,
            // Get the national cadastral reference of the parcel.
            'nationalReference' => (string) $parcel->nationalCadastralReference,
            // Get the registered area of the parcel in square meters.
            'area' => (float) $parcel->areaValue,
            'cadastralTerritory' => $cadastralTerritory,
            'geometry' => $polygonCoordinates
        ];
    }
}
