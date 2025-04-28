<?php

function calculate_distance_miles($lat1, $lon1, $lat2, $lon2)
{
    $earthRadiusMiles = 3958.8;

    $lat1Rad = deg2rad($lat1);
    $lon1Rad = deg2rad($lon1);
    $lat2Rad = deg2rad($lat2);
    $lon2Rad = deg2rad($lon2);

    $deltaLat = $lat2Rad - $lat1Rad;
    $deltaLon = $lon2Rad - $lon1Rad;

    $a = sin($deltaLat / 2) ** 2 +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLon / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadiusMiles * $c;
}
