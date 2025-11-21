<?php

namespace Modules\Algorithm;

use Exception;

class AlgorithmService
{
    private \MySQLConnection $db;

    public function __construct()
    {
        $this->db = new \MySQLConnection();
    }

    public function createNewGame(?int $usuarioId = null): array
    {
        // Seleccionar personaje objetivo aleatorio
        $res = $this->db->query("SELECT id FROM personajes ORDER BY RAND() LIMIT 1");
        $row = $res->fetch_assoc();
        $objetivoId = $row ? (int)$row['id'] : null;

        $fields = [];
        $values = [];
        $params = [];

        if ($usuarioId !== null) {
            $fields[] = 'usuario_id';
            $values[] = '?';
            $params[] = (string)$usuarioId;
        } else {
            $fields[] = 'usuario_id';
            $values[] = 'NULL';
        }

        if ($objetivoId !== null) {
            $fields[] = 'personaje_objetivo_id';
            $values[] = '?';
            $params[] = (string)$objetivoId;
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

    public function applyCorrection(int $partidaId, int $personajeId): void
    {
        $asked = $this->getAskedAnswers($partidaId);
        foreach ($asked as $qid => $ans) {
            $norm = $this->normalizeAnswer($ans);
            $exists = $this->db->query(
                "SELECT 1 FROM personaje_pregunta WHERE personaje_id = ? AND pregunta_id = ? LIMIT 1",
                [(string)$personajeId, (string)$qid]
            );
            if ($exists && $exists->num_rows > 0) {
                $this->db->query(
                    "UPDATE personaje_pregunta SET respuesta_esperada = ? WHERE personaje_id = ? AND pregunta_id = ?",
                    [$norm, (string)$personajeId, (string)$qid]
                );
            } else {
                $this->db->query(
                    "INSERT INTO personaje_pregunta (personaje_id, pregunta_id, respuesta_esperada) VALUES (?, ?, ?)",
                    [(string)$personajeId, (string)$qid, $norm]
                );
            }
        }
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
        $res = $this->db->query("SELECT id, texto_pregunta, tipo FROM preguntas ORDER BY RAND()");
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $qid = (int)$row['id'];
            $list[$qid] = [
                'id' => $qid,
                'texto_pregunta' => $row['texto_pregunta'],
                'tipo' => $row['tipo'],
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
        return $map;
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

    public function biasByNameHint(array $probs, array $personajes, ?string $hint): array
    {
        if ($hint === null || $hint === '') return $probs;
        $h = $this->normalizeNameInput($hint);
        foreach ($personajes as $pid => $info) {
            $n = $this->normalizeNameInput((string)($info['nombre'] ?? ''));
            if ($n === $h) {
                $probs[$pid] = $probs[$pid] * 2.0;
            }
        }
        $sum = array_sum($probs);
        if ($sum > 0) {
            foreach ($probs as $pid => $p) {
                $probs[$pid] = $p / $sum;
            }
        }
        return $probs;
    }

    private function normalizeNameInput(string $n): string
    {
        $n = mb_strtolower(trim($n));
        $n = preg_replace('/\s+/', ' ', $n);
        $n = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $n);
        return $n;
    }

    public function selectNextQuestion(array $askedIds, array $probs, array $mapping, array $preguntas, array $askedAnswers = []): ?int
    {
        $allQIds = array_keys($preguntas);
        $remaining = array_values(array_diff($allQIds, $askedIds));
        if (empty($remaining)) return null;
        $bestQ = null;
        $bestH = -1.0;
        $remainingToConsider = $remaining;

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
            $H *= $this->coherenceScore($askedAnswers, $preguntas, $q['texto_pregunta'] ?? '');
            if ($H > $bestH) {
                $bestH = $H;
                $bestQ = $qid;
            }
        }
        return $bestQ;
    }

    private function coherenceScore(array $askedAnswers, array $preguntas, string $textoQ): float
    {
        $t = mb_strtolower($textoQ);
        $score = 1.0;
        foreach ($askedAnswers as $qid => $ans) {
            $aq = mb_strtolower($preguntas[$qid]['texto_pregunta'] ?? '');
            $a = mb_strtolower(trim($ans));
            if (strpos($aq, 'familia') !== false && strpos($aq, 'goku') !== false && $a === 'sí') {
                if (strpos($t, 'chicle') !== false) $score *= 0.6;
            }
            if (strpos($aq, 'guerreros z') !== false && $a === 'sí') {
                if (strpos($t, 'ejército de freezer') !== false || strpos($t, 'frieza') !== false) $score *= 0.7;
                if (strpos($t, 'guerreros z') !== false) $score *= 1.2;
            }
            if (strpos($aq, 'chicle') !== false && ($a === 'no' || $a === 'no lo sé' || $a === 'ns')) {
                if (strpos($t, 'buu') !== false || strpos($t, 'boo') !== false || strpos($t, 'majin') !== false) $score *= 0.5;
            }
        }
        return max(0.3, min(1.3, $score));
    }

    private function normalizeAnswer(string $ans): string
    {
        $a = trim(mb_strtolower($ans));
        // normalización de variantes
        if ($a === 'si') $a = 'sí';
        if ($a === 'ns') $a = 'no lo sé';
        if ($a === 'probablemente si') $a = 'probablemente';
        if ($a === 'probablemente sí') $a = 'probablemente';
        if ($a === 'no lo se' || $a === 'no se') $a = 'no lo sé';
        return $a;
    }
}
