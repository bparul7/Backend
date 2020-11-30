<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require './vendor/autoload.php';

$app = new \Slim\App;

function make_api_request($path, $access_token){
    $headers[] = 'Authorization: Bearer '.$access_token;
    return make_curl_request($path, false, $headers);
}


function make_curl_request($url, $post = FALSE, $headers = array())
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    }

    $headers[] = 'content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    return $response;
}


function problems_for_tag($tags, $access_token){
    $path = "https://api.codechef.com/tags/problems?filter=";
    for ($i=0; $i<count($tags); $i++) {
        if ($i != 0)
            $path .= ',';
        $path .= $tags[$i];
    }
    $path .= "&limit=100&offset=";
    $off = 0;
    $ans;
    $flg = 0;
    while ($off <= 2000) {
        $mpath = $path.$off;
        $cc = 0;
        $res = make_api_request($mpath, $access_token);
        $fres = json_decode($res);
        if (($fres->status) == "OK") {
            foreach ($fres->result->data->content as $key => $value) {
                $ans[$key]->tags = $value->tags;
                $ans[$key]->solved = $value->solved;
                $ans[$key]->attempted = $value->attempted;
                $ans[$key]->partiallySolved = $value->partiallySolved;
                $flg = 1;
            };
        }
        else {
            if ($flg == 0)
                return $fres;
            break;
        }
        $off += 100;
    }
    return $ans;
    //problemcode : {tags : array, solved : , attempted :, partiallySolved :}
}

function GetProfile ($access_token) {
    $path = "https://api.codechef.com/users/me";
    $res = make_api_request($path, $access_token);
    $fres = json_decode($res);
    if ($fres->status == "error")
        return $res;
    $ans;
    $ans['username'] = $fres->result->data->content->username;
    $ans['fullname'] = $fres->result->data->content->fullname;
    $fans = json_encode($ans);
    return $fans;
}

function AllTags ($access_token) {
    $mpath = "https://api.codechef.com/tags/problems?limit=100&offset=";
    $off = 0;
    $flg = 0;
    $ans;
    while ($off <= 1000) {
        $path = $mpath.$off;
        $response = make_api_request ($path, $access_token);
        $obj = json_decode($response);
        $cc = 0;
        if (($obj->status)=="OK") {
            foreach($obj->result->data->content as $itemkey => $itemvalue) {
                $cc++;
                 if (($itemvalue->type)=="actual_tag") {
                    $ans[$itemkey]->count = $itemvalue->count;
                    $flg = 1;
                }
            }
            if ($cc < 100)
                break;
        }
        else {
            if ($flg == 0)
                return $obj;
            break;
        }
        $off += 100;
    }
    ksort ($ans);
    return $ans;
    //tagname : {count : count}
}

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});


$app->post('/', function (Request $request, Response $response, array $args) {
    $tags = array();
    array_push ($tags, "2-d", "prefix-sum");
    $body = $request->getBody();
    $enbody = json_decode ($body);
    $ftags = ($enbody->tags);
    $headerValue = $request->getHeaderLine('Authorization');
    $access_token = "";
    if (strlen ($headerValue) > 0) {
        $access_token = $headerValue;
    }
    $res = problems_for_tag($ftags, $access_token);
    $fres = json_encode($res);
    $response->getBody()->write("$fres");
    return $response;
});


$app->get('/tags', function (Request $request, Response $response, array $args) {
    $headerValue = $request->getHeaderLine('Authorization');
    $access_token = "";
    if (strlen ($headerValue) > 0) {
        $access_token = $headerValue;
    }
    echo $access_token;
    $res = AllTags ($access_token);
    $fres = json_encode($res);
    $response->getBody()->write("$fres");
    return $response;
});

$app->get('/profile', function (Request $request, Response $response, array $args) {
    $headerValue = $request->getHeaderLine('Authorization');
    $access_token = "";
    if (strlen ($headerValue) > 0) {
        $access_token = $headerValue;
    }
    $res = GetProfile ($access_token);
    $fres = json_encode($res);
    $response->getBody()->write("$fres");
    return $response;
});

$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function($req, $res) {
    $handler = $this->notFoundHandler; // handle using the default Slim page not found handler
    return $handler($req, $res);
});
$app->run();