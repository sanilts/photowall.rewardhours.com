<?php
declare(strict_types=1);

/** Line icons drawn for Photowall, used inline so they take the colour of the text around them. */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        'person'   => '<circle cx="12" cy="8" r="3.6"/><path d="M4.8 20.2c0-4 3.2-6.7 7.2-6.7s7.2 2.7 7.2 6.7"/>',
        'mountain' => '<path d="M2.8 19.5 9 9.2l3.9 6 2.4-3.4 5.9 7.7z"/><circle cx="17.2" cy="6.2" r="2.1"/>',
        'sofa'     => '<path d="M5.5 11V8.2a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3V11"/><path d="M3 13.2a2 2 0 0 1 4 0v2.3h10v-2.3a2 2 0 0 1 4 0v5.3H3z"/><path d="M6 18.5v2M18 18.5v2"/>',
        'paw'      => '<circle cx="7.4" cy="9.6" r="1.7"/><circle cx="12" cy="7" r="1.7"/><circle cx="16.6" cy="9.6" r="1.7"/><path d="M12 12.2c-2.9 0-5.1 2.2-5.1 4.4 0 1.5 1.2 2.6 2.7 2.6.9 0 1.6-.5 2.4-.5s1.5.5 2.4.5c1.5 0 2.7-1.1 2.7-2.6 0-2.2-2.2-4.4-5.1-4.4z"/>',
        'food'     => '<path d="M6 3v6a2.5 2.5 0 0 0 5 0V3M8.5 3v18"/><path d="M17.2 3c-1.8 1.6-2.7 4.1-2.7 7.2V12h2.7zM17.2 12v9"/>',
        'vase'     => '<path d="M9 3h6M10 3v3.4c-2.5 1.5-4 3.9-4 6.6 0 4 2.7 8 6 8s6-4 6-8c0-2.7-1.5-5.1-4-6.6V3"/><path d="M7 11.5h10"/>',
        'flower'   => '<circle cx="12" cy="12" r="2.4"/><circle cx="12" cy="6.3" r="2.5"/><circle cx="16.9" cy="9.2" r="2.5"/><circle cx="16.9" cy="14.8" r="2.5"/><circle cx="12" cy="17.7" r="2.5"/><circle cx="7.1" cy="14.8" r="2.5"/><circle cx="7.1" cy="9.2" r="2.5"/>',
        'coin'     => '<circle cx="12" cy="12" r="8.6"/><path d="M14.6 9.4c-.5-.9-1.4-1.4-2.6-1.4-1.6 0-2.7.8-2.7 2 0 2.7 5.5 1.3 5.5 4 0 1.2-1.1 2-2.8 2-1.3 0-2.3-.5-2.8-1.5M12 6.2V8M12 16v1.8"/>',
        'camera'   => '<path d="M4 8h3l1.5-2.5h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.2" r="3.6"/>',
        'image'    => '<rect x="3" y="4" width="18" height="16" rx="2.2"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="m21 15.5-4.7-4.7L7.5 19.6"/>',
        'phone'    => '<rect x="7" y="2.5" width="10" height="19" rx="2.6"/><path d="M10.6 5.6h2.8"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2.2"/><path d="m3.6 7.2 8.4 6 8.4-6"/>',
        'upload'   => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16.5V19a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2.5"/>',
        'check'    => '<path d="m5 12.6 4.5 4.5L19 7.5"/>',
        'cross'    => '<path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/>',
        'shield'   => '<path d="m12 3 7.5 3v5.5c0 4.6-3.1 8.2-7.5 9.5-4.4-1.3-7.5-4.9-7.5-9.5V6z"/><path d="m9 12 2.2 2.2 3.9-4.1"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2.2"/><path d="M4 10h16M8 3v4M16 3v4"/>',
        'doc'      => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4M10 12h5M10 16h5"/>',
        'lock'     => '<rect x="5" y="11" width="14" height="9.5" rx="2.2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'pin'      => '<path d="M12 21s6.5-5.6 6.5-11a6.5 6.5 0 0 0-13 0c0 5.4 6.5 11 6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
        'eye'      => '<path d="M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'chevron'  => '<path d="m6 9 6 6 6-6"/>',
        'copies'   => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'focus'    => '<path d="M4 9V5h4M16 5h4v4M20 15v4h-4M8 19H4v-4"/><circle cx="12" cy="12" r="2.6"/>',
        'pencil'   => '<path d="M4 20l1-4.5L16.5 4a1.8 1.8 0 0 1 2.6 0l.9.9a1.8 1.8 0 0 1 0 2.6L8.5 19z"/><path d="M14.5 6l3.5 3.5"/>',
        'refresh'  => '<path d="M20 11.5A8 8 0 0 0 5.3 8M4 4.5V8.5h4"/><path d="M4 12.5A8 8 0 0 0 18.7 16M20 19.5v-4h-4"/>',
        'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];
    $body = $paths[$name] ?? $paths['image'];
    return '<svg class="ico' . ($class !== '' ? ' ' . $class : '') . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $body . '</svg>';
}

/**
 * Picks an icon, a short name and a helpful line for one "what to photograph" entry of a project,
 * by looking for familiar words in it. Anything unfamiliar gets a neutral picture icon.
 * @return array{icon:string, short:string, hint:string, tone:string}
 */
function subject_look(string $label): array
{
    $l = strtolower($label);
    $rules = [
        ['/portrait|people|person|human|face|selfie/', 'person', 'Portraits', 'People at work, at play or at rest. Only people who are happy to be photographed.', 'rose'],
        ['/landscape|outdoor|nature|mountain|street|city|sky/', 'mountain', 'Landscapes', 'Streets, skylines, parks, hills, coastlines. Any time of day.', 'sky'],
        ['/indoor|interior|room|home|office/', 'sofa', 'Indoors', 'Rooms, shops, offices, cafes. Everyday spaces as they are.', 'sand'],
        ['/animal|pet|dog|cat|bird|wildlife/', 'paw', 'Animals', 'Pets at home, birds, street animals, wildlife.', 'mint'],
        ['/food|dining|meal|drink|restaurant|cook/', 'food', 'Food', 'Meals, drinks, markets, kitchens and tables.', 'peach'],
        ['/still|object|product|arrang/', 'vase', 'Still life', 'Objects arranged or found: flowers, tools, shelves, desks.', 'lilac'],
        ['/macro|texture|close|detail|pattern/', 'flower', 'Macro', 'Very close shots of surfaces, fabric, leaves, patterns.', 'lime'],
    ];
    foreach ($rules as [$re, $icon, $short, $hint, $tone]) {
        if (preg_match($re, $l)) return ['icon' => $icon, 'short' => $short, 'hint' => $hint, 'tone' => $tone];
    }
    $words = preg_split('/\s+/', trim($label)) ?: [];
    return ['icon' => 'image', 'short' => implode(' ', array_slice($words, 0, 2)), 'hint' => '', 'tone' => 'sky'];
}
