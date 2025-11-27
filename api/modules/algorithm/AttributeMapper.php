<?php

namespace Modules\Algorithm;

class AttributeMapper
{
    /**
     * Maps character data to an answer for a specific question text.
     * Returns: 'si', 'no', or 'no lo se'.
     */
    public static function getAnswer(array $characterData, string $questionText): string
    {
        $race = mb_strtolower($characterData['race'] ?? '');
        $affiliation = mb_strtolower($characterData['affiliation'] ?? '');
        $gender = mb_strtolower($characterData['gender'] ?? '');
        $description = mb_strtolower($characterData['description'] ?? '');
        $name = mb_strtolower($characterData['name'] ?? '');
        $ki = $characterData['ki'] ?? '';
        $maxKi = $characterData['maxKi'] ?? '';
        $image = $characterData['image'] ?? '';

        // Normalize question text
        $q = mb_strtolower(trim($questionText));

        // --- RAZA ---
        if (preg_match('/raza\s+saiyan/i', $q) || str_contains($q, 'es de raza saiyan'))
            return $race === 'saiyan' ? 'si' : 'no';
        if (preg_match('/raza\s+human/i', $q) || str_contains($q, 'es humano'))
            return $race === 'human' ? 'si' : 'no';
        if (preg_match('/raza\s+android/i', $q) || str_contains($q, 'androide'))
            return $race === 'android' ? 'si' : 'no';
        if (preg_match('/raza\s+(de\s+)?freezer/i', $q) || str_contains($q, 'frieza race'))
            return $race === 'frieza race' ? 'si' : 'no';
        if (preg_match('/raza\s+dios/i', $q))
            return $race === 'god' ? 'si' : 'no';
        if (preg_match('/raza\s+ngel/i', $q) || str_contains($q, 'raza angel'))
            return $race === 'angel' ? 'si' : 'no';
        if (preg_match('/raza\s+majin/i', $q))
            return $race === 'majin' ? 'si' : 'no';
        if (str_contains($q, 'namekian'))
            return $race === 'namekian' ? 'si' : 'no';
        if (str_contains($q, 'jiren race'))
            return $race === 'jiren race' ? 'si' : 'no';
        if (str_contains($q, 'nucleico'))
            return str_contains($race, 'nucleico') ? 'si' : 'no';

        // --- GÉNERO ---
        if (str_contains($q, 'es mujer') || str_contains($q, 'femenino'))
            return $gender === 'female' ? 'si' : 'no';
        if (str_contains($q, 'es hombre') || str_contains($q, 'masculino'))
            return $gender === 'male' ? 'si' : 'no';

        // --- AFILIACIÓN ---
        if (str_contains($q, 'guerreros z'))
            return $affiliation === 'z fighter' ? 'si' : 'no';
        if (str_contains($q, 'ejército de freezer'))
            return $affiliation === 'army of frieza' ? 'si' : 'no';
        if (str_contains($q, 'tropas del orgullo'))
            return $affiliation === 'pride troopers' ? 'si' : 'no';
        if (str_contains($q, 'villano')) {
            if ($affiliation === 'villain' || $affiliation === 'army of frieza')
                return 'si';
            if ($affiliation === 'z fighter' || $affiliation === 'pride troopers')
                return 'no';
            if (str_contains($description, 'villano') || str_contains($description, 'antagonista') || str_contains($description, 'enemigo'))
                return 'si';
            return 'no';
        }

        // --- FAMILIA ---
        if (str_contains($q, 'familia de goku')) {
            $fam = ['goku', 'chi-chi', 'gohan', 'goten', 'bardock', 'raditz', 'pan', 'videl'];
            return in_array($name, $fam) ? 'si' : 'no';
        }
        if (str_contains($q, 'familia de vegeta')) {
            $fam = ['vegeta', 'bulma', 'trunks', 'bra', 'king vegeta'];
            return in_array($name, $fam) ? 'si' : 'no';
        }

        // Sibling relationships
        if (str_contains($q, 'hermano') || str_contains($q, 'hermana')) {
            // Cooler is Freezer's brother
            if (($name === 'cooler' || $name === 'freezer') && (str_contains($q, 'importante') || str_contains($q, 'conocido')))
                return 'si';
            // Android siblings
            if ($name === 'android 17' || $name === 'android 18')
                return 'si';
            // Raditz is Goku's brother
            if ($name === 'raditz' || $name === 'goku')
                return 'si';
            return 'no';
        }

        // --- SAGAS / APARICIONES ---
        if (str_contains($q, 'dragon ball super')) {
            if (str_contains($description, 'dragon ball super') || $race === 'god' || $race === 'angel' || $affiliation === 'pride troopers')
                return 'si';
            if ($name === 'goku' || $name === 'vegeta' || $name === 'freezer')
                return 'si';
            return 'no lo se';
        }
        if (str_contains($q, 'saga de freezer')) {
            if ($affiliation === 'army of frieza' || $name === 'freezer' || $name === 'goku' || $name === 'vegeta' || $name === 'krillin' || $name === 'gohan' || $name === 'piccolo')
                return 'si';
            return 'no lo se';
        }
        if (str_contains($q, 'saga de cell') || str_contains($q, 'juegos de cell')) {
            if ($race === 'android' || $name === 'celula' || $name === 'cell' || $name === 'gohan')
                return 'si';
            return 'no lo se';
        }
        if (str_contains($q, 'saga de majin buu')) {
            if ($race === 'majin' || $name === 'majin buu' || $name === 'babidi' || $name === 'gotenks' || $name === 'vegetto')
                return 'si';
            return 'no lo se';
        }
        if (str_contains($q, 'torneo del poder') || str_contains($q, 'torneo de poder')) {
            if ($affiliation === 'pride troopers' || str_contains($description, 'torneo del poder') || $name === 'jiren' || $name === 'toppo' || $name === 'dyspo')
                return 'si';
            if ($name === 'goku' || $name === 'vegeta' || $name === 'freezer' || $name === 'android 17' || $name === 'android 18' || $name === 'krillin' || $name === 'master roshi' || $name === 'tenshinhan' || $name === 'piccolo' || $name === 'gohan')
                return 'si';
            return 'no';
        }

        // Movie characters - enhanced to better distinguish Cooler
        if (str_contains($q, 'película') || str_contains($q, 'movie')) {
            // Movie-exclusive or primarily movie characters
            if ($name === 'cooler' || $name === 'broly' || $name === 'janemba' || $name === 'gogeta')
                return 'si';
            // Main characters appear in movies too
            if ($name === 'goku' || $name === 'vegeta' || $name === 'gohan' || $name === 'piccolo')
                return 'si';
            // Freezer appears in movies but primarily in series
            if ($name === 'freezer')
                return 'si';
            return 'no lo se';
        }

        if (str_contains($q, 'película no canónica')) {
            if ($name === 'broly' || $name === 'janemba' || $name === 'cooler' || $name === 'gogeta')
                return 'si';
            return 'no';
        }

        // --- RELACIONES / RIVALIDADES ---
        if (str_contains($q, 'rivalidad directa con goku')) {
            if ($name === 'vegeta' || $name === 'freezer' || $name === 'piccolo' || $name === 'hit' || $name === 'jiren' || $name === 'beerus')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'rivalidad directa con vegeta')) {
            if ($name === 'goku' || $name === 'freezer')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'maestro') || str_contains($q, 'mentor')) {
            if ($name === 'master roshi' || $name === 'whis' || $name === 'piccolo' || $name === 'kami' || $name === 'king kai')
                return 'si';
            return 'no';
        }

        // --- ORIGEN / NATURALEZA ---
        if (str_contains($q, 'artificial') || str_contains($q, 'creado desde cero')) {
            if ($race === 'android' || $name === 'cell' || $name === 'celula' || $name === 'buu' || $name === 'majin buu')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'futuro alternativo')) {
            if ($name === 'trunks' || $name === 'zamasu' || $name === 'black goku')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'espacio') || str_contains($q, 'dimensiones')) {
            if ($name === 'janemba' || $name === 'buu' || $name === 'gotenks' || $name === 'gogeta' || $name === 'vegetto' || $name === 'whis' || $name === 'vados')
                return 'si';
            return 'no';
        }

        // --- INTELECTO / ROL ---
        if (str_contains($q, 'inteligente') || str_contains($q, 'científico')) {
            if ($name === 'bulma' || $name === 'dr. gero' || $name === 'dr. brief' || $name === 'gohan')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'guardián de la tierra')) {
            if ($name === 'kami' || $name === 'dende' || $name === 'piccolo')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'organización') || str_contains($q, 'militar')) {
            if ($affiliation === 'army of frieza' || $affiliation === 'pride troopers' || $affiliation === 'red ribbon army')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'videojuegos')) {
            if ($name === 'android 21' || $name === 'towa' || $name === 'mira')
                return 'si';
            return 'no lo se'; // Casi todos salen, pero la pregunta suele referirse a exclusivos
        }

        if (str_contains($q, 'legendario') || str_contains($q, 'legendary')) {
            if ($name === 'broly' || $name === 'kale')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'odia a goku') || str_contains($q, 'kakaroto')) {
            if ($name === 'broly' || $name === 'baby' || $name === 'android 16')
                return 'si';
            return 'no';
        }

        // Fix for Broly Affiliation (DB says 'Other', but he is often considered Villain)
        if (str_contains($q, 'villano')) {
            if ($name === 'broly')
                return 'si'; // Z Broly is definitely a villain, Super Broly is antagonist. Safer to say Yes to distinguish from heroes.
        }

        if (str_contains($q, 'hielo') || str_contains($q, 'frío')) {
            if ($name === 'cooler' || $name === 'king cold' || $name === 'eis shenron' || str_contains($name, 'cold'))
                return 'si';
            return 'no';
        }

        // --- CARACTERÍSTICAS ---

        // Transformations - need to detect multiple forms
        if (str_contains($q, 'transformación') || str_contains($q, 'transform') || str_contains($q, 'múltiples formas') || str_contains($q, 'formas')) {
            // Frieza Race with transformations
            if ($race === 'frieza race' && ($name === 'freezer' || $name === 'cooler' || $name === 'king cold'))
                return 'si';

            // Saiyans can transform to Super Saiyan
            if ($race === 'saiyan')
                return 'si';

            // Specific characters with transformations
            if ($name === 'buu' || $name === 'majin buu' || $name === 'cell' || $name === 'celula')
                return 'si';
            if ($name === 'piccolo' || $name === 'gohan')
                return 'si';

            // Dodoria, Zarbon, Ginyu - NO tienen transformaciones significativas (Zarbon tiene una pero es menor)
            if ($name === 'zarbon' || $name === 'dodoria' || $name === 'ginyu')
                return 'no lo se'; // Zarbon tiene una transformación pero es menos conocida

            // If mentioned in description
            if (str_contains($description, 'transform') || str_contains($description, 'forma'))
                return 'si';

            return 'no lo se';
        }

        if (str_contains($q, 'super saiyan')) {
            if ($race === 'saiyan')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'fusión')) {
            // Characters who ARE fusions
            if (str_contains($description, 'fusión') || str_contains($name, 'vegetto') || str_contains($name, 'gogeta') || str_contains($name, 'gotenks') || str_contains($name, 'kibito-shin') || str_contains($name, 'kefla') || str_contains($name, 'zamasu'))
                return 'si';

            // Characters who CAN participate in a fusion
            $fusionParticipants = ['goku', 'vegeta', 'trunks', 'goten', 'piccolo', 'krillin', 'kale', 'caulifla', 'black goku', 'zamasu'];
            if (in_array($name, $fusionParticipants))
                return 'si';

            // Namekian fusion participants (often considered 'assimilation' but sometimes fusion in games)
            if ($name === 'piccolo' || $name === 'kami' || $name === 'nail')
                return 'si';

            return 'no';
        }
        if (preg_match('/dios(es)?\s+de\s+la\s+destrucci/i', $q)) {
            if (str_contains($description, 'dios de la destrucción') || str_contains($name, 'bills') || str_contains($name, 'beerus') || str_contains($name, 'champa') || str_contains($name, 'vermouth') || str_contains($name, 'vermoudh'))
                return 'si';
            // Relación directa: Whis, Vados, Goku, Vegeta
            if ($name === 'whis' || $name === 'vados' || $name === 'goku' || $name === 'vegeta')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'calva') || str_contains($q, 'calvo') || str_contains($q, 'pelo')) {
            if ($name === 'krillin' || $name === 'piccolo' || $name === 'tenshinhan' || $name === 'master roshi' || $name === 'freezer' || $name === 'jiren' || $name === 'hit' || $name === 'nappa')
                return 'si';
            if ($race === 'saiyan')
                return 'no';
            return 'no lo se';
        }
        if (str_contains($q, 'piel verde')) {
            if ($race === 'namekian' || $name === 'celula' || $name === 'cell' || $name === 'zamasu')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'cola')) {
            if ($race === 'saiyan' && ($name === 'goku' || $name === 'vegeta' || $name === 'gohan'))
                return 'no';
            if ($race === 'frieza race' || $name === 'beerus' || $name === 'cell')
                return 'si';
            return 'no lo se';
        }
        if (str_contains($q, 'espada')) {
            if ($name === 'trunks' || $name === 'janemba' || $name === 'yajirobe' || $name === 'tapion')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'muerto') && str_contains($q, 'revivido')) {
            if ($name === 'goku' || $name === 'vegeta' || $name === 'krillin' || $name === 'piccolo' || $name === 'master roshi' || $name === 'yamcha' || $name === 'tenshinhan' || $name === 'chaos' || $name === 'freezer' || $name === 'android 17' || $name === 'android 18')
                return 'si';
            return 'no lo se';
        }
        if (str_contains($q, 'destruir planetas')) {
            if ($race === 'god' || $race === 'frieza race' || $race === 'saiyan' || $race === 'majin' || $name === 'cell' || $name === 'jiren')
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'kaiosama') || str_contains($q, 'kaio')) {
            if (str_contains($name, 'kaio'))
                return 'si';
            return 'no';
        }
        if (str_contains($q, 'androide') && str_contains($q, 'mujer')) {
            if ($name === 'android 18')
                return 'si';
            return 'no';
        }

        // --- INFERENCIA POR DESCRIPCIÓN ---
        // Si la pregunta contiene palabras clave que aparecen en la descripción
        $keywords = explode(' ', str_replace(['¿', '?', 'es ', 'un ', 'una ', 'el ', 'la ', 'de ', 'que '], '', $q));
        $matches = 0;
        foreach ($keywords as $kw) {
            if (strlen($kw) > 3 && str_contains($description, $kw)) {
                $matches++;
            }
        }
        if ($matches >= 2)
            return 'si'; // Heurística débil

        return 'no lo se';
    }
}
