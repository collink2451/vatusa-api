<?php

namespace App\Helpers;

use App\Facility;
use App\Role;
use App\User;

class ULSHelper
{
    public static function generateToken($facility = "") {
        if (!isset($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] == "") {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'REMOTE_ADDR not defined.';
            exit;
        }


        $fac = Facility::find($facility);
        if (!$fac->uls_secret) {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'Secret not available for defined facility.';
            exit;
        }

        $tokenRaw = $fac->uls_secret . "-" . $_SERVER['REMOTE_ADDR'];
        $token = sha1($tokenRaw);
        return $token;
    }

    public static function generatev2Token(User $user, Facility $facility) {
        $data = [
            'iss' => 'VATUSA',
            'aud' => $facility->id,
            'sub' => $user->cid,
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'not available',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 20
        ];
        return static::generatev2Signature($data);
    }

    public static function generatev2Signature(array $data) {
        if ($data === null) return null;
        $signature = hash_hmac('sha256', encode_json($data), env('ULS_SECRET'));
        $data['sig'] = $signature;
        return $data;
    }

    public static function doHandleLogin($cid, $return) {
        //require_once(config('sso.forumapi', ''));
        //smfapi_login($cid, 14400);
        \Auth::loginUsingId($cid);

        $token = [
            "cid" => $cid,
            "nlt" => time() + 7,
            "return" => $return
        ];
        $token = static::base64url_encode(json_encode($token));
        $signature = static::base64url_encode("sha512", $token, base64_decode(env("FORUM_SECRET")));
        return redirect("https://forums.vatusa.net/api.php?login=1&token=$token&signature=$signature");
    }

    public static function base64url_encode($data, $use_padding = false) {
        $encoded = strtr(base64_encode($data), '+/', '-_');
        return true === $use_padding ? $encoded : rtrim($encoded, '=');
    }

    public static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
