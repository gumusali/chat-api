<?php
require __DIR__ . '/vendor/autoload.php';

// connect to db
try {
  $options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  );

  $db = new PDO("sqlite:chat.sqlite", null, null, $options);
} catch(\Exception $e) {
  echo "DB Connection error";
  exit();
}

// create table statements

// $db->exec(`
// CREATE TABLE chats (
//   id INTEGER PRIMARY KEY,
//   user1 INTEGER NOT NULL,
//   user2 INTEGER NOT NULL
// );

// CREATE TABLE messages (
//   id INTEGER PRIMARY KEY,
//   chat_id INTEGER NOT NULL,
//   user_from INTEGER NOT NULL,
//   user_to INTEGER NOT NULL,
//   message TEXT NOT NULL
// );

// CREATE TABLE users (
//   id INTEGER PRIMARY KEY,
//   username TEXT NOT NULL,
//   password TEXT NOT NULL,
//   token TEXT NOT NULL
// );
// `);

// create user statements
// $user1 = array('username' => 'johndoe', 'password' => '12345');
// $user2 = array('username' => 'janedoe', 'password' => '123456');

// $insertStmt = $pdo->prepare("INSERT INTO users (username, password, token) VALUES (:username, :password, '')");
// $insertStmt->bindParam(':username', $user1['username']);
// $insertStmt->bindParam(':password', $user1['password']);
// $insertStmt->execute();
// $insertStmt->bindParam(':username', $user2['username']);
// $insertStmt->bindParam(':password', $user2['password']);
// $insertStmt->execute();

// app
$app = new Slim\App();

$app->post('/login', function($request, $response, $args) use($db) {
  $credentials = json_decode($request->getParam('credentials'));
  $check = $db->prepare("select * from users where username = :user and password = :pass");
  $check->bindParam(":user", $credentials->username);
  $check->bindParam(":pass", $credentials->password);
  $check->execute();
  $fetch = $check->fetchAll();
  
  if(count($fetch) > 0) {
    $uid   = $fetch[0]['id'];
    $token = $uid . bin2hex(random_bytes(32));

    $db->exec("UPDATE users SET token = '{$token}' WHERE id = '{$uid}'");

    echo json_encode(["status" => "success", "token" => $token]);
  } else {
    echo json_encode(["status" => "error", "text" => "Invalid username or password"]);
  }
});

$app->get('/getMessages', function($request, $response, $args) use($db) {
  $token = $request->getParam('token');
  $check = checkToken($token, $db);
  
  if($check !== false) {
    $uid = $check['id'];
    
    // get chats
    try {
      $chats = $db->prepare("SELECT c.*, (
                                SELECT json_group_array(json_object(
                                  'id', m.id,
                                  'chat_id', m.chat_id,
                                  'user_from', m.user_from,
                                  'user_to', m.user_to,
                                  'message', m.message
                                ))
                                FROM messages m
                                WHERE m.chat_id = c.id
                              ) as messages
                              FROM chats c
                              WHERE user1 = '{$uid}' OR user2 = '{$uid}'
                              ");
      $chats->execute();
      $fetchChats = $chats->fetchAll();
    } catch(\Exception $e) {
      echo $e->getMessage();
      exit();
    }

    // get users
    $users = [];

    foreach($fetchChats as $c) {
      $users[] = $c['user1'];
      $users[] = $c['user2'];
    }


    $users = array_unique($users);
    $implode = implode(',', $users);
    $getUsers = $db->prepare("SELECT username, id FROM users WHERE id IN({$implode})");
    $getUsers->execute();
    $fetchUsers = $getUsers->fetchAll();

    // return chats
    echo json_encode(["chats" => $fetchChats, "users" => $fetchUsers]);
  } else {
    // return error
    echo json_encode(["status" => "error", "text" => "Invalid token"]);
  }
});

$app->post('/sendMessage', function($request, $response, $args) use($db) {
  $params = json_decode($request->getParam('params'));
  $check = checkToken($params->token, $db);  
  
  if($check !== false) {
    $uid = $check['id'];

    
    // get chats
    $chats = $db->prepare("SELECT * FROM chats WHERE (user1 = '{$uid}' AND user2 = '{$params->to}') OR (user2 = '{$uid}' AND user1 = '{$params->to}')");
    $chats->execute();
    $fetch = $chats->fetchAll();
    
    if(count($fetch) > 0) {
      // insert message to existing chat
      try {
        $insert = $db->prepare("INSERT INTO messages(chat_id, user_from, user_to, message) VALUES(?, ?, ?, ?)");
        $insert->bindParam(1, $fetch[0]['id']);
        $insert->bindParam(2, $uid);
        $insert->bindParam(3, $params->to);
        $insert->bindParam(4, $params->message);
        $insert->execute();

        echo json_encode(["status" => "success", "lastInsertId" => $db->lastInsertId()]);        
      } catch(\Exception $e) {
        echo $e->getMessage();
        exit();
        echo json_encode(["status" => "error", "text" => "Error!"]);
      }
    } else {
      $createChat = $db->prepare("INSERT INTO chats(user1, user2) VALUES('{$uid}', '{$params->to}')");
      $createChat->execute();
      $chat_id = $db->lastInsertId();

      try {
        $insert = $db->prepare("INSERT INTO messages(chat_id, user_from, user_to, message) VALUES(?, ?, ?, ?)");
        $insert->bindParam(1, $chat_id);
        $insert->bindParam(2, $uid);
        $insert->bindParam(3, $params->to);
        $insert->bindParam(4, $params->message);
        $insert->execute();

        echo json_encode(["status" => "success", "lastInsertId" => $db->lastInsertId()]);        
      } catch(\Exception $e) {
        echo $e->getMessage();
        exit();
        echo json_encode(["status" => "error", "text" => "Error!"]);
      }
    }
  } else {
    // return error
    echo json_encode(["status" => "error", "text" => "Invalid token"]);
  }
});

// run
$app->run();


// helpers
function checkToken($token, $db) {
  $check = $db->prepare("select id, username from users where token = :token");
  $check->bindParam(":token", $token);
  $check->execute();
  $fetch = $check->fetchAll();

  if(count($fetch) > 0) {
    return $fetch[0];
  }
  
  return false;
}