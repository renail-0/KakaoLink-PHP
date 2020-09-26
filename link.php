<?php

/**
 *  ----- [KakaoLink PHP Sending Module] --------------
 * |                                                   |
 * |    > @author  renail184                      <    |
 * |    > @version 1.0                            <    |
 * |                                                   |
 * |    > @see     Snoopy HTTP Parser             <    |
 * |    > @see     CryptoJS AES                   <    |
 * |    > @see     Mozila Rhino Engine            <    |
 * |                                                   |
 * |    > @license Eclipse Public License - v 2.0 <    |
 * |                                                   |
 *  ---------------------------------------------------
 */



require_once("class/Snoopy.php");

class Kakao {
    var $ak;
    var $ua;
    var $ka;
    var $cookies = array();

    function __construct($domain) {
        $this->ua = $_SERVER['HTTP_USER_AGENT']; // or set custom settings
        $this->ka = "sdk/1.39.6 os/javascript sdk_type/javascript lang/ko-KR device/Win32 origin/".urlencode($domain);
    }

    function init($ak) {
        if(gettype($ak) != "string" || strlen($ak) != 32) throw new Exception("API key is not vaild.");
        $this->ak = $ak;
        return $this;
    }

    function isInitialized() {
        return !!$this->ak;
    }

    function login($id, $password) {
        if(!$this->isInitialized()) throw new Exception("Login method called before initialize.");
        if(gettype($id) != "string") throw new TypeError("ID is not vaild type.");
        if(gettype($password) != "string") throw new TypeError("Password is not vaild type.");

        $c = new Snoopy;
        $url = "https://sharer.kakao.com/talk/friends/picker/link";
        $param = array(
            "app_key" => $this->ak,
            "validation_action" => "default",
            "validation_params" => "{}",
            "ka" => $this->ka,
            "lcba" => ""
        );
        $c->submit($url, $param);
        $c->setcookies();
        $r = $c->results;
        $status = (int) explode(" ", $c->headers[3])[1];
        if($status === 401) throw new Exception("Invaild API key.");
        if($status !== 200) throw new Exception("An error occured with login.");
        $this->cookies = $c->cookies;
        $aesKey = explode("\"", explode("<input type=\"hidden\" name=\"p\" value=\"", $r)[1])[0];
        $referer = $c->lastredirectaddr;

        $c = new Snoopy;
        $url = "https://track.tiara.kakao.com/queen/footsteps";
        $c->submit($url);
        $c->setcookies();
        $this->cookies["TIARA"] = $c->cookies["TIARA"];

        $c = new Snoopy;
        $url = "https://accounts.kakao.com/weblogin/authenticate.json";
        $c->referer = $referer;
        $c->cookies = $this->cookies;
        $id = shell_exec("scriptRhinoEngine.exe strKeyAes.js str \"$id\" key \"$aesKey\"");
        $password = shell_exec("scriptRhinoEngine.exe strKeyAes.js str \"$password\" key \"$aesKey\"");
        $param = array(
            "os" => "web",
            "webview_v" => "2",
            "email" => $id,
            "password" => $password,
            "continue" => urldecode(explode("continue=", $referer)[1]),
            "third" => "false",
            "k" => "true"
        );
        $c->submit($url, $param);
        $c->setcookies();
        $r = $c->results;
        $status = (int) json_decode($r)->status;
        if($status === -450) throw new Exception("Incorrect ID or password.");
        if($status !== 0) throw new Exception("An error occured with login.");
        $this->cookies = $c->cookies;
        //setcookie("_kawlt", $this->cookies["_kawlt"], time() + 7776000);
        return $this;
    }

    function send($room, $data, $type) {
        //if(empty($this->cookies)) {
        //    if(isset($_COOKIE["_kawlt"])) $this->cookies["_kawlt"] = $_COOKIE["_kawlt"];
        //    else throw new Exception("Send method called before login.");
        //}
        if(!isset($this->cookies["_kawlt"])) throw new Exception("Send method called before login.");
        $this->cookies = array("_kawlt" => $this->cookies["_kawlt"]);
        if(gettype($room) == "string") $room = array($room);
        else if(gettype($room) != "array") throw new TypeError("Room is not vaild type");
        $type = empty($type) ? "default" : $type;
        
        $c = new Snoopy;
        $url = "https://sharer.kakao.com/talk/friends/picker/link";
        $c->cookies = $this->cookies;
        $param = array(
            "app_key" => $this->ak,
            "validation_action" => $type,
            "validation_params" => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "ka" => $this->ka,
            "lcba" => ""
        );
        $c->submit($url, $param);
        $c->setcookies();
        $r = $c->results;
        $status = (int) explode(" ", $c->headers[3])[1];
        if($status < 3300) throw new Exception("Incorrect templete object. If you had other domains, add it at Kakao Developer Settings.");
        $this->cookies = $c->cookies;
        $template = htmlspecialchars_decode(explode("\"", explode("value=\"", $r)[1])[0]);
        $csrf = explode("'", explode("token='", $r)[1])[0];

        $c = new Snoopy;
        $url = "https://sharer.kakao.com/api/talk/chats";
        $c->rawheaders["Csrf-Token"] = $csrf;
        $c->rawheaders["App-Key"] = $this->ak;
        $c->cookies = $this->cookies;
        $c->fetch($url);
        $r = $c->results;
        $this->cookies = $c->cookies;
        $rooms = json_decode($r);

        $key = $rooms->securityKey;
        foreach($rooms->chats as $roominfo) if($roominfo->title == $room[0]) $id = $roominfo->id;
        $ch = curl_init();
        $url = "https://sharer.kakao.com/api/talk/message/link";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "User-Agent: $this->ua",
            "Csrf-Token: $csrf",
            "App-Key: $this->ak",
            "Content-Type: application/json;charset=UTF-8"
        ));
        $cookies = array();
        foreach($this->cookies as $k=>$v) $cookies[] = "$k=$v";
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_COOKIE, join(";", $cookies));
        $param = array(
            "receiverChatRoomMemberCount" => array(1),
            "receiverIds" => array($id),
            "receiverType" => "chat",
            "securityKey" => $key,
            "validatedTalkLink" => json_decode($template)
        );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $r = curl_exec($ch);
        curl_close($ch);
        if(!isset($id)) throw new Exception("Invaild room name.");
        array_shift($room);
        if(count($room)) return $this->send($room, $data, $type);
        else return true;
    }
}

?>
