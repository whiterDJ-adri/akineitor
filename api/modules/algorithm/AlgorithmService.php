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
        $objetivoId = $row ? (int)$row['id'] : null;

        $usuarioIdStr = $usuarioId !== null ? (string)$usuarioId : null;
        $sql = "INSERT INTO partidas (usuario_id, personaje_objetivo_id, estado, estado_json) VALUES (?, ?, 'in_progress', NULL)";
        $this->db->query($sql, [$usuarioIdStr, (string)$objetivoId]);
        $rid = $this->db->query("SELECT LAST_INSERT_ID() AS id");
        $ridRow = $rid->fetch_assoc();
        $partidaId = (int)($ridRow['id'] ?? 0);
        return ['partida_id' => $partidaId, 'personaje_objetivo_id' => $objetivoId];
    }

    public function getPartida(int $partidaId): ?array
    {
        $res = $this->db->query("SELECT * FROM partidas WHERE id = ?", [(string)$partidaId]);
        $row = $res ? $res->fetch_assoc() : null;
        return $row ?: null;
    }

    public function updatePartidaEstadoJson(int $partidaId, array $estado): void
    {
        $json = json_encode($estado, JSON_UNESCAPED_UNICODE);
        $this->db->query("UPDATE partidas SET estado_json = ? WHERE id = ?", [$json, (string)$partidaId]);
    }

    public function completePartida(int $partidaId): void
    {
        $this->db->query("UPDATE partidas SET estado = 'completed' WHERE id = ?", [(string)$partidaId]);
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
            [(string)$partidaId, (string)$preguntaId, $respuesta]
        );
    }

    public function getAskedAnswers(int $partidaId): array
    {
        $res = $this->db->query(
            "SELECT pregunta_id, respuesta_usuario FROM respuestas_partida WHERE partida_id = ? ORDER BY fecha ASC",
            [(string)$partidaId]
        );
        $asked = [];
        while ($row = $res->fetch_assoc()) {
            $asked[(int)$row['pregunta_id']] = $row['respuesta_usuario'];
        }
        return $asked;
    }

    public function getAllPersonajes(): array
    {
        $res = $this->db->query("SELECT id, nombre, descripcion, imagen_url FROM personajes");
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $list[(int)$row['id']] = $row;
        }
        return $list;
    }

    public function getAllPreguntas(): array
    {
        $res = $this->db->query("SELECT id, texto_pregunta, tipo, opciones_json FROM preguntas");
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $qid = (int)$row['id'];
            $opts = [];
            if (!empty($row['opciones_json'])) {
                $decoded = json_decode($row['opciones_json'], true);
                if (is_array($decoded)) $opts = array_values($decoded);
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
        $res = $this->db->query("SELECT 1 FROM preguntas WHERE id = ? LIMIT 1", [(string)$preguntaId]);
        return $res && ($res->num_rows > 0);
    }

    public function getMapping(): array
    {
        $res = $this->db->query("SELECT personaje_id, pregunta_id, respuesta_esperada FROM personaje_pregunta");
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $pid = (int)$row['personaje_id'];
            $qid = (int)$row['pregunta_id'];
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
                if (!in_array($texto, $categoriaTexts, true)) continue;
                if (isset($map[$pid][$qid])) continue; // ya existe en BD
                $ans = $this->evalCategoryAnswer($json, $texto);
                $map[$pid][$qid] = $ans;
            }
        }
        return $map;
    }

    private function loadJsonCharacters(): void
    {
        $path = __DIR__ . '/../../models/data.json';
        if (!file_exists($path)) return;
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        if (!is_array($data)) return;
        foreach ($data as $c) {
            $name = $c['name'] ?? null;
            if (!$name) continue;
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
        ];
    }

    private function evalCategoryAnswer(?array $c, string $texto): string
    {
        if (!$c) return 'no lo sé';
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
                if ($aff === 'pride troopers') return 'sí';
                if (strpos($desc, 'universo 11') !== false) return 'sí';
                return 'no';
            case '¿Aparece en Dragon Ball Super?':
                if (strpos($desc, 'dragon ball super') !== false) return 'sí';
                if ($aff === 'pride troopers') return 'sí';
                if ($race === 'angel' || $race === 'god') return 'sí';
                return (strlen($desc) > 0 ? 'no' : 'no lo sé');
            case '¿Es una fusión?':
                if (strpos($desc, 'fusión') !== false) return 'sí';
                if (in_array($name, ['gotenks', 'vegito', 'gogeta'])) return 'sí';
                return 'no';
            case '¿Es un villano?':
                if (in_array($race, ['majin', 'android'])) return 'sí';
                if ($aff === 'freelancer') return 'sí';
                if (in_array($aff, ['army of frieza', 'pride troopers', 'z fighter'])) return 'no';
                return 'no';
            default:
                return 'no lo sé';
        }
    }

    private function compatibility(string $expected, string $given): float
    {
        $expected = $this->normalizeAnswer($expected);
        $given = $this->normalizeAnswer($given);
        if ($expected === $given) return 1.0;
        // Desconocido (sin mapeo o respuesta "no lo sé"): neutro-controlado
        // Reducimos el peso para evitar que candidatos con muchos "no lo sé"
        // dominen el ranking.
        if ($expected === 'no lo sé' || $given === 'no lo sé') return 0.6;
        // aproximaciones binarias con mayor separación
        $nearYes = ['sí' => 1.0, 'probablemente' => 0.8, 'probablemente no' => 0.2, 'no' => 0.01];
        $nearNo  = ['no' => 1.0, 'probablemente no' => 0.8, 'probablemente' => 0.2, 'sí' => 0.01];
        if ($expected === 'sí') return $nearYes[$given] ?? 0.5;
        if ($expected === 'no') return $nearNo[$given] ?? 0.5;
        if ($expected === 'probablemente') {
            return match ($given) {
                'sí' => 0.8,
                'probablemente' => 1.0,
                'no lo sé' => 0.9,
                'probablemente no' => 0.5,
                'no' => 0.2,
                default => 0.6,
            };
        }
        if ($expected === 'probablemente no') {
            return match ($given) {
                'no' => 0.8,
                'probablemente no' => 1.0,
                'no lo sé' => 0.9,
                'probablemente' => 0.5,
                'sí' => 0.2,
                default => 0.6,
            };
        }
        // Para categorías no binarias (si existieran), penalizar fuerte el desacierto
        return 0.05;
    }

    public function computeProbabilities(array $asked, array $personajes, array $mapping): array
    {
        $probs = [];
        $epsilon = 1e-9;
        foreach ($personajes as $pid => $_) {
            $prob = 1.0;
            foreach ($asked as $qid => $ans) {
                $expected = $mapping[$pid][$qid] ?? 'no lo sé';
                $prob *= $this->compatibility($expected, $this->normalizeAnswer($ans));
            }
            $probs[$pid] = max($prob, $epsilon);
        }
        // Normalizar
        $sum = array_sum($probs);
        if ($sum > 0) {
            foreach ($probs as $pid => $p) {
                $probs[$pid] = $p / $sum;
            }
        }
        return $probs;
    }

    public function selectNextQuestion(array $askedIds, array $probs, array $mapping, array $preguntas): ?int
    {
        $allQIds = array_keys($preguntas);
        $remaining = array_values(array_diff($allQIds, $askedIds));
        if (empty($remaining)) return null;
        $bestQ = null;
        $bestH = -1.0;

        // Priorizar preguntas de categorías primero
        $catTexts = $this->getCategoryQuestionTexts();
        $remainingCat = array_values(array_filter($remaining, function ($qid) use ($preguntas, $catTexts) {
            $texto = $preguntas[$qid]['texto_pregunta'] ?? '';
            return in_array($texto, $catTexts, true);
        }));
        $remainingToConsider = !empty($remainingCat) ? $remainingCat : $remaining;

        // Considerar solo top-K candidatos para maximizar ganancia de información donde importa
        $K = 12;
        $sorted = $probs;
        arsort($sorted);
        $topProbs = array_slice($sorted, 0, $K, true);

        foreach ($remainingToConsider as $qid) {
            $q = $preguntas[$qid] ?? null;
            if (!$q) continue;
            $answers = ['no lo sé'];
            if (($q['tipo'] ?? '') === 'multiple_choice') {
                $answers = array_merge((array)($q['opciones'] ?? []), ['no lo sé']);
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
            if ($total <= 0) continue;
            $H = 0.0;
            foreach ($dist as $v) {
                if ($v > 0) {
                    $H += - ($v / $total) * log($v / $total, 2);
                }
            }
            if ($H > $bestH) {
                $bestH = $H;
                $bestQ = $qid;
            }
        }
        return $bestQ;
    }

    private function normalizeAnswer(string $ans): string
    {
        $a = trim(mb_strtolower($ans));
        // normalización de variantes
        if ($a === 'si') $a = 'sí';
        if ($a === 'probablemente si') $a = 'probablemente';
        if ($a === 'probablemente sí') $a = 'probablemente';
        if ($a === 'no lo se' || $a === 'no se') $a = 'no lo sé';
        return $a;
    }
}
