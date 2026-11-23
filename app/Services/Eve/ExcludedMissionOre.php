<?php

namespace App\Services\Eve;

final class ExcludedMissionOre
{
    private const TYPE_IDS = [
        28617, 28618, 28619, 28620, 28621, 28622, 28623, 28624, 28625, 28626,
        28627, 28628, 16269, 17977, 46293, 46305, 46705,
        92565, 92566, 92567, 92568, 92818, 92819, 92820, 92821,
    ];

    private const NAMES = [
        'Arisite', 'Augumene', 'Azure Ice', 'Banidine', 'Batch Compressed Veldspar IV-Grade',
        'Crystalline Icicle', 'Flawed Gneiss', 'Flawed Raspite', 'Flawed Polycrase', 'Flawed Moissanite', 'Flawed Kangite',
        'Compressed Flawed Raspite', 'Compressed Flawed Polycrase', 'Compressed Flawed Moissanite', 'Compressed Flawed Kangite', 'Geodite', 'Glacial Mass IV-Grade',
        'Glowing Carnotite', 'Green Arisite', 'Krystallos', 'Lyavite', 'Mercium',
        'Oeryl', 'Pithix', 'Polygypsum', 'Twinkling Titanite', 'Zuthrine',
    ];

    public static function isExcluded(string $name, ?int $typeId = null): bool
    {
        if ($typeId !== null && in_array($typeId, self::TYPE_IDS, true)) {
            return true;
        }

        $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        foreach (self::NAMES as $excludedName) {
            if ($key === mb_strtolower($excludedName)) {
                return true;
            }
        }

        return false;
    }
}
