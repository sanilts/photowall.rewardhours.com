<?php
declare(strict_types=1);

/** Runs the local Python scanner (scanner/scan.py) on one file and returns its result. */
final class Scanner
{
    public const EXPLICIT = ['FEMALE_GENITALIA_EXPOSED', 'MALE_GENITALIA_EXPOSED', 'ANUS_EXPOSED'];

    public static function run(string $file, ?string $preview = null, ?string $thumb = null, int $timeout = 90): array
    {
        $cmd = [cfg('python', 'python3'), '-I', PW_ROOT . '/scanner/scan.py', '--in', $file, '--models', (string) cfg('models', '')];
        if ($preview) array_push($cmd, '--preview', $preview);
        if ($thumb) array_push($cmd, '--thumb', $thumb);
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['HOME' => sys_get_temp_dir(), 'OMP_NUM_THREADS' => '2']);
        if (!is_resource($proc)) return ['ok' => false, 'error' => 'scanner_unavailable', 'detail' => 'could not start'];
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        $deadline = time() + $timeout;
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            $st = proc_get_status($proc);
            if (!$st['running']) break;
            if (time() > $deadline) {
                proc_terminate($proc, 9);
                return ['ok' => false, 'error' => 'scanner_unavailable', 'detail' => 'timed out'];
            }
            usleep(50000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $lines = array_filter(array_map('trim', explode("\n", $out)));
        $res = json_decode((string) end($lines), true);
        if (!is_array($res)) {
            error_log('Photowall scanner failed: ' . substr($err, -500));
            return ['ok' => false, 'error' => 'scanner_unavailable', 'detail' => substr($err, -300)];
        }
        return $res;
    }

    /**
     * Turns the model scores into a decision: blocked (discard), review (staff must look
     * carefully, shown blurred) or clear. Returns [decision, combined score 0..1].
     */
    public static function safetyDecision(array $safety, float $block, float $review): array
    {
        $engines = $safety['engines'] ?? [];
        if (!$engines) return ['review', 0.0];
        $score = (float) ($safety['score'] ?? 0);
        $explicit = $partial = 0.0;
        foreach ($safety['parts'] ?? [] as $p) {
            if (in_array($p['label'], self::EXPLICIT, true)) $explicit = max($explicit, (float) $p['score']);
            else $partial = max($partial, (float) $p['score']);
        }
        $combined = max($score, $explicit);
        if ($score >= $block || $explicit >= 0.60 || ($partial >= 0.60 && $score >= $review)) return ['blocked', max($combined, $partial)];
        if ($score >= $review || $explicit >= 0.30 || $partial >= 0.50) return ['review', max($combined, $partial)];
        return ['clear', $combined];
    }
}
