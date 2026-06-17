# 整改前后对比说明（Before / After Diff）

> 配套：扫描导出 [scan-report.txt](scan-report.txt)（静态特征扫描，32/32 通过）、可复跑脚本 [run-scan.sh](run-scan.sh)
> 基线：整改前 = `git HEAD`；整改后 = 当前工作区
> 说明：本机无 PHP 运行时，验证方式为「git 前后版本静态特征对比 + 人工走查」，动态用例见 [../docs/fix-report.md](../docs/fix-report.md)

---

## 汇总对照表

| # | 模块 | 整改前（危险） | 整改后（控制点） | 扫描结论 |
|---|---|---|---|---|
| 1 | exec 命令注入 | `shell_exec('ping '.$target)` 直接拼接 | IP 八位组 `is_numeric` 白名单 + `checkToken` | PASS |
| 2 | sqli SQL 注入 | `WHERE user_id = '$id'` 拼接 | `intval` + `bindParam(PDO::PARAM_INT)` | PASS |
| 3 | upload 文件上传 | `move_uploaded_file` 不校验 | 扩展名/MIME/`getimagesize` + 重命名 | PASS |
| 4 | fi 文件包含 | `$file=$_GET['page']` 直接 include | `in_array($file,$configFileNames)` 白名单 | PASS |
| 5 | xss_r 反射 XSS | `X-XSS-Protection:0` + 原样输出 | 移除该头 + `htmlspecialchars` | PASS |
| 6 | xss_s 存储 XSS | 仅 SQL 转义即入库 | `htmlspecialchars` + `$db->prepare` | PASS |
| 7 | csrf | 无原密码校验/无 Token | 校验当前密码 + Token + 预编译 | PASS |
| 8 | brute 爆破 | 拼接登录 SQL + 无锁定 | 预编译 + 失败计数/锁定 + 延时 | PASS |
| 9a | authbypass 越权(读) | 仅 high/impossible 鉴权 | 所有等级要求 admin | PASS |
| 9b | authbypass 越权+注入(写) | 拼接 UPDATE 语句 | 全等级 admin + PDO 预编译 | PASS |
| 9c | open_redirect | `header("location:".$_GET)` | `is_numeric` + switch 白名单 | PASS |
| 9d | weak_id 弱会话 | 自增 `last_session_id` | `sha1(mt_rand().time())` + 安全 Cookie | PASS |
| 接线 | 7×index.php | token 字段仅 impossible 渲染 | low 等级也渲染 `tokenField()` | PASS |

---

## 关键代码对比（节选）

### #1 命令注入 exec/source/low.php
```php
// 整改前
$target = $_REQUEST[ 'ip' ];
$cmd = shell_exec( 'ping  ' . $target );          // 任意命令执行
// 整改后
checkToken( $_REQUEST['user_token'], $_SESSION['session_token'], 'index.php' );
$octet = explode( ".", stripslashes($target) );
if( is_numeric($octet[0]) && ... && sizeof($octet)==4 ) {   // 仅允许合法 IP
    $target = $octet[0].'.'.$octet[1].'.'.$octet[2].'.'.$octet[3];
    $cmd = shell_exec( 'ping  ' . $target );
} else { $html .= 'ERROR: invalid IP'; }
```

### #2 SQL 注入 sqli/source/low.php
```php
// 整改前
$query = "SELECT first_name,last_name FROM users WHERE user_id = '$id';";
mysqli_query($GLOBALS["___mysqli_ston"], $query);
// 整改后
if (is_numeric($id)) { $id = intval($id);
  $data = $db->prepare('SELECT first_name,last_name FROM users WHERE user_id=(:id) LIMIT 1;');
  $data->bindParam(':id', $id, PDO::PARAM_INT);  $data->execute();
}
```

### #5 反射型 XSS xss_r/source/low.php
```php
// 整改前
header ("X-XSS-Protection: 0");                    // 关闭浏览器防护
$html .= '<pre>Hello ' . $_GET['name'] . '</pre>'; // 原样输出
// 整改后（不再设置 X-XSS-Protection:0）
$name = htmlspecialchars( $_GET['name'] );
$html .= "<pre>Hello {$name}</pre>";
```

### #9b 越权+SQL 注入 authbypass/change_user_details.php
```php
// 整改前
if (dvwaSecurityLevelGet()=="impossible" && dvwaCurrentUser()!="admin") {...}  // 低/中/高不校验
$query = "UPDATE users SET first_name='".$data->first_name."', last_name='".$data->surname
       ."' where user_id = ".$data->id;                                        // SQL 注入
// 整改后
if (dvwaCurrentUser() != "admin") { print '{"result":"fail","error":"Access denied"}'; exit; }
$stmt = $db->prepare('UPDATE users SET first_name=:first_name, last_name=:surname WHERE user_id=:id');
$stmt->bindValue(':id', (int)$data->id, PDO::PARAM_INT); ... $stmt->execute();
```

> 其余模块（#3/#4/#6/#7/#8/#9a/#9c/#9d）对比见 `git diff HEAD -- 漏洞靶场/DVWA/vulnerabilities`。

---

## 「未引入新问题」回归扫描

对全部整改文件复扫危险写法，期望均为 0：

| 检查项 | 结果 |
|---|---|
| `or die(mysqli_error())` 信息泄露 | 0 ✅ |
| `$_GET/$_POST/$_REQUEST` 直接拼入 SQL/HTML | 0 ✅ |
| `eval(` 调用 | 0 ✅ |
| 残留 `mysqli_query` 拼接查询 | 0 ✅ |
| `shell_exec` 调用 | 2（exec 两个 OS 分支，均在 `is_numeric` 校验之后，非回归）✅ |

结论：原 9 类问题的危险特征均已消除或被控制点取代，未发现新增危险写法。
