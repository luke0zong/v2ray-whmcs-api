<?php
// require(dirname(dirname(dirname(dirname(__FILE__)))).'/init.php');
require('lib/DB.php');
require('lib/WHMCS.php');
require('config.php');
// use WHMCS\Database\Capsule;

function getUsers(){
    global $Db;
    $users = $Db->get('user');
    $data = [];
    foreach ($users as $user){
        $user['id'] = $user['pid'];
        $user['v2ray_user'] = [
            "uuid" => $user['v2ray_uuid'],
            "email" => sprintf("%s@v2ray.user", $user['v2ray_uuid']),
            "alter_id" => $user['v2ray_alter_id'],
            "level" => $user['v2ray_level'],
        ];
        array_push($data, $user);
    }
    $res = [
        'msg' => 'ok',
        'data' => $data,
    ];

    echo json_encode($res);
}

function addTraffic(){
    global $Db;
    $rate = $_GET['rate'];
    $input = file_get_contents("php://input");
    //file_put_contents('111.txt', json_encode($input));
    $datas = json_decode($input, true);
    foreach ($datas as $data) {
        $user = $Db->where('pid', $data['user_id'])->getOne('user');
        $fetchData = [
            't' => time(),
            'u' => $user['u'] + ($data['u'] * $rate),
            'd' => $user['d'] + ($data['d'] * $rate),
            'enable' => $user['u'] + $user['d'] <= $user['transfer_enable']?1:0
        ];
        $result = $Db->where('pid', $data['user_id'])->update('user', $fetchData);
    }
    
    $res = [
        "ret" => 1,
        "msg" => "ok",
    ];
    
    echo json_encode($res);
}


function getConfig(){
	echo '{
  "api": {
    "services": [
      "HandlerService",
      "StatsService"
    ],
    "tag": "api"
  },
  "stats": {
  },
  "inbound": {
    "port": 443,
    "protocol": "vmess",
    "settings": {
      "clients": []
    },
    "streamSettings": {
      "network": "tcp"
    },
    "tag": "proxy"
  },
  "inboundDetour": [{
    "listen": "0.0.0.0",
    "port": 23333,
    "protocol": "dokodemo-door",
    "settings": {
      "address": "0.0.0.0"
    },
    "tag": "api"
  }],
  "log": {
    "loglevel": "debug",
    "access": "access.log",
    "error": "error.log"
  },
  "outbound": {
    "protocol": "freedom",
    "settings": {}
  },
  "routing": {
    "settings": {
      "rules": [{
        "inboundTag": [
          "api"
        ],
        "outboundTag": "api",
        "type": "field"
      }]
    },
    "strategy": "rules"
  },
  "policy": {
    "levels": {
      "0": {
        "handshake": 4,
        "connIdle": 300,
        "uplinkOnly": 5,
        "downlinkOnly": 30,
        "statsUserUplink": true,
        "statsUserDownlink": true
      }
    }
  }
}
';
}

$databaseName = !empty($_GET['databaseName'])?$_GET['databaseName']:null;
$token = !empty($_GET['token'])?$_GET['token']:null;
$method = !empty($_GET['method'])?$_GET['method']:null;

if(isset($databaseName) && isset($token)){
	$WHMCSdb = new MysqliDb($config['db_hostname'], $config['db_username'], $config['db_password'], $config['db_database'], $config['db_port']);
	$WHMCS = new WHMCS($config['cc_encryption_hash']);
	$server = $WHMCSdb->where('name', $databaseName)->getOne('tblservers');
    if($token !== $server['accesshash']) {
        die('TOKEN ERROR!!');
    }
	$dbhost = $server['ipaddress'] ? $server['ipaddress'] : 'localhost';
	$dbuser = $server['username'];
	$dbpass = $WHMCS->decrypt($server['password']);
	$Db = new MysqliDb($dbhost, $dbuser, $dbpass, $databaseName, 3306);
	switch($_GET['method']) {
	    case 'getUsers': return getUsers();
	    break;
	    case 'addTraffic': return addTraffic();
        break;
        case 'getConfig': return getConfig();
        break;
	}

}else{
	die('Invaild');
}