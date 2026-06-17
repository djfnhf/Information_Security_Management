<?php
define( 'DVWA_WEB_PAGE_TO_ROOT', '../../' );
require_once DVWA_WEB_PAGE_TO_ROOT . 'dvwa/includes/dvwaPage.inc.php';

dvwaDatabaseConnect();

/*
[安全修复] 越权修改（IDOR）：所有等级均要求 admin 才能修改用户资料
*/

if (dvwaCurrentUser() != "admin") {
	print json_encode (array ("result" => "fail", "error" => "Access denied"));
	exit;
}

if ($_SERVER['REQUEST_METHOD'] != "POST") {
	$result = array (
						"result" => "fail",
						"error" => "Only POST requests are accepted"
					);
	echo json_encode($result);
	exit;
}

try {
	$json = file_get_contents('php://input');
	$data = json_decode($json);
	if (is_null ($data)) {
		$result = array (
							"result" => "fail",
							"error" => 'Invalid format, expecting "{id: {user ID}, first_name: "{first name}", surname: "{surname}"}'

						);
		echo json_encode($result);
		exit;
	}
} catch (Exception $e) {
	$result = array (
						"result" => "fail",
						"error" => 'Invalid format, expecting \"{id: {user ID}, first_name: "{first name}", surname: "{surname}\"}'

					);
	echo json_encode($result);
	exit;
}

// [安全修复] SQL 注入：改用 PDO 预编译参数绑定，user_id 强制为整型
$stmt = $db->prepare( 'UPDATE users SET first_name = :first_name, last_name = :surname WHERE user_id = :id' );
$stmt->bindValue( ':first_name', $data->first_name, PDO::PARAM_STR );
$stmt->bindValue( ':surname', $data->surname, PDO::PARAM_STR );
$stmt->bindValue( ':id', (int)$data->id, PDO::PARAM_INT );
$stmt->execute();

print json_encode (array ("result" => "ok"));
exit;
?>
