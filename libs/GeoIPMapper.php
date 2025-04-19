<?php
namespace Libs;

class GeoIPMapper {
    private static $geoDB = __DIR__.'/../storage/geoip/GeoLite2-City.mmdb';
    
    public static function mapIP(string $ip): array {
        try {
            $reader = new \GeoIp2\Database\Reader(self::$geoDB);
            $record = $reader->city($ip);
            
            return [
                'latitude' => $record->location->latitude,
                'longitude' => $record->location->longitude,
                'country' => $record->country->name,
                'city' => $record->city->name,
                'asn' => self::getASN($ip)
            ];
        } catch (\Exception $e) {
            return [
                'latitude' => 0,
                'longitude' => 0,
                'country' => '未知',
                'city' => '未知',
                'asn' => '未知'
            ];
        }
    }

    private static function getASN(string $ip): string {
        $asnDB = __DIR__.'/../storage/geoip/GeoLite2-ASN.mmdb';
        try {
            $reader = new \GeoIp2\Database\Reader($asnDB);
            return $reader->asn($ip)->autonomousSystemOrganization;
        } catch (\Exception $e) {
            return '未知';
        }
    }
}