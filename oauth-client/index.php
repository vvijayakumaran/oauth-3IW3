<?php

/**
 * "client_id":"client_6070546c6aba63.16480463"
 * "client_secret":"38201ad253c323a79d9108f4588bbc62d2e1a5c6"
 */
const CLIENT_ID = "client_6070546c6aba63.16480463";
const CLIENT_FBID = "280112260228105";
const CLIENT_SECRET = "38201ad253c323a79d9108f4588bbc62d2e1a5c6";
const CLIENT_FBSECRET = "3e5833b07ed52a57c0cad5c745cd1061";

function getUser($params)
{
    $result = file_get_contents("http://oauth-server:8081/token?"
        . "client_id=" . CLIENT_ID
        . "&client_secret=" . CLIENT_SECRET
        . "&" . http_build_query($params));
    $token = json_decode($result, true)["access_token"];
    // GET USER by TOKEN
    $context = stream_context_create([
        'http' => [
            'method' => "GET",
            'header' => "Authorization: Bearer " . $token
        ]
    ]);
    $result = file_get_contents("http://oauth-server:8081/me", false, $context);
    $user = json_decode($result, true);
    var_dump($user);
}

function handleLogin()
{
    echo '<h1>Login with Auth-Code</h1>';
    echo "<a href='http://localhost:8081/auth?"
        . "response_type=code"
        . "&client_id=" . CLIENT_ID
        . "&scope=basic&state=dsdsfsfds'>Login with oauth-server</a>";
    echo "<a href='https://www.facebook.com/v2.10/dialog/oauth?"
        . "response_type=code"
        . "&client_id=" . CLIENT_FBID
        . "&scope=email&state=dsdsfsfds&redirect_uri=http://localhost:https/fbauth-success'>Login with Facebook</a>";
    echo "<a href='https://www.facebook.com/v2.10/dialog/oauth?"
        . "response_type=code"
        . "&client_id=" . CLIENT_FBID
        . "&scope=email&state=dsdsfsfds&redirect_uri=http://localhost:https/fbauth-success'>Login with Facebook</a>";
}

function handleSuccess()
{
    ["code" => $code, "state" => $state] = $_GET;
    // ECHANGE CODE => TOKEN
    getUser([
        "grant_type" => "authorization_code",
        "code" => $code
    ]);
}

function handleFBSuccess()
{
    ["code" => $code, "state" => $state] = $_GET;
    // ECHANGE CODE => TOKEN
    $result = file_get_contents("https://graph.facebook.com/oauth/access_token?"
        . "client_id=" . CLIENT_FBID
        . "&client_secret=" . CLIENT_FBSECRET
        . "&redirect_uri=http://localhost:https/fbauth-success"
        . "&grant_type=authorization_code&code={$code}");
    $token = json_decode($result, true)["access_token"];
    // GET USER by TOKEN
    $context = stream_context_create([
        'http' => [
            'method' => "GET",
            'header' => "Authorization: Bearer " . $token
        ]
    ]);
    $result = file_get_contents("https://graph.facebook.com/me?fields=id,name,email", false, $context);
    $user = json_decode($result, true);
    var_dump($user);
}

function handleError()
{
    echo "refusé";
}

/**
 * AUTH_CODE WORKFLOW
 * => GET Code <- Générer le lien /auth (login)
 * => EXCHANGE Code <> Token (auth-success)
 * => GET USER by Token (auth-success)
 */
$route = strtok($_SERVER["REQUEST_URI"], '?');
switch ($route) {
    case '/login':
        handleLogin();
        break;
    case '/auth-success':
        handleSuccess();
        break;
    case '/fbauth-success':
        handleFBSuccess();
        break;
    case '/auth-error':
        handleError();
        break;
    case '/password':
        if ($_SERVER['REQUEST_METHOD'] === "GET") {
            echo "<form method='POST'>";
            echo "<input name='username'>";
            echo "<input name='password'>";
            echo "<input type='submit' value='Log with oauth'>";
            echo "</form>";
        } else {
            ['username' => $username, 'password' => $password] = $_POST;
            getUser([
                'grant_type' => "password",
                'username' => $username,
                'password' => $password
            ]);
        }
        break;
    default:
        http_response_code(404);
}
