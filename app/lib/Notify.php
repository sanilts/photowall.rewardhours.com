<?php
declare(strict_types=1);

/**
 * Tells contributors by email which of their photos were accepted.
 * Run every few minutes by cron (bin/notify.php). One email per contributor lists every photo accepted
 * since the last email, and it waits until staff have stopped approving that person's photos for a few
 * minutes, so a review session produces one email and not one per photo.
 */
final class Notify
{
    public const SETTLE = 300;

    /** Returns how many emails were sent. */
    public static function accepted(int $settle = self::SETTLE): int
    {
        $ready = Db::all(
            "SELECT respondent_id FROM photos WHERE status = 'approved' AND notified_at IS NULL
              GROUP BY respondent_id HAVING MAX(COALESCE(reviewed_at, created_at)) <= ?",
            [date('Y-m-d H:i:s', time() - max(0, $settle))]
        );
        $sent = 0;
        foreach ($ready as $row) {
            $who = Db::one('SELECT * FROM respondents WHERE id = ?', [$row['respondent_id']]);
            $project = $who ? Db::one('SELECT * FROM projects WHERE id = ?', [$who['project_id']]) : null;
            $photos = Db::all("SELECT id, orig_name, category, reviewed_at FROM photos WHERE respondent_id = ? AND status = 'approved' AND notified_at IS NULL ORDER BY id", [$row['respondent_id']]);
            if (!$photos) continue;
            $ids = array_column($photos, 'id');
            $marks = rtrim(str_repeat('?,', count($ids)), ',');
            if (!$who || !$project) { // nobody to tell: do not look at these again
                Db::q("UPDATE photos SET notified_at = ? WHERE id IN ($marks)", [now(), ...$ids]);
                continue;
            }
            [$subject, $text, $html] = self::message($project, $who, $photos);
            if (Mailer::send($who['email'], $subject, $text, $html, 'accepted')) {
                Db::q("UPDATE photos SET notified_at = ? WHERE id IN ($marks)", [now(), ...$ids]);
                $sent++;
            } else {
                error_log('Photowall: could not send the accepted-photos email to ' . $who['email'] . ': ' . Mailer::$lastError);
            }
        }
        return $sent;
    }

    /** Subject, plain text and HTML of the email for one contributor. */
    public static function message(array $project, array $who, array $photos): array
    {
        // written in the language the contributor read the site in
        $was = Lang::code();
        Lang::use((string) ($who['lang'] ?? '') !== '' ? (string) $who['lang'] : 'en');
        try {
            return self::write($project, $who, $photos);
        } finally {
            Lang::use($was);
        }
    }

