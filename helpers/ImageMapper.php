<?php

class ImageMapper
{

    private static $characterImageMap = [
        // Principales protagonistas
        'goku' => 'goku_normal.webp',
        'son goku' => 'goku_normal.webp',
        'kakarot' => 'goku_normal.webp',

        'vegeta' => 'vegeta_normal.webp',
        'principe vegeta' => 'vegeta_normal.webp',

        'piccolo' => 'picolo_normal.webp',
        'piccoro' => 'picolo_normal.webp',

        'gohan' => 'gohan.webp',
        'son gohan' => 'gohan.webp',

        // Villanos principales
        'freezer' => 'Freezer.webp',
        'frieza' => 'Freezer.webp',
        'celula' => 'celula.webp',
        'cell' => 'celula.webp',
        'célula' => 'celula.webp',

        // Androides
        'android 19' => 'Android19.webp',
        'androide 19' => 'Android19.webp',
        'android 16' => 'Androide_16.webp',
        'androide 16' => 'Androide_16.webp',
        'android 13' => 'Androide13normal.webp',
        'androide 13' => 'Androide13normal.webp',
        'dr. gero' => 'Dr._Gero nadroide 20.webp',
        'doctor gero' => 'Dr._Gero nadroide 20.webp',

        // Saiyanos
        'bardock' => 'Bardock_Artwork.webp',
        'raditz' => 'Raditz_artwork_Dokkan.webp',
        'trunks' => 'Trunks_Buu_Artwork.webp',
        'gotenks' => 'Gotenks_Artwork.webp',

        // Humanos
        'bulma' => 'bulma.webp',
        'chi-chi' => 'ChiChi_DBS.webp',
        'chichi' => 'ChiChi_DBS.webp',
        'krillin' => 'Krilin_Universo7.webp',
        'krilin' => 'Krilin_Universo7.webp',
        'kuririn' => 'Krilin_Universo7.webp',
        'tenshinhan' => 'Tenshinhan_Universo7.webp',
        'ten shin han' => 'Tenshinhan_Universo7.webp',
        'yamcha' => 'Final_Yamcha.webp',
        'mr. satan' => 'Mr_Satan_DBSuper.webp',
        'mr satan' => 'Mr_Satan_DBSuper.webp',
        'hercule' => 'Mr_Satan_DBSuper.webp',
        'master roshi' => 'roshi.webp',
        'maestro roshi' => 'roshi.webp',
        'kame-sennin' => 'roshi.webp',
        'lunch' => 'Lunch_traje_de_sirvienta_en_el_manga.webp',
        'lanch' => 'Lunch_traje_de_sirvienta_en_el_manga.webp',

        // Ejercito de Freezer
        'zarbon' => 'zarbon.webp',
        'dodoria' => 'dodoria.webp',
        'ginyu' => 'ginyu.webp',
        'captain ginyu' => 'ginyu.webp',
        'capitán ginyu' => 'ginyu.webp',

        // Namekianos
        'dende' => 'Dende_Artwork.webp',
        'nail' => 'Nail_Artwork.webp',

        // GT/Especiales
        'android 14' => '14Dokkan.webp',
        'androide 14' => '14Dokkan.webp',
        'android 15' => '15Dokkan.webp',
        'androide 15' => '15Dokkan.webp',
        'android 17' => '17_Artwork.webp',
        'androide 17' => '17_Artwork.webp',

        // Otros
        'babidi' => 'Babidi_Artwork.webp',
        'shenlong' => 'Shen_Long_Artwork.png',
        'shen long' => 'Shen_Long_Artwork.png',
        'shenron' => 'Shen_Long_Artwork.png'
    ];

    /**
     * Obtiene la imagen local correspondiente a un personaje
     */
    public static function getCharacterImage(string $characterName): ?string
    {
        $normalized = self::normalizeName($characterName);

        // Buscar en el mapa directo
        if (isset(self::$characterImageMap[$normalized])) {
            $imagePath = 'assets/img/' . self::$characterImageMap[$normalized];
            if (file_exists(__DIR__ . '/../' . $imagePath)) {
                return $imagePath;
            }
        }

        // Búsqueda fuzzy - intentar coincidencias parciales
        foreach (self::$characterImageMap as $mapName => $imagefile) {
            if (strpos($mapName, $normalized) !== false || strpos($normalized, $mapName) !== false) {
                $imagePath = 'assets/img/' . $imagefile;
                if (file_exists(__DIR__ . '/../' . $imagePath)) {
                    return $imagePath;
                }
            }
        }

        return null;
    }

    /**
     * Normaliza nombres para comparación
     */
    private static function normalizeName(string $name): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s\.\-]/', '', $name)));
    }

    /**
     * Obtiene todas las imágenes disponibles para galería
     */
    public static function getAllCharacterImages(): array
    {
        $images = [];
        $imageDir = __DIR__ . '/../assets/img/';

        if (!is_dir($imageDir)) {
            return $images;
        }

        $files = scandir($imageDir);
        $characterFiles = array_filter($files, function ($file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            return in_array(strtolower($ext), ['webp', 'png', 'jpg', 'jpeg']) &&
                $file !== 'Shen_Long_Artwork.png'; // Excluir Shenlong de galería
        });

        foreach ($characterFiles as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $displayName = self::getDisplayName($name);
            $images[] = [
                'file' => 'assets/img/' . $file,
                'name' => $displayName,
                'filename' => $file
            ];
        }

        return $images;
    }

    /**
     * Convierte nombre de archivo a nombre de display
     */
    private static function getDisplayName(string $filename): string
    {
        $name = str_replace(['_', '.webp', '.png', '.jpg'], [' ', '', '', ''], $filename);
        $name = preg_replace('/\b(normal|artwork|dbs|universo7|dokkan)\b/i', '', $name);
        return trim(ucwords($name));
    }

    /**
     * Obtiene imágenes aleatorias para la galería rotativa
     */
    public static function getRandomCharacterImages(int $count = 6): array
    {
        $allImages = self::getAllCharacterImages();
        shuffle($allImages);
        return array_slice($allImages, 0, $count);
    }
}