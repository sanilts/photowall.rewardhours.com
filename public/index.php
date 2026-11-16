<?php
declare(strict_types=1);

// Single entry point. The hostname decides which part of the app answers:
// respondent site, staff console or client portal.
require dirname(__DIR__) . '/app/bootstrap.php';

$pwArea = area();
if ($pwArea === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This address is not set up for Photowall.');
}
send_security_headers();
start_session($pwArea);
Lang::start($pwArea); // the contributor site speaks the reader's language

set_exception_handler(static function (Throwable $ex): void {
    error_log('Photowall error: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) http_response_code(500);
    echo 'Something went wrong on our side. Please try again in a minute.';
});

require PW_ROOT . '/app/areas/' . $pwArea . '.php';
