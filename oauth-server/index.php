<?php

function read_file($filename)
{
    if (!file_exists($filename)) throw new RuntimeException("{$filename} not exists");

    $data = file($filename);
    return array_map(fn($item) => unserialize($item), $data);
}

function write_file($data, $filename)
{
    if (!file_exists($filename)) throw new RuntimeException("{$filename} not exists");

    $data = array_map(fn($item) => serialize($item), $data);
    return file_put_contents($filename, implode(PHP_EOL, $data));
}

function findData($criteria, $filename, $findAll = false)
{
    $apps = read_file($filename);
    $results = array_values(
        array_filter(
            $apps,
            fn($app) => count(array_intersect_assoc($app, $criteria)) === count($criteria)
        )
    );

    if ($findAll) return $results;

    return count($results) === 1 ? $results[0] : null;
}

function findApp($criteria)
{
    return findData($criteria, './data/app.data');
}

function findAllCode($criteria)
{
    return findData($criteria, './data/code.data', true);
}

function findCode($criteria)
{
    return findData($criteria, './data/code.data');
}

function findToken($criteria)
{
    return findData($criteria, './data/token.data');
}

function register()
{
    ["name" => $name] = $_POST;

    if (findApp(["name" => $name])) throw new InvalidArgumentException("{$name} already registered");

    $clientID = uniqid('client_', true);
    $clientSecret = sha1($clientID);

    $apps = read_file('./data/app.data');
    $apps[] = array_merge(
        $_POST,
        ["client_id" => $clientID, "client_secret" => $clientSecret]
    );

    write_file($apps, './data/app.data');

    http_response_code(201);
    echo json_encode(["client_id" => $clientID, "client_secret" => $clientSecret]);
}

function auth()
{
    // Check clientID
    ["client_id" => $clientId, "scope" => $scope, "state" => $state] = $_GET;

    $app = findApp(["client_id" => $clientId]);
    if (!$app) throw new InvalidArgumentException("{$clientId} not exists");

    if (count(findAllCode(["client_id" => $clientId]))) return handleAuth(true);

    // Generate page
    echo "{$app['name']} - {$scope}<br>";
    echo "<a href=\"/auth-Oui?client_id={$clientId}&state={$state}\">Oui</a>&nbsp;";
    echo "<a href=\"/auth-Non?client_id={$clientId}&state={$state}\">Non</a>";
}

function handleAuth($success)
{
    ["client_id" => $clientId, "state" => $state] = $_GET;
    // Get app
    $app = findApp(["client_id" => $clientId]);
    if (!$app) throw new InvalidArgumentException("{$clientId} not exists");

    $queryParams = [
        "state" => $state
    ];

    // success => generate and save auth code
    if ($success) {
        $code = uniqid();
        //save code
        $codes = read_file('./data/code.data');
        $codes[] = [
            "code" => $code,
            "client_id" => $clientId,
            "user_id" => uniqid(),
            "expiredIn" => (new \DateTimeImmutable())->modify("+5 minutes")
        ];
        write_file($codes, './data/code.data');

        $queryParams['code'] = $code;
        $url = $app["redirect_success"];
    } else {
        $url = $app["redirect_error"];
    }

    // Redirect vers
    //      success => redirect_success
    //      error => redirect_error
    header("Location: {$url}?" . http_build_query($queryParams));
}

function handleAuthCode()
{
    ["client_id" => $clientId, "code" => $code] = $_GET;
    // Get Code
    if (!($code = findCode(["code" => $code, "client_id" => $clientId]))) throw new InvalidArgumentException("{$code} not valid for app {$clientId}");
    if ($code["expiredIn"] < new DateTimeImmutable()) throw new InvalidArgumentException("{$code['code']} is expired");

    return uniqid();
}

function handlePassword()
{
    ["username" => $username, "password" => $password] = $_GET;
    // Check in database
    return uniqid();
}

function token()
{
    ["grant_type" => $grantType, "client_id" => $clientId, "client_secret" => $clientSecret] = $_GET;
    // Get app
    if (!findApp(["client_id" => $clientId, "client_secret" => $clientSecret])) throw new InvalidArgumentException("Client credentials not valid");

    //$userId = null;
    //switch($grantType) {
    //    case 'authorization_code':
    //        $userId = handleAuthCode();
    //        break;
    //    case 'password':
    //        $userId = handlePassword();
    //        break;
    //    default:
    //        break;
    //}
    // <==>
    $userId = match ($grantType) {
        'authorization_code' => handleAuthCode(),
        'password' => handlePassword(),
        default => null
    };

    // GENERATE AND SAVE Token
    $token = uniqid();
    $expiredIn = (new \DateTimeImmutable())->modify("+1 day");
    $tokens = read_file('./data/token.data');
    $tokens[] = [
        "token" => $token,
        "user_id" => $userId,
        "expiredIn" => $expiredIn
    ];
    write_file($tokens, './data/token.data');

    echo json_encode([
        "access_token" => $token,
        "expires_in" => $expiredIn->getTimestamp() - (new DateTimeImmutable())->getTimestamp()
    ]);
}

function me()
{
    $authHeader = getallheaders()["Authorization"] ?? '';
    if (!str_starts_with($authHeader, "Bearer ")) throw new \HttpRequestException("Not authorized");
    $token = str_replace('Bearer ', '', $authHeader);
    if (null == ($tokenEntity = findToken(['token' => $token]))) throw new \HttpRequestException("Not authorized");
    echo json_encode([
        "userId" => $tokenEntity["user_id"]
    ]);
}

$route = strtok($_SERVER["REQUEST_URI"], '?');
switch ($route) {
    case '/register':
        register();
        break;
    case '/auth':
        auth();
        break;
    case '/auth-Oui':
        handleAuth(true);
        break;
    case '/auth-Non':
        handleAuth(false);
        break;
    case '/token':
        token();
        break;
    case '/me':
        me();
        break;
    default:
        http_response_code(404);
}
