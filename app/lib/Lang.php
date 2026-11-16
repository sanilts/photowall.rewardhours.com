<?php
declare(strict_types=1);

/**
 * Languages of the contributor site. English is the text written in the code; every other language is a
 * file in app/lang that maps the English sentence to its translation. A sentence with no translation is
 * shown in English. The console and the client portal are English only.
 */
final class Lang
{
    /** code => the language's own name, in the order of the picker. The flags are drawn in public/assets/flags.svg. */
    public const ALL = [
        'en' => 'English', 'es' => 'Español', 'pt-BR' => 'Português (Brasil)', 'fr' => 'Français',
        'de' => 'Deutsch', 'it' => 'Italiano', 'nl' => 'Nederlands', 'pl' => 'Polski',
        'sv' => 'Svenska', 'tr' => 'Türkçe', 'ru' => 'Русский', 'ar' => 'العربية',
        'hi' => 'हिन्दी', 'ja' => '日本語', 'ko' => '한국어', 'zh-CN' => '简体中文',
        'zh-TW' => '繁體中文', 'id' => 'Bahasa Indonesia', 'vi' => 'Tiếng Việt', 'th' => 'ไทย',
    ];
    public const COOKIE = 'pw_lang';
    /** The sentence added to several "not accepted" reasons. */
    public const HOW = 'Please choose the original photo from the Photos app on your iPhone. Do not send it through WhatsApp, email or another app first.';

    private static string $code = 'en';
    /** @var array<string,string> */
    private static array $words = [];
    /** @var array<string,array<string,string>> */
    private static array $files = [];

    /** Decides the language of this request: the link (?lang=es), then the saved choice, then the browser's own language. */
    public static function start(string $area): void
    {
        if ($area !== 'respondent') return;
        $asked = $_GET['lang'] ?? null;
        $code = is_string($asked) ? self::match($asked) : null;
        if ($code !== null) {
            if (!headers_sent()) {
                setcookie(self::COOKIE, $code, ['expires' => time() + 31536000, 'path' => '/', 'secure' => (bool) cfg('https', true), 'httponly' => true, 'samesite' => 'Lax']);
            }
        } else {
            $code = self::match((string) ($_COOKIE[self::COOKIE] ?? '')) ?? self::fromBrowser((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        }
        self::use($code ?? 'en');
    }

    /** Switches language, for example before writing an email to someone. */
    public static function use(string $code): void
    {
        $code = self::match($code) ?? 'en';
        self::$code = $code;
        if ($code === 'en') {
            self::$words = [];
            return;
        }
        if (!isset(self::$files[$code])) {
            $file = PW_ROOT . '/app/lang/' . $code . '.php';
            $words = is_file($file) ? require $file : [];
            self::$files[$code] = is_array($words) ? $words : [];
        }
        self::$words = self::$files[$code];
    }

    public static function code(): string
    {
        return self::$code;
    }

    public static function dir(): string
    {
        return self::$code === 'ar' ? 'rtl' : 'ltr';
    }

    /** One of our codes for what a browser or a link asks for ("pt", "zh-hant-tw", "ES"), or null. */
    public static function match(string $tag): ?string
    {
        $tag = strtolower(str_replace('_', '-', trim($tag)));
        if ($tag === '' || strlen($tag) > 20) return null;
        foreach (array_keys(self::ALL) as $code) {
            if (strtolower($code) === $tag) return $code;
        }
        $first = explode('-', $tag)[0];
        if ($first === 'zh') {
            return preg_match('/hant|tw|hk|mo/', $tag) ? 'zh-TW' : 'zh-CN';
        }
        if ($first === 'pt') return 'pt-BR';
        if ($first === 'nb' || $first === 'no' || $first === 'da') return null; // close to Swedish, but not the same language
        return isset(self::ALL[$first]) ? $first : null;
    }

    private static function fromBrowser(string $header): ?string
    {
        $best = null;
        $bestQ = 0.0;
        foreach (array_slice(explode(',', $header), 0, 12) as $part) {
            $bits = explode(';', trim($part));
            $q = 1.0;
            if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $m)) $q = (float) $m[1];
            $code = self::match($bits[0]);
            if ($code !== null && $q > $bestQ) {
                $best = $code;
                $bestQ = $q;
            }
        }
        return $best;
    }

