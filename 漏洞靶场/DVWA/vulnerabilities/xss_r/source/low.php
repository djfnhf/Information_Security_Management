<?php

// [安全修复] 反射型 XSS：输出 HTML 实体编码（htmlspecialchars）+ Anti-CSRF Token；不再关闭浏览器 XSS 防护
// Is there any input?
if( array_key_exists( "name", $_GET ) && $_GET[ 'name' ] != NULL ) {
	// Check Anti-CSRF token
	checkToken( $_REQUEST[ 'user_token' ], $_SESSION[ 'session_token' ], 'index.php' );

	// Get input
	$name = htmlspecialchars( $_GET[ 'name' ] );

	// Feedback for end user
	$html .= "<pre>Hello {$name}</pre>";
}

// Generate Anti-CSRF token
generateSessionToken();

?>
