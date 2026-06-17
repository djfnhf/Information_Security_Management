<?php

// [安全修复] 弱会话标识：使用不可预测的随机值生成会话 ID，并设置安全 Cookie 属性（限定路径/域、过期、Secure、HttpOnly）
$html = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
	$cookie_value = sha1(mt_rand() . time() . "Impossible");
	setcookie("dvwaSession", $cookie_value, time()+3600, "/vulnerabilities/weak_id/", $_SERVER['HTTP_HOST'], true, true);
}
?>
