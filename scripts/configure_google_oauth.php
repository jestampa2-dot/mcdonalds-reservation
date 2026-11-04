<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php scripts/configure_google_oauth.php path/to/client_secret.json\n");
    exit(1);
}

$credentialsPath = realpath($argv[1]);

if ($credentialsPath === false || ! is_file($credentialsPath)) {
    fwrite(STDERR, "The OAuth credential JSON file was not found.\n");
    exit(1);
}

$credentials = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
$web = $credentials['web'] ?? null;

if (! is_array($web) || empty($web['client_id']) || empty($web['client_secret'])) {
    fwrite(STDERR, "The JSON file does not contain a Google Web OAuth client.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
$clientId = (string) $web['client_id'];
$clientSecret = (string) $web['client_secret'];
$redirectUri = 'http://127.0.0.1:8000/auth/google/callback';

updateEnvFile($projectRoot.'/.env', [
    'GOOGLE_CLIENT_ID' => $clientId,
    'GOOGLE_CLIENT_SECRET' => $clientSecret,
    'GOOGLE_REDIRECT_URI' => $redirectUri,
]);

updateEnvFile($projectRoot.'/mobile/.env', [
    'EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID' => $clientId,
]);

$easPath = $projectRoot.'/mobile/eas.json';
$eas = json_decode((string) file_get_contents($easPath), true, 512, JSON_THROW_ON_ERROR);

foreach (['preview', 'production'] as $profile) {
    $eas['build'][$profile]['env'] ??= [];
    $eas['build'][$profile]['env']['EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID'] = $clientId;
}

$easWritten = file_put_contents(
    $easPath,
    json_encode($eas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL
);

if ($easWritten === false) {
    throw new RuntimeException('Unable to write '.$easPath);
}

fwrite(STDOUT, "Google OAuth configuration applied without printing the client secret.\n");

/**
 * @param  array<string, string>  $values
 */
function updateEnvFile(string $path, array $values): void
{
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    $contents = rtrim($contents).PHP_EOL;

    foreach ($values as $key => $value) {
        $replacement = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            $contents = (string) preg_replace($pattern, $replacement, $contents, 1);
        } else {
            $contents .= $replacement.PHP_EOL;
        }
    }

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Unable to write '.$path);
    }
}
