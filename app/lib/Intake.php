<?php
declare(strict_types=1);

/** Takes one uploaded file through every check and either stores it or discards it. */
final class Intake
{
    /** What this person may still upload right now. */
    public static function allowance(array $project, array $resp): array
    {
        $now = time();
        $state = 'open';
        if ($project['status'] === 'draft') $state = 'not_open';
        elseif ($project['status'] === 'closed') $state = 'closed';
        elseif ($project['opens_at'] && strtotime($project['opens_at']) > $now) $state = 'not_open';
        elseif ($project['closes_at'] && strtotime($project['closes_at']) < $now) $state = 'closed';
        // A project can close by itself once it has collected the number of photos it set out to get.
        $full = false;
        if ($state === 'open' && (int) ($project['target_photos'] ?? 0) > 0 && self::collected($project) >= (int) $project['target_photos']) {
            $state = 'closed';
            $full = true;
        }

        $used = (int) Db::val("SELECT COUNT(*) FROM photos WHERE respondent_id = ? AND status IN ('pending','approved')", [$resp['id']]);
        $today = (int) Db::val("SELECT COUNT(*) FROM photos WHERE respondent_id = ? AND status <> 'removed' AND created_at >= ?", [$resp['id'], date('Y-m-d 00:00:00')]);
        // 0 means "no limit" for both settings; null below means nothing holds the person back.
        $total = (int) $project['photos_per_person'];
        $left = $total > 0 ? max(0, $total - $used) : null;
        $daily = (int) $project['daily_limit'];
        $todayLeft = $daily > 0 ? max(0, $daily - $today) : null;
        $caps = array_filter([$left, $todayLeft], static fn($v) => $v !== null);
        return [
            'state' => $state, 'full' => $full, 'used' => $used, 'left' => $left, 'today' => $today, 'today_left' => $todayLeft,
            'can' => $state === 'open' && !(int) $resp['blocked'] ? ($caps ? min($caps) : 1000000) : 0,
        ];
    }