    private static function write(array $project, array $who, array $photos): array
    {
        $n = count($photos);
        $accepted = (int) Db::val("SELECT COUNT(*) FROM photos WHERE respondent_id = ? AND status = 'approved'", [$who['id']]);
        $waiting = (int) Db::val("SELECT COUNT(*) FROM photos WHERE respondent_id = ? AND status = 'pending'", [$who['id']]);
        $reward = reward($project);
        $link = area_url('respondent', '/p/' . $project['slug']);
        $cid = contributor_id($who['id']);
        $hello = trim((string) $who['full_name']) !== '' ? t('Hello {name},', ['name' => trim((string) $who['full_name'])]) : t('Hello,');
        $lead = $n === 1 ? t('A photo you uploaded to "{project}" has been accepted.', ['project' => $project['name']]) : t('{n} photos you uploaded to "{project}" have been accepted.', ['n' => $n, 'project' => $project['name']]);
        $totals = t('Accepted so far: {accepted}. Still in review: {waiting}.', ['accepted' => $accepted, 'waiting' => $waiting]);
        $pay = '';
        if ($reward) {
            $toNext = $reward['every'] - $accepted % $reward['every'];
            $pay = t('Reward earned so far: {amount}.', ['amount' => reward_earned($reward, $accepted)]) . ' '
                . t('We pay {amount} for every {n} accepted photos.', ['amount' => $reward['amount'], 'n' => $reward['every']]) . ' '
                . ($toNext === 1 ? t('1 more accepted photo for the next {amount}.', ['amount' => $reward['amount']]) : t('{n} more accepted photos for the next {amount}.', ['n' => $toNext, 'amount' => $reward['amount']]));
        }
        $subject = ($n === 1 ? t('Your photo was accepted') : t('{n} of your photos were accepted', ['n' => $n])) . ' (' . $project['name'] . ')';

        $text = "$hello\n\n$lead\n\n";
        foreach ($photos as $ph) {
            $text .= image_id($ph['id']) . '  ' . $ph['orig_name'] . '  |  ' . ($ph['category'] !== '' ? t($ph['category']) : t('No category'))
                . '  |  ' . Lang::date($ph['reviewed_at']) . "\n";
        }
        $inv = (Settings::get('invite') ?? []) + ['on' => true, 'title' => 'Join Reward Hours', 'link' => 'https://rewardhours.com'];
        $inv['title'] = t((string) $inv['title']);
        $invText = !empty($inv['on']) && preg_match('~^https://~', (string) $inv['link']) ? $inv['title'] . ': ' . $inv['link'] : '';
        $text .= "\n$totals\n" . ($pay !== '' ? "$pay\n" : '') . "\n" . t('Your contributor ID: {id}', ['id' => $cid]) . "\n" . t('See your photos or add more') . ": $link\n" . ($invText !== '' ? "\n$invText\n" : '') . "\n" . cfg('company', 'Photowall');

        $side = Lang::dir() === 'rtl' ? 'right' : 'left';
        $cell = 'padding:8px 10px;border-bottom:1px solid #d8dfeb;font-size:14px;text-align:' . $side . ';vertical-align:top';
        $rows = '';
        foreach ($photos as $ph) {
            $rows .= '<tr><td style="' . $cell . ';white-space:nowrap;font-weight:bold">' . e(image_id($ph['id'])) . '</td>'
                . '<td style="' . $cell . ';word-break:break-all">' . e($ph['orig_name']) . '</td>'
                . '<td style="' . $cell . '">' . e($ph['category'] !== '' ? t($ph['category']) : t('No category')) . '</td>'
                . '<td style="' . $cell . ';white-space:nowrap">' . e(Lang::date($ph['reviewed_at'])) . '</td></tr>';
        }
        $head = 'padding:8px 10px;font-size:12px;text-align:' . $side . ';color:#52607a;border-bottom:1px solid #d8dfeb';
        $html = '<div' . (Lang::dir() === 'rtl' ? ' dir="rtl"' : '') . ' style="margin:0;padding:24px;background:#f2f5fa;font-family:Arial,Helvetica,sans-serif;color:#0a1e3c">'
            . '<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden">'
            . '<div style="background:#0a1e3c;color:#fffef0;padding:18px 28px;font-size:18px;font-weight:bold">Photowall</div>'
            . '<div style="padding:28px">'
            . '<p style="margin:0 0 10px;font-size:16px">' . e($hello) . '</p>'
            . '<p style="margin:0 0 18px;font-size:16px;line-height:1.5"><strong>' . e($lead) . '</strong></p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 18px">'
            . '<tr><th style="' . $head . '">' . te('Image ID') . '</th><th style="' . $head . '">' . te('File name') . '</th><th style="' . $head . '">' . te('Category') . '</th><th style="' . $head . '">' . te('Accepted') . '</th></tr>'
            . $rows . '</table>'
            . '<p style="margin:0 0 8px;font-size:15px;line-height:1.5">' . e($totals) . '</p>'
            . ($pay !== '' ? '<p style="margin:0 0 8px;font-size:15px;line-height:1.5;background:#fff6d6;border-radius:8px;padding:10px 12px">' . e($pay) . '</p>' : '')
            . '<p style="margin:14px 0 18px;font-size:15px;line-height:1.5">' . t('Your contributor ID: {id}', ['id' => '<strong>' . e($cid) . '</strong>']) . '</p>'
            . '<p style="margin:0"><a href="' . e($link) . '" style="display:inline-block;background:#ffd60a;color:#0a1e3c;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:10px">' . te('See your photos or add more') . '</a></p>'
            . ($invText !== '' ? '<p style="margin:22px 0 0;padding-top:16px;border-top:1px solid #d8dfeb;font-size:14px;line-height:1.5">' . e($inv['title']) . ': <a href="' . e($inv['link']) . '" style="color:#0a1e3c;font-weight:bold">' . e(preg_replace('~^https://~', '', (string) $inv['link'])) . '</a></p>' : '')
            . '</div></div>'
            . '<p style="max-width:600px;margin:14px auto 0;font-size:12px;color:#5a6780;text-align:center">' . e(cfg('company', 'Photowall')) . '</p></div>';
        return [$subject, $text, $html];
    }
}
