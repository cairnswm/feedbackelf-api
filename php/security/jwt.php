<?php
include "base64url.php";
include_once "security.config.php";
include_once __DIR__ . "/../getguid.php";

$jwtError = array();

function jwt_header()
{
    return [
        "alg" => "HS256",
        "typ" => "JWT"
    ];
}

function jwt_set_secret($secret)
{
    global $SecretKey;
    $SecretKey = $secret;
}


function jwt_secret()
{
    global $SecretKey, $JWTSECRET;
    if (!isset($SecretKey)) {
        $SecretKey = $JWTSECRET;
    }
    return $SecretKey;
}

$jwtpayload = array();
function jwt_set_payload($payload, $config = NULL)
{
    make_payload($payload, $config);
}

function jwt_payload()
{
    global $jwtpayload;
    return $jwtpayload;
}



function make_payload($payload, $config = NULL)
{
    if ($config == NULL) {
        global $defaultConfig;
        $config = $defaultConfig;
    }
    ;
    array_key_exists("issuer", $config) ? $iss = $config["issuer"] : $iss = "";
    array_key_exists("subject", $config) ? $sub = $config["subject"] : $sub = "";
    array_key_exists("audience", $config) ? $aud = $config["audience"] : $aud = "";
    $datetime = new DateTime();
    $nbf = $datetime->getTimestamp();
    $iat = $datetime->getTimestamp();
    if (array_key_exists("expiryperiod", $config)) {
        $exp = $datetime->getTimestamp() + $config["expiryperiod"];
    } else {
        $exp = $datetime->getTimestamp() + 60480000; // 2 years

    }
    $jti = getGUID();

    global $jwtpayload;
    $jwtpayload = array("iss" => $iss, "sub" => $sub, "aud" => $aud, "exp" => $exp, "nbf" => $nbf, "iat" => $iat, "jti" => $jti, "data" => $payload);
    return $jwtpayload;
}

function jwt_token()
{
    $header = base64url_encode(json_encode(jwt_header()));
    $payload = base64url_encode(json_encode(jwt_payload()));
    $secret = jwt_secret();
    $raw = $header . "." . $payload;
    $signature = hash_hmac("sha256", $raw, $secret);
    $jwt_token = $raw . "." . base64url_encode($signature);
    return $jwt_token;
}

function jwt_error()
{
    global $jwtError;
    return $jwtError;
}

function validate_jwt($token, $time = false, $aud = null)
{
    global $jwtError;
    $jwtError = array();
    $section = explode('.', $token);
    $secret = jwt_secret();

    if (count($section) < 3) {
        $jwtError[] = "Invalid token format";
        return false;
    }

    $header = $section[0];
    $payload = $section[1];
    $tokensignature = $section[2];

    $raw = $header . "." . $payload;
    $signature = base64url_encode(hash_hmac("sha256", $raw, $secret));

    if ($signature === $tokensignature) {
        if ($time) {
            $decodedPayload = json_decode(base64url_decode($payload));
            if ($decodedPayload && isset($decodedPayload->exp)) {
                $now = new DateTime();
                if ($decodedPayload->exp < $now->getTimestamp()) {
                    $jwtError[] = "Token has expired";
                    return false;
                }
            } else {
                $jwtError[] = "Invalid payload structure";
                return false;
            }
        }
        if ($aud !== null) {
            $decodedPayload = json_decode(base64url_decode($payload));
            if ($decodedPayload && isset($decodedPayload->aud)) {
                if ($decodedPayload->aud !== $aud) {
                    $jwtError[] = "Invalid Audience, {$decodedPayload->aud}, not {$aud} expected";
                    return false;
                }
            } else {
                $jwtError[] = "Invalid payload structure";
                return false;
            }
        }
        return true;
    } else {
        $jwtError[] = "Signature does not match";
        return false;
    }
}

function get_jwt_payload($token)
{
    $section = explode('.', $token);
    $payload = base64url_decode($section[1]);
    return json_decode($payload);
}