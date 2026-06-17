# 漏洞靶场代码风险分析

> 分析对象：`漏洞靶场/DVWA/`（Damn Vulnerable Web Application）
> 以下列出各漏洞模块涉及的敏感操作及其安全风险。各模块均以 `low.php` 为例，因其最能直观暴露问题。
>
> ⚠️ DVWA 为故意设计的教学靶场，相关风险即其设计目的。请仅在隔离/本地环境运行，切勿暴露到公网。

---

## 1. 系统命令执行（命令注入 / RCE）

**位置**：[exec/source/low.php:5-14](../漏洞靶场/DVWA/vulnerabilities/exec/source/low.php#L5-L14)

```php
$target = $_REQUEST[ 'ip' ];
$cmd = shell_exec( 'ping  ' . $target );
```

- **敏感操作**：使用 `shell_exec()` 直接调用操作系统命令。
- **安全风险**：用户输入 `$target` 未做任何过滤即拼接进命令行。攻击者输入
  `127.0.0.1 & whoami`（Windows）或 `127.0.0.1; cat /etc/passwd`（*nix）
  即可实现**任意命令执行（RCE）**，进而控制服务器、读取敏感文件、横向移动。
- **根因**：危险函数直用 + 输入未校验 + 命令字符串拼接。
- **正确做法**：对输入做白名单/格式校验（如仅允许合法 IP），使用 `escapeshellarg()` 转义参数，避免拼接执行。

---

## 2. 数据库查询（SQL 注入）

**位置**：[sqli/source/low.php:5-11](../漏洞靶场/DVWA/vulnerabilities/sqli/source/low.php#L5-L11)、[brute/source/low.php:12](../漏洞靶场/DVWA/vulnerabilities/brute/source/low.php#L12)、[sqli_blind/source/low.php:11](../漏洞靶场/DVWA/vulnerabilities/sqli_blind/source/low.php#L11)

```php
$id = $_REQUEST[ 'id' ];
$query = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
```

- **敏感操作**：拼接 SQL 语句并查询用户表。
- **安全风险**：输入直接拼入 SQL 且单引号闭合。攻击者可通过
  `' OR '1'='1`、`UNION SELECT ...` 拖库，或布尔/时间盲注（参见 `sqli_blind` 模块）
  导出全部用户名与密码哈希，造成**数据泄露/篡改**。
- **根因**：SQL 字符串拼接 + 输入未参数化。
- **正确做法**：使用预编译/参数化查询（PDO / mysqli prepared statement），对输入做类型校验。

---

## 3. 文件上传（任意文件上传 / WebShell）

**位置**：[upload/source/low.php:5-9](../漏洞靶场/DVWA/vulnerabilities/upload/source/low.php#L5-L9)

```php
$target_path  = DVWA_WEB_PAGE_TO_ROOT . "hackable/uploads/";
$target_path .= basename( $_FILES[ 'uploaded' ][ 'name' ] );
move_uploaded_file( $_FILES['uploaded']['tmp_name'], $target_path );
```

- **敏感操作**：将上传文件写入 Web 可访问目录。
- **安全风险**：完全不校验文件类型、扩展名或内容。攻击者可上传 `shell.php`
  等 **WebShell**，随后访问 `hackable/uploads/shell.php` 即获得**远程代码执行（RCE）**。
- **根因**：上传文件类型未校验 + 落地于可执行目录 + 文件名未重命名。
- **正确做法**：扩展名/MIME 类型白名单校验，校验文件真实内容，重命名文件，
  将上传目录置于 Web 根之外或禁用其脚本执行权限。

---

## 4. 文件包含（LFI / RFI）

**位置**：[fi/source/low.php:4](../漏洞靶场/DVWA/vulnerabilities/fi/source/low.php#L4) → [fi/index.php](../漏洞靶场/DVWA/vulnerabilities/fi/index.php) 中 `include($file)`

```php
$file = $_GET[ 'page' ];
// ... include( $file );
```

- **敏感操作**：使用 `include()` 动态包含用户指定路径的文件。
- **安全风险**：路径完全由用户控制。可通过 `../../../../etc/passwd` 实现
  **本地文件包含（LFI）** 读取任意文件；若 `allow_url_include` 开启则可
  **远程文件包含（RFI）**；配合文件上传或日志投毒可形成 RCE。
- **根因**：包含路径未校验、未限定目录。
- **正确做法**：使用白名单映射允许的页面，禁止用户直接控制路径，关闭 `allow_url_include`。

---

## 5. 反射型 XSS

**位置**：[xss_r/source/low.php:3-8](../漏洞靶场/DVWA/vulnerabilities/xss_r/source/low.php#L3-L8)

```php
header ("X-XSS-Protection: 0");          // 主动关闭浏览器 XSS 防护
$html .= '<pre>Hello ' . $_GET[ 'name' ] . '</pre>';
```

- **敏感操作**：将用户输入直接拼入 HTML 输出，并显式关闭 `X-XSS-Protection`。
- **安全风险**：`?name=<script>...</script>` 即触发**反射型 XSS**，可窃取
  Cookie / 会话、执行钓鱼或浏览器端攻击。
- **根因**：输出未编码 + 主动关闭浏览器防护。
- **正确做法**：使用 `htmlspecialchars()` 对输出做 HTML 实体编码，保留并启用安全响应头与 CSP。

---

## 6. 存储型 XSS

**位置**：[xss_s/source/low.php:9-16](../漏洞靶场/DVWA/vulnerabilities/xss_s/source/low.php#L9-L16)

```php
$message = stripslashes( $message );
$message = mysqli_real_escape_string(..., $message);   // 只做了 SQL 转义
$query = "INSERT INTO guestbook ( comment, name ) VALUES ( '$message', '$name' );";
```

- **敏感操作**：将留言写入数据库并在后续页面回显。
- **安全风险**：仅做了 SQL 转义、**未做 HTML 实体编码**。恶意脚本被持久化，
  所有访问留言板的用户都会中招，属危害更大的**存储型 XSS（可蠕虫化）**。
- **根因**：入库前/输出时未做 HTML 编码。
- **正确做法**：输出时统一 `htmlspecialchars()` 编码，并配合输入校验与 CSP。

---

## 7. 修改账户口令 / CSRF

**位置**：[csrf/source/low.php:3-17](../漏洞靶场/DVWA/vulnerabilities/csrf/source/low.php#L3-L17)

```php
if( isset( $_GET[ 'Change' ] ) ) {
    $pass_new = $_GET[ 'password_new' ];
    // ...
    $insert = "UPDATE `users` SET password = '$pass_new' WHERE user = '$current_user';";
```

- **敏感操作**：通过 GET 请求修改当前用户密码。
- **安全风险**：无 CSRF Token、无原密码校验、且以 GET 传递敏感操作。攻击者
  诱导受害者访问构造好的链接/图片即可**静默改密 → 账户接管（CSRF）**。
- **根因**：缺少反 CSRF 令牌 + 敏感操作用 GET + 未校验原密码。
- **正确做法**：引入一次性 CSRF Token，敏感操作改用 POST，并要求验证当前密码。

---

## 8. 认证 / 弱口令爆破

**位置**：[brute/source/low.php:3-15](../漏洞靶场/DVWA/vulnerabilities/brute/source/low.php#L3-L15)

```php
$user = $_GET[ 'username' ];
$pass = md5( $_GET[ 'password' ] );
$query = "SELECT * FROM `users` WHERE user = '$user' AND password = '$pass';";
```

- **敏感操作**：登录身份校验。
- **安全风险**：无失败次数限制、无验证码、无锁定/延时，口令仅 `md5()`（无盐、
  可彩虹表破解），且同时存在 SQL 注入。可被**暴力破解 / 撞库**。
- **根因**：缺少频率/锁定控制 + 弱哈希 + SQL 拼接。
- **正确做法**：失败计数与锁定/延时、验证码、使用强哈希（bcrypt/argon2 加盐）、参数化查询。

---

## 9. 其他模块（同类敏感操作与风险）

| 模块 | 敏感操作 | 风险 |
|---|---|---|
| `authbypass/`（[change_user_details.php](../漏洞靶场/DVWA/vulnerabilities/authbypass/change_user_details.php)） | 修改/读取用户资料 | **越权 / 不安全直接对象引用（IDOR）**，可改他人资料 |
| `weak_id/` | 生成会话标识 | **可预测的会话 ID**，会话劫持 |
| `open_redirect/` | URL 跳转 | **开放重定向**，钓鱼跳转 |
| `xss_d/` | 客户端 DOM 操作 | **DOM 型 XSS** |
| `csp/` | 内容安全策略配置 | **CSP 配置不当 / JSONP 绕过** |
| `cryptography/` | 加解密 / Token 校验 | **弱加密（ECB、Padding Oracle）、Token 可伪造** |
| `api/` | 接口访问与鉴权 | **接口越权、Token/JWT 校验缺陷** |

---

## 共性根因小结

| 根因 | 体现 |
|---|---|
| 输入未验证/过滤 | 直接使用 `$_GET/$_POST/$_REQUEST` |
| 不安全拼接 | 命令、SQL、HTML、文件路径全靠字符串拼接 |
| 缺少输出编码 | XSS 系列回显未做 HTML 实体编码 |
| 危险函数直用 | `shell_exec`、`include`、`move_uploaded_file` |
| 缺少访问/频率控制 | 无 CSRF Token、无爆破限制、IDOR |
| 弱密码学 | `md5` 无盐、ECB 模式 |

> 各模块的 `impossible.php` 提供了正确的防护实现：参数化查询、白名单校验、
> `htmlspecialchars()` 输出编码、CSRF Token、文件类型白名单 + 重命名、
> `escapeshellarg()` 等，可作为修复参考。
