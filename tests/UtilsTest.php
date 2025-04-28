<?php
require_once __DIR__ . '/../includes/utils.php';

use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testCalculateDistanceMiles()
    {
        $result = calculate_distance_miles(40.7128, -74.0060, 34.0522, -118.2437);
        $this->assertGreaterThan(0, $result);
    }
}