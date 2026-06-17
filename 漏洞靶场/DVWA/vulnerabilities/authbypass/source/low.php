<?php
/*

[安全修复] 越权访问（IDOR）：访问前校验当前用户权限，仅允许 admin 访问

*/

if (dvwaCurrentUser() != "admin") {
	print "Unauthorised";
	http_response_code(403);
	exit;
}
?>
