<?php

// [安全修复] 开放重定向：跳转目标使用数值白名单映射，禁止用户直接控制跳转 URL
$target = "";

if (array_key_exists ("redirect", $_GET) && is_numeric($_GET['redirect'])) {
	switch (intval ($_GET['redirect'])) {
		case 1:
			$target = "info.php?id=1";
			break;
		case 2:
			$target = "info.php?id=2";
			break;
		case 99:
			$target = "https://digi.ninja";
			break;
	}
	if ($target != "") {
		header ("location: " . $target);
		exit;
	} else {
		?>
		Unknown redirect target.
		<?php
		exit;
	}
}

?>
Missing redirect target.