    /** Photos that count towards a project's target: accepted, or still waiting for review. */
    public static function collected(array $project): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM photos WHERE project_id = ? AND status IN ('pending','approved')", [$project['id']]);
    }

    /** @return array{ok:bool, code:string, message:string, photo?:array} */
    public static function handle(array $project, array $resp, array $file, string $category = ''): array
    {
        $maxBytes = (int) cfg('max_upload_mb', 60) * 1048576;
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > $maxBytes) {
            return self::no('too_big', 'This file is too large. The limit is ' . cfg('max_upload_mb', 60) . ' MB per photo.');
        }
        if ($err === UPLOAD_ERR_PARTIAL) return self::no('broken', 'The upload was interrupted. Please try this photo again.');
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return self::no('busy', 'We could not receive this file. Please try again.');
        }
        // '' or 'auto': the scanner suggests the category. Otherwise it must be one of the project's own.
        $auto = $category === '' || $category === 'auto';
        if ($auto) $category = '';
        elseif (!in_array($category, project_categories($project), true)) {
            return self::no('category', 'That category is not part of this project. Please choose another.');
        }
        if (!throttle('upload:' . $resp['id'], 1000, 3600)) {
            return self::no('busy', 'Too many uploads in a short time. Please wait a little and try again.');
        }

        Db::q('SELECT GET_LOCK(?, 30)', ['pw_resp_' . $resp['id']]);
        $token = bin2hex(random_bytes(16));
        $tmp = Storage::dir('tmp') . '/' . $token;
        $preview = $tmp . '.preview.jpg';
        $thumb = $tmp . '.thumb.jpg';
        try {
            $allow = self::allowance($project, $resp);
            if ($allow['state'] !== 'open') {
                return self::no('closed', $allow['full'] ? 'This project has collected all the photos it needs and is now closed. Thank you.' : 'This project is not accepting photos right now.');
            }
            if ((int) $resp['blocked']) return self::no('closed', 'Your account cannot upload photos to this project.');
            if ($allow['left'] === 0) return self::no('limit', 'You have already uploaded all ' . $project['photos_per_person'] . ' photos for this project.');
            if ($allow['today_left'] === 0) return self::no('limit', 'You have reached today\'s limit of ' . $project['daily_limit'] . ' photos. Please come back tomorrow.');

            if (!move_uploaded_file((string) $file['tmp_name'], $tmp)) return self::no('busy', 'We could not save this file. Please try again.');
            $name = mb_substr(preg_replace('/[^\w.\- ()]+/u', '_', basename((string) ($file['name'] ?? 'photo'))), 0, 200);
            $bytes = (int) filesize($tmp);
            $sha = (string) hash_file('sha256', $tmp);
            $base = ['orig_name' => $name, 'bytes' => $bytes, 'sha256' => $sha, 'category' => $category];

            if (Db::val("SELECT id FROM photos WHERE project_id = ? AND sha256 = ? AND status <> 'removed' LIMIT 1", [$project['id'], $sha])) {
                return self::discard($project, $resp, $base, [], 'duplicate', 'This photo has already been uploaded to this project.');
            }

            if (PhotoCheck::isCutShort($tmp)) {
                return self::discard($project, $resp, $base, [], 'broken', 'This file is incomplete, so the upload was cut short. Please upload the photo again.');
            }
            $meta = PhotoCheck::readMeta($tmp);
            if ($meta === null) return self::discard($project, $resp, $base, [], 'broken', 'This file could not be read. It may be damaged. Please try the photo again.');
            $facts = PhotoCheck::facts($meta);
            $bad = PhotoCheck::evaluate($meta, $project);
            if ($bad) return self::discard($project, $resp, $base, $meta, $bad[0], $bad[1]);

            $scan = Scanner::run($tmp, $preview, $thumb);
            if (empty($scan['ok'])) {
                if (($scan['error'] ?? '') === 'broken') {
                    return self::discard($project, $resp, $base, $meta, 'broken', 'This file is damaged or incomplete and cannot be opened. Please upload the photo again.');
                }
                return self::no('busy', 'We could not check this photo right now. Please try again in a minute.');
            }
            $bad = PhotoCheck::evaluateSize($facts, (int) $scan['width'], (int) $scan['height'], $project);
            if ($bad) return self::discard($project, $resp, $base, $meta, $bad[0], $bad[1]);
            if ((float) $scan['brightness'] < (float) $project['dark_min']) {
                return self::discard($project, $resp, $base, $meta, 'too_dark', 'This photo is too dark to use. Please take it again with more light.');
            }
            if ((float) $scan['brightness'] > (float) $project['bright_max']) {
                return self::discard($project, $resp, $base, $meta, 'too_bright', 'This photo is washed out (too bright). Please take it again.');
            }
            if ((float) $scan['sharpness'] < (float) $project['blur_min']) {
                return self::discard($project, $resp, $base, $meta, 'blurry', 'This photo is blurry. Hold the phone steady, tap to focus and take it again.');
            }
            if ((int) $project['dup_distance'] >= 0) {
                $twin = Db::val(
                    "SELECT id FROM photos WHERE project_id = ? AND status <> 'removed' AND BIT_COUNT(dhash ^ CAST(CONV(?, 16, 10) AS UNSIGNED)) <= ? LIMIT 1",
                    [$project['id'], $scan['dhash'], (int) $project['dup_distance']]
                );
                if ($twin) return self::discard($project, $resp, $base, $meta, 'duplicate', 'This looks like the same picture as one already uploaded to this project. Please upload a different photo.');
            }
            [$safety, $score] = Scanner::safetyDecision($scan['safety'] ?? [], (float) $project['safety_block'], (float) $project['safety_review']);
            if ($safety === 'blocked') {
                return self::discard($project, $resp, $base, ['safety' => $scan['safety']] + $meta, 'unsafe', 'This photo cannot be accepted because it appears to contain nudity or sexual content.');
            }

            if ($auto) $category = category_for_key($project, $scan['category']['key'] ?? null);
            $ext = ['JPEG' => 'jpg', 'HEIC' => 'heic', 'HEIF' => 'heif'][$facts['file_type']];
            $photo = [
                'project_id' => (int) $project['id'], 'respondent_id' => (int) $resp['id'], 'token' => $token,
                'orig_name' => $name, 'category' => $category, 'category_auto' => $auto ? 1 : 0, 'ext' => $ext, 'mime' => $facts['mime'] ?: 'application/octet-stream',
                'bytes' => $bytes, 'sha256' => $sha, 'crc32' => hexdec((string) hash_file('crc32b', $tmp)),
                'width' => (int) $scan['width'], 'height' => (int) $scan['height'],
                'device_model' => mb_substr($facts['model'], 0, 80), 'lens_model' => mb_substr($facts['lens'], 0, 160),
                'software' => mb_substr($facts['software'], 0, 80), 'taken_at' => $facts['taken_at'],
                'gps_lat' => $facts['gps_lat'], 'gps_lng' => $facts['gps_lng'],
                'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'sharpness' => (float) $scan['sharpness'], 'brightness' => (float) $scan['brightness'],
                'safety_score' => round($score, 4), 'safety_detail' => json_encode($scan['safety'] ?? []),
                'safety' => $safety, 'status' => 'pending', 'created_at' => now(),
            ];
            if (Spaces::on()) {
                $photo['store'] = 'spaces';
                $photo['store_key'] = Spaces::keyFor($photo);
                if (!Spaces::put($tmp, $photo['store_key'], $photo['mime'], $sha)) {
                    error_log('Photowall: upload to the bucket failed: ' . Spaces::$lastError);
                    return self::no('busy', 'We could not save this photo right now. Please try again in a minute.');
                }
            } else {
                if (!rename($tmp, Storage::original($photo))) return self::no('busy', 'We could not save this file. Please try again.');
                @chmod(Storage::original($photo), 0640);
            }
            @rename($preview, Storage::preview($photo));
            @rename($thumb, Storage::thumb($photo));
            $cols = array_keys($photo);
            $photo['id'] = Db::insert(
                'INSERT INTO photos (' . implode(',', $cols) . ', dhash) VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ', CAST(CONV(?, 16, 10) AS UNSIGNED))',
                [...array_values($photo), $scan['dhash']]
            );
            return ['ok' => true, 'code' => 'accepted', 'message' => 'Photo accepted.', 'photo' => $photo];
        } catch (Throwable $ex) {
            error_log('Photowall intake error: ' . $ex->getMessage());
            return self::no('busy', 'Something went wrong while checking this photo. Please try again.');
        } finally {
            foreach ([$tmp, $preview, $thumb] as $f) {
                if (is_file($f)) @unlink($f);
            }
            Db::q('SELECT RELEASE_LOCK(?)', ['pw_resp_' . $resp['id']]);
        }
    }

    private static function no(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message];
    }

    /** The file is thrown away (the caller's cleanup deletes it); only the reason and its metadata are kept. */
    private static function discard(array $project, array $resp, array $base, array $meta, string $code, string $message): array
    {
        Db::q(
            'INSERT INTO rejections (project_id, respondent_id, orig_name, category, bytes, sha256, code, message, device_model, taken_at, meta_json, ip, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $project['id'], $resp['id'], $base['orig_name'], $base['category'] ?? '', $base['bytes'], $base['sha256'], $code, mb_substr($message, 0, 255),
                mb_substr(trim((string) PhotoCheck::tag($meta, 'Make') . ' ' . (string) PhotoCheck::tag($meta, 'Model')), 0, 80),
                $meta ? PhotoCheck::facts($meta)['taken_at'] : null,
                json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), client_ip(), now(),
            ]
        );
        return self::no($code, $message);
    }
}
