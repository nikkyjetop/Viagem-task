<?php

$url = "https://services.cuzk.gov.cz/wfs/inspire-CP-wfs.asp?service=WFS&version=2.0.0&request=GetFeature&typeNames=CadastralParcel&count=1";

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if ($response === false) {
    echo "CHYBA:<br>";
    echo curl_error($ch);
} else {
    echo "REQUEST FUNGUJE!<br><br>";

    echo "<pre>";
    echo htmlspecialchars($response);
    echo "</pre>";
}