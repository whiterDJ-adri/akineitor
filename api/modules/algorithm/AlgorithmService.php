<?php

namespace Modules\Algorithm;

use Exception;

class AlgorithmService
{
    private \MySQLConnection $db;
    private array $jsonCharsByName = [];

    public function __construct()
    {
        $this->db = new \MySQLConnection();
        $this->loadJsonCharacters();
    }

    public function createNewGame(?int $usuarioId = null): array
    {
        // Seleccionar personaje objetivo aleatorio
        $res = $this->db->query("SELECT id FROM personajes ORDER BY RAND() LIMIT 1");
        $row = $res->fetch_assoc();
        $objetivoId = $row ? (int) $row['id'] : null;

        $fields = [];
        $values = [];
        $params = [];

        if ($usuarioId !== null) {
            $fields[] = 'usuario_id';
            $values[] = '?';
            $params[] = (string) $usuarioId;
        } else {
            $fields[] = 'usuario_id';
            $values[] = 'NULL';
        }

        if ($objetivoId !== null) {
            $fields[] = 'personaje_objetivo_id';
            $values[] = '?';
            $params[] = (string) $objetivoId;
        } else {
            $fields[] = 'personaje_objetivo_id';
            $values[] = 'NULL';
        }

        $fields[] = 'estado';
        $values[] = "'in_progress'";
        $fields[] = 'estado_json';
        $values[] = 'NULL';

        $sql = "INSERT INTO partidas (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        $this->db->query($sql, $params);
        $rid = $this->db->query("SELECT LAST_INSERT_ID() AS id");
        $ridRow = $rid->fetch_assoc();
        $partidaId = (int) ($ridRow['id'] ?? 0);
        return ['partida_id' => $partidaId, 'personaje_objetivo_id' => $objetivoId];
    }

    public function getPartida(int $partidaId): ?array
    {
        $res = $this->db->query("SELECT * FROM partidas WHERE id = ?", [(string) $partidaId]);
        $row = $res ? $res->fetch_assoc() : null;
        return $row ?: null;
    }

    public function updatePartidaEstadoJson(int $partidaId, array $estado): void
    {
        $json = json_encode($estado, JSON_UNESCAPED_UNICODE);
        $this->db->query("UPDATE partidas SET estado_json = ? WHERE id = ?", [$json, (string) $partidaId]);
    }

    public function completePartida(int $partidaId): void
    {
        $this->db->query("UPDATE partidas SET estado = 'completed' WHERE id = ?", [(string) $partidaId]);
    }

    public function recordAnswer(int $partidaId, int $preguntaId, string $respuesta): void
    {
        // Evitar fallo por FK si la pregunta no existe (BD desalineada)
        if (!$this->preguntaExists($preguntaId)) {
            // No-op: no registramos la respuesta para evitar romper el flujo
            return;
        }
        $this->db->query(
            "INSERT INTO respuestas_partida (partida_id, pregunta_id, respuesta_usuario) VALUES (?, ?, ?)",
            [(string) $partidaId, (string) $preguntaId, $respuesta]
        );
    }

    public function applyCorrection(int $partidaId, int $personajeId): void
    {
        $asked = $this->getAskedAnswers($partidaId);
        foreach ($asked as $qid => $ans) {
            $norm = $this->normalizeAnswer($ans);
            $exists = $this->db->query(
                "SELECT 1 FROM personaje_pregunta WHERE personaje_id = ? AND pregunta_id = ? LIMIT 1",
                [(string) $personajeId, (string) $qid]
            );
            if ($exists && $exists->num_rows > 0) {
                $this->db->query(
                    "UPDATE personaje_pregunta SET respuesta_esperada = ? WHERE personaje_id = ? AND pregunta_id = ?",
                    [$norm, (string) $personajeId, (string) $qid]
                );
            } else {
                $this->db->query(
                    "INSERT INTO personaje_pregunta (personaje_id, pregunta_id, respuesta_esperada) VALUES (?, ?, ?)",
                    [(string) $personajeId, (string) $qid, $norm]
                );
            }
        }
    }

    public function getAskedAnswers(int $partidaId): array
    {
        $res = $this->db->query(
            "SELECT pregunta_id, respuesta_usuario FROM respuestas_partida WHERE partida_id = ? ORDER BY fecha ASC",
            [(string) $partidaId]
        );
        $asked = [];
        while ($row = $res->fetch_assoc()) {
            $asked[(int) $row['pregunta_id']] = $row['respuesta_usuario'];
        }
        return $asked;
    }

    public function getAllPersonajes(): array
    {
        $res = $this->db->query("SELECT id, nombre, descripcion, imagen_url FROM personajes");
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $list[(int) $row['id']] = $row;
        }
        return $list;
    }

    public function getAllPreguntas(): array
    {
        // Intentar primero con opciones_json (nuevo esquema)
        $res = $this->db->query("SELECT id, texto_pregunta, tipo FROM preguntas");
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $qid = (int) $row['id'];
            // Por defecto, preguntas tipo sí/no
            $opts = ['sí', 'no', 'no lo sé'];

            // Si existe opciones_json en la fila (esquema nuevo)
            if (isset($row['opciones_json']) && !empty($row['opciones_json'])) {
                $decoded = json_decode($row['opciones_json'], true);
                if (is_array($decoded))
                    $opts = array_values($decoded);
            }
            $list[$qid] = [
                'id' => $qid,
                'texto_pregunta' => $row['texto_pregunta'],
                'tipo' => $row['tipo'],
                'opciones' => $opts,
            ];
        }
        return $list;
    }

    public function preguntaExists(int $preguntaId): bool
    {
        $res = $this->db->query("SELECT 1 FROM preguntas WHERE id = ? LIMIT 1", [(string) $preguntaId]);
        return $res && ($res->num_rows > 0);
    }

    public function getMapping(): array
    {
        $res = $this->db->query("SELECT personaje_id, pregunta_id, respuesta_esperada FROM personaje_pregunta");
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $pid = (int) $row['personaje_id'];
            $qid = (int) $row['pregunta_id'];
            $map[$pid][$qid] = $row['respuesta_esperada'];
        }

        // Fallback dinámico: calcular respuestas para preguntas de categorías usando data.json
        $preguntas = $this->getAllPreguntas();
        $categoriaTexts = $this->getCategoryQuestionTexts();
        $personajes = $this->getAllPersonajes();
        foreach ($personajes as $pid => $pinfo) {
            $pnameNorm = $this->normalizeName($pinfo['nombre'] ?? '');
            $json = $this->jsonCharsByName[$pnameNorm] ?? null;
            foreach ($preguntas as $qid => $pq) {
                $texto = $pq['texto_pregunta'] ?? '';
                if (!in_array($texto, $categoriaTexts, true))
                    continue;
                if (isset($map[$pid][$qid]))
                    continue; // ya existe en BD
                $ans = $this->evalCategoryAnswer($json, $texto);
                $map[$pid][$qid] = $ans;
            }
        }
        return $map;
    }

    private function loadJsonCharacters(): void
    {
        $path = __DIR__ . '/../../models/data.json';
        if (!file_exists($path))
            return;
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        if (!is_array($data))
            return;
        foreach ($data as $c) {
            $name = $c['name'] ?? null;
            if (!$name)
                continue;
            $this->jsonCharsByName[$this->normalizeName($name)] = $c;
        }
    }

    private function normalizeName(string $n): string
    {
        $n = mb_strtolower(trim($n));
        $n = preg_replace('/\s+/', ' ', $n);
        $n = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $n);
        return $n;
    }

    private function getCategoryQuestionTexts(): array
    {
        return [
            '¿Pertenece a la raza Saiyan?',
            '¿Pertenece a la raza Humana?',
            '¿Pertenece a la raza Android?',
            '¿Pertenece a la raza de Freezer?',
            '¿Pertenece a la raza Dios?',
            '¿Pertenece a la raza Ángel?',
            '¿Pertenece a la raza Majin?',
            '¿Forma parte de los Guerreros Z?',
            '¿Forma parte del Ejército de Freezer?',
            '¿Forma parte de las Tropas del Orgullo?',
            '¿Pertenece al Universo 11?',
            '¿Aparece en Dragon Ball Super?',
            '¿Es una fusión?',
            '¿Es un villano?',
            '¿Puede transformarse en Super Saiyan?',
            '¿Tiene forma Golden?',
            '¿Aparece en Saga de Freezer?',
            '¿Aparece en Torneo del Poder?',
        ];
    }

    private function evalCategoryAnswer(?array $c, string $texto): string
    {
        if (!$c)
            return 'no lo sé';
        $race = strtolower($c['race'] ?? '');
        $aff = strtolower($c['affiliation'] ?? '');
        $desc = mb_strtolower($c['description'] ?? '');
        $name = mb_strtolower($c['name'] ?? '');

        switch ($texto) {
            case '¿Pertenece a la raza Saiyan?':
                return $race === 'saiyan' ? 'sí' : 'no';
            case '¿Pertenece a la raza Humana?':
                return $race === 'human' ? 'sí' : 'no';
            case '¿Pertenece a la raza Android?':
                return $race === 'android' ? 'sí' : 'no';
            case '¿Pertenece a la raza de Freezer?':
                return $race === 'frieza race' ? 'sí' : 'no';
            case '¿Pertenece a la raza Dios?':
                return $race === 'god' ? 'sí' : 'no';
            case '¿Pertenece a la raza Ángel?':
                return $race === 'angel' ? 'sí' : 'no';
            case '¿Pertenece a la raza Majin?':
                return $race === 'majin' ? 'sí' : 'no';
            case '¿Forma parte de los Guerreros Z?':
                return $aff === 'z fighter' ? 'sí' : 'no';
            case '¿Forma parte del Ejército de Freezer?':
                return $aff === 'army of frieza' ? 'sí' : 'no';
            case '¿Forma parte de las Tropas del Orgullo?':
                return $aff === 'pride troopers' ? 'sí' : 'no';
            case '¿Pertenece al Universo 11?':
                if ($aff === 'pride troopers')
                    return 'sí';
                if (strpos($desc, 'universo 11') !== false)
                    return 'sí';
                return 'no';
            case '¿Aparece en Dragon Ball Super?':
                if (strpos($desc, 'dragon ball super') !== false)
                    return 'sí';
                if ($aff === 'pride troopers')
                    return 'sí';
                if ($race === 'angel' || $race === 'god')
                    return 'sí';
                return (strlen($desc) > 0 ? 'no' : 'no lo sé');
            case '¿Es una fusión?':
                if (strpos($desc, 'fusión') !== false)
                    return 'sí';
                if (in_array($name, ['gotenks', 'vegito', 'gogeta']))
                    return 'sí';
                return 'no';
            case '¿Es un villano?':
                if (in_array($race, ['majin', 'android']))
                    return 'sí';
                if ($aff === 'freelancer')
                    return 'sí';
                if (in_array($aff, ['army of frieza', 'pride troopers', 'z fighter']))
                    return 'no';
                return 'no';
            case '¿Puede transformarse en Super Saiyan?':
                if ($race === 'saiyan')
                    return 'sí';
                if (strpos($desc, 'super saiyan') !== false || strpos($desc, 'super saiyajin') !== false)
                    return 'sí';
                return 'no';
            case '¿Tiene forma Golden?':
                if (strpos($desc, 'golden') !== false)
                    return 'sí';
                if ($race === 'frieza race' && (strpos($name, 'frieza') !== false || strpos($name, 'freezer') !== false))
                    return 'sí';
                return 'no';
            case '¿Aparece en Saga de Freezer?':
                if (strpos($desc, 'saga de freezer') !== false)
                    return 'sí';
                if ($aff === 'army of frieza')
                    return 'sí';
                return 'no';
            case '¿Aparece en Torneo del Poder?':
                if (strpos($desc, 'torneo del poder') !== false)
                    return 'sí';
                if ($aff === 'pride troopers')
                    return 'sí';
                return 'no';
            default:
                return 'no lo sé';
        }
    }

    private function compatibility(string $expected, string $given): float
    {
        $expected = $this->normalizeAnswer($expected);
        $given = $this->normalizeAnswer($given);
        if ($expected === $given)
            return 1.0;

        // Cuando no hay información (no lo sé), ser más neutral para no penalizar tanto
        if ($expected === 'no lo sé' || $given === 'no lo sé')
            return 0.85; // Aumentado de 0.6 a 0.85 para ser más permisivo

        // Aproximaciones binarias con mayor recompensa por aciertos y penalización por errores claros
        $nearYes = ['sí' => 1.0, 'probablemente' => 0.9, 'probablemente no' => 0.3, 'no' => 0.05];
        $nearNo = ['no' => 1.0, 'probablemente no' => 0.9, 'probablemente' => 0.3, 'sí' => 0.05];

        if ($expected === 'sí')
            return $nearYes[$given] ?? 0.5;
        if ($expected === 'no')
            return $nearNo[$given] ?? 0.5;
        if ($expected === 'probablemente') {
            return match ($given) {
                'sí' => 0.9,
                'probablemente' => 1.0,
                'no lo sé' => 0.85,
                'probablemente no' => 0.4,
                'no' => 0.1,
                default => 0.6,
            };
        }
        if ($expected === 'probablemente no') {
            return match ($given) {
                'no' => 0.9,
                'probablemente no' => 1.0,
                'no lo sé' => 0.85,
                'probablemente' => 0.4,
                'sí' => 0.1,
                default => 0.6,
            };
        }
        // Para categorías no binarias (si existieran), penalizar el desacierto
        return 0.1;
    }

    public function computeProbabilities(array $asked, array $personajes, array $mapping): array
    {
        $probs = [];
        $epsilon = 1e-9;

        // Prior uniforme: todos los personajes empiezan con la misma probabilidad base
        $numPersonajes = count($personajes);
        $numPreguntas = count($asked);

        // Debug: log para ver qué está pasando
        error_log("[AlgorithmService] Computing probabilities for " . $numPersonajes . " characters with " . $numPreguntas . " questions");

        foreach ($personajes as $pid => $_) {
            // Usar un enfoque de scoring aditivo en lugar de multiplicativo
            $score = 0.0;
            $matchCount = 0;
            $mismatchCount = 0;
            $unknownCount = 0;

            foreach ($asked as $qid => $ans) {
                $expected = $mapping[$pid][$qid] ?? 'no lo sé';
                $givenNorm = $this->normalizeAnswer($ans);
                $compat = $this->compatibility($expected, $givenNorm);

                // Puntuación logarítmica para evitar que un solo 0 mate la probabilidad
                if ($compat >= 0.95) {
                    $score += 3.0; // Match perfecto
                    $matchCount++;
                } elseif ($compat >= 0.8) {
                    $score += 2.0; // Buena compatibilidad
                    $matchCount++;
                } elseif ($compat >= 0.5) {
                    $score += 0.5; // Neutral/desconocido
                    $unknownCount++;
                } elseif ($compat >= 0.2) {
                    $score -= 1.0; // Incompatibilidad leve
                    $mismatchCount++;
                } else {
                    $score -= 3.0; // Incompatibilidad fuerte
                    $mismatchCount++;
                }
            }

            // Convertir score a probabilidad usando exponencial
            $probs[$pid] = exp($score / max($numPreguntas, 1));

            // Debug para el personaje con mejor score
            if ($pid === 1 || $score > 5) {
                error_log("[AlgorithmService] Personaje {$pid}: score=$score, matches=$matchCount, mismatches=$mismatchCount, unknown=$unknownCount");
            }
        }

        // Normalizar para que sumen 1.0
        $sum = array_sum($probs);
        if ($sum > 0) {
            foreach ($probs as $pid => $p) {
                $probs[$pid] = $p / $sum;
            }
        }

        // Debug: mostrar top 3
        $sorted = $probs;
        arsort($sorted);
        $top3 = array_slice($sorted, 0, 3, true);
        error_log("[AlgorithmService] Top 3 probabilities: " . json_encode($top3));

        return $probs;
    }

    public function selectNextQuestion(array $askedIds, array $probs, array $mapping, array $preguntas): ?int
    {

        $allQIds = array_keys($preguntas);
        $remaining = array_values(array_diff($allQIds, $askedIds));
        if (empty($remaining))
            return null;
        $bestQ = null;
        $bestH = -1.0;

        // Priorizar preguntas de categorías primero
        $catTexts = $this->getCategoryQuestionTexts();
        $remainingCat = array_values(array_filter($remaining, function ($qid) use ($preguntas, $catTexts) {
            $texto = $preguntas[$qid]['texto_pregunta'] ?? '';
            return in_array($texto, $catTexts, true);
        }));
        $remainingToConsider = !empty($remainingCat) ? $remainingCat : $remaining;

        $remainingToConsider = $this->filterContradictoryOrRedundant($remainingToConsider, $preguntas, $askedIds, $mapping, $probs);

        // Considerar solo top-K candidatos para maximizar ganancia de información donde importa
        $K = 12;
        $sorted = $probs;
        arsort($sorted);
        $topProbs = array_slice($sorted, 0, $K, true);

        if (empty($topProbs)) {
            return $remainingToConsider[0] ?? null;
        }

        foreach ($remainingToConsider as $qid) {
            $q = $preguntas[$qid] ?? null;
            if (!$q)
                continue;
            $answers = ['no lo sé'];
            if (($q['tipo'] ?? '') === 'multiple_choice') {
                $answers = array_merge((array) ($q['opciones'] ?? []), ['no lo sé']);
            } else {
                $answers = ['sí', 'no', 'probablemente', 'probablemente no', 'no lo sé'];
            }
            $dist = array_fill_keys($answers, 0.0);
            foreach ($topProbs as $pid => $p) {
                $exp = $mapping[$pid][$qid] ?? 'no lo sé';
                if (!array_key_exists($exp, $dist)) {
                    // Mapear cualquier respuesta fuera de opciones a 'no lo sé'
                    $dist['no lo sé'] += $p;
                } else {
                    $dist[$exp] += $p;
                }
            }
            $total = array_sum($dist);
            if ($total <= 0)
                continue;
            $H = 0.0;
            foreach ($dist as $v) {
                if ($v > 0) {
                    $H += -($v / $total) * log($v / $total, 2);
                }
            }
            $H *= $this->domainRelevanceBoost($q['texto_pregunta'] ?? '');
            if ($H > $bestH) {
                $bestH = $H;
                $bestQ = $qid;
            }
        }
        return $bestQ;
    }

    private function filterContradictoryOrRedundant(array $candidates, array $preguntas, array $askedIds, array $mapping, array $probs): array
    {

        if (empty($askedIds))
            return $candidates;
        $askedByText = [];
        foreach ($askedIds as $qid) {
            $texto = $preguntas[$qid]['texto_pregunta'] ?? null;
            if ($texto)
                $askedByText[$texto] = $qid;
        }
        $rules = $this->getContradictionRules();
        $exclude = [];
        foreach ($rules as $rule) {
            $ifText = $rule['if_text'];
            $ifAns = $rule['if_answer'];
            $thenText = $rule['then_exclude_text'];
            if (!isset($askedByText[$ifText]))
                continue;
            $qidAsked = $askedByText[$ifText];
            $given = $this->normalizeAnswer($this->mappingTopAnswerForAsked($qidAsked, $mapping, $probs));
            $givenUser = null;
            // Intentar recuperar la respuesta real del usuario mediante mapeo de asked
            // Si no disponible, usar mayoría ponderada por probs
            $givenUser = $given;
            if ($givenUser === $this->normalizeAnswer($ifAns)) {
                foreach ($candidates as $cid) {
                    $textoC = $preguntas[$cid]['texto_pregunta'] ?? '';
                    if ($textoC === $thenText)
                        $exclude[$cid] = true;
                }
            }
        }
        $filtered = [];
        foreach ($candidates as $cid) {
            if (!isset($exclude[$cid]))
                $filtered[] = $cid;
        }
        return $filtered;
    }

    private function getContradictionRules(): array
    {

        return [
            ['if_text' => '¿Forma parte de los Guerreros Z?', 'if_answer' => 'sí', 'then_exclude_text' => '¿Es un villano?'],
            ['if_text' => '¿Pertenece a la raza Saiyan?', 'if_answer' => 'sí', 'then_exclude_text' => '¿Pertenece a la raza Humana?'],
            ['if_text' => '¿Pertenece a la raza Humana?', 'if_answer' => 'sí', 'then_exclude_text' => '¿Pertenece a la raza Saiyan?'],
            ['if_text' => '¿Pertenece a la raza Ángel?', 'if_answer' => 'sí', 'then_exclude_text' => '¿Es un villano?'],
            ['if_text' => '¿Pertenece a la raza Dios?', 'if_answer' => 'sí', 'then_exclude_text' => '¿Es un villano?'],
        ];
    }

    private function domainRelevanceBoost(string $texto): float
    {

        $race = [
            '¿Pertenece a la raza Saiyan?',
            '¿Pertenece a la raza Humana?',
            '¿Pertenece a la raza Android?',
            '¿Pertenece a la raza de Freezer?',
            '¿Pertenece a la raza Dios?',
            '¿Pertenece a la raza Ángel?',
            '¿Pertenece a la raza Majin?',
        ];
        $align = [
            '¿Forma parte de los Guerreros Z?',
            '¿Forma parte del Ejército de Freezer?',
            '¿Forma parte de las Tropas del Orgullo?',
            '¿Es un villano?',
        ];
        $transform = [
            '¿Puede transformarse en Super Saiyan?',
            '¿Tiene forma Golden?',
            '¿Es una fusión?',
        ];
        $saga = [
            '¿Aparece en Saga de Freezer?',
            '¿Aparece en Torneo del Poder?',
            '¿Aparece en Dragon Ball Super?',
        ];
        if (in_array($texto, $race, true))
            return 1.25;
        if (in_array($texto, $align, true))
            return 1.2;
        if (in_array($texto, $transform, true))
            return 1.15;
        if (in_array($texto, $saga, true))
            return 1.1;
        return 1.0;
    }

    private function mappingTopAnswerForAsked(int $qid, array $mapping, array $probs): string
    {

        $sorted = $probs;
        arsort($sorted);
        foreach ($sorted as $pid => $_p) {
            if (isset($mapping[$pid][$qid]))
                return $mapping[$pid][$qid];
        }
        return 'no lo sé';
    }

    private function normalizeAnswer(string $ans): string
    {
        $a = trim(mb_strtolower($ans));
        // normalización de variantes
        if ($a === 'si')
            $a = 'sí';
        if ($a === 'ns')
            $a = 'no lo sé';
        if ($a === 'probablemente si')
            $a = 'probablemente';
        if ($a === 'probablemente sí')
            $a = 'probablemente';
        if ($a === 'no lo se' || $a === 'no se')
            $a = 'no lo sé';
        return $a;
    }
}