    /** The sentence in the current language. {name} places are filled from $vars. */
    public static function t(string $english, array $vars = []): string
    {
        $out = self::$words[$english] ?? $english;
        if ($vars) {
            $map = [];
            foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string) $v;
            $out = strtr($out, $map);
        }
        return $out;
    }

    /** A date in the reader's language when the server can write it, otherwise in English. */
    public static function date(?string $dt, bool $time = false): string
    {
        if (!$dt) return '';
        if (self::$code !== 'en' && class_exists('IntlDateFormatter')) {
            $f = new IntlDateFormatter(str_replace('-', '_', self::$code), IntlDateFormatter::MEDIUM, $time ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE, date_default_timezone_get(), IntlDateFormatter::GREGORIAN);
            $out = $f->format((int) strtotime($dt));
            if (is_string($out) && $out !== '') return $out;
        }
        return nice_date($dt, $time ? 'j M Y, H:i' : 'j M Y');
    }

    /** A country's name in the reader's language when the server knows it. What is saved stays English. */
    public static function country(string $english): string
    {
        if (self::$code === 'en' || !class_exists('Locale')) return $english;
        $cc = country_code($english);
        if ($cc === '') return $english;
        $name = Locale::getDisplayRegion('-' . $cc, str_replace('-', '_', self::$code));
        return is_string($name) && $name !== '' && $name !== $cc ? $name : $english;
    }

    /**
     * A reason a photo was not accepted, as shown to the contributor. Reasons are saved in English (staff read them
     * in the console); here the known ones are put into the reader's language. Anything else is shown as it is.
     */
    public static function reason(string $english): string
    {
        if (self::$code === 'en' || $english === '') return $english;
        $how = '';
        $main = $english;
        if (str_ends_with($main, ' ' . self::HOW)) {
            $main = substr($main, 0, -strlen(' ' . self::HOW));
            $how = ' ' . self::t(self::HOW);
        }
        if (isset(self::$words[$main])) return self::$words[$main] . $how;
        $shapes = [
            ['/^This photo was taken on (.+)\. Only photos taken on iPhone (\d+) or newer are accepted\.$/su', 'This photo was taken on {model}. Only photos taken on iPhone {min} or newer are accepted.', ['model', 'min']],
            ['/^This file has no camera information, so we cannot confirm it was taken on iPhone (\d+) or newer\.$/', 'This file has no camera information, so we cannot confirm it was taken on iPhone {min} or newer.', ['min']],
            ['/^This photo was saved by "(.+)", so it is not the original camera file\.$/su', 'This photo was saved by "{app}", so it is not the original camera file.', ['app']],
            ['/^This photo is smaller than a full camera photo \(([\d.,]+) megapixels\), so it was resized or cropped\. Please upload the original\.$/', 'This photo is smaller than a full camera photo ({mp} megapixels), so it was resized or cropped. Please upload the original.', ['mp']],
            ['/^This file is too large\. The limit is (\d+) MB per photo\.$/', 'This file is too large. The limit is {mb} MB per photo.', ['mb']],
            ['/^You have already uploaded all (\d+) photos for this project\.$/', 'You have already uploaded all {n} photos for this project.', ['n']],
            ['/^You have reached today\'s limit of (\d+) photos\. Please come back tomorrow\.$/', 'You have reached today\'s limit of {n} photos. Please come back tomorrow.', ['n']],
            ['/^This email address does not seem to exist\. Please check it for typing mistakes\. Did you mean (.+)\?$/u', 'This email address does not seem to exist. Please check it for typing mistakes. Did you mean {hint}?', ['hint']],
        ];
        foreach ($shapes as [$re, $key, $names]) {
            if (preg_match($re, $main, $m)) {
                $vars = [];
                foreach ($names as $i => $n) $vars[$n] = $m[$i + 1];
                return self::t($key, $vars) . $how;
            }
        }
        return $english;
    }
}

/** The sentence in the reader's language. Use e(t(...)) for plain text; t(...) alone when the sentence carries tags. */
function t(string $english, array $vars = []): string
{
    return Lang::t($english, $vars);
}

/** Same, made safe for HTML. */
function te(string $english, array $vars = []): string
{
    return htmlspecialchars(Lang::t($english, $vars), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
