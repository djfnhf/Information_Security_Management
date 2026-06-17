# 整改报告（Fix Report）

> 范围：[risk-analysis.md](risk-analysis.md) 识别的 9 类问题
> 整改方式：原地修复 `low.php`（参照各模块 `impossible.php` 安全实现）+ 修复连带接线问题
> 验证：静态特征扫描（[../reports/scan-report.txt](../reports/scan-report.txt)，32/32 通过）+ 回归扫描 + 人工走查
> 前后对比：[../reports/before-after-diff.md](../reports/before-after-diff.md)
> 日期：2026-06-17
>
> ⚠️ 验证限制：本机无 PHP 运行时，无法 `php -l` 与动态运行。已用「git 前后版本静态特征比对」
> 替代，并在文末给出需在 DVWA 环境补做的**动态回归用例**。

---

## 一、整改说明（问题 / 改法 / 验收条件）

### #1 命令注入（exec）
- **问题**：`shell_exec('ping '.$target)` 直接拼接 `$_REQUEST['ip']`，可任意命令执行（RCE）。
- **怎么改**：先 `checkToken` 校验 CSRF；将输入按 `.` 切分，要求 4 段且每段 `is_numeric`，
  重新拼回合法 IP 后才执行；非法输入返回错误。
- **验收条件**：输入含 `;`/`&`/`|`/空格等非 IP 字符时拒绝执行；合法 IP 仍能 ping。

### #2 SQL 注入（sqli）
- **问题**：`WHERE user_id = '$id'` 字符串拼接，可注入/拖库。
- **怎么改**：`is_numeric`+`intval` 校验；改用 `$db->prepare` + `bindParam(PDO::PARAM_INT)`（SQLite 同理）。
- **验收条件**：`1' OR '1'='1` 类输入无任何返回；`1` 正常返回单条。

### #3 文件上传（upload）
- **问题**：不校验类型即写入可执行目录，可上传 WebShell（RCE）。
- **怎么改**：扩展名∈{jpg,jpeg,png} + MIME 校验 + 大小<100KB + `getimagesize` + 图像重编码去元数据 + `md5(uniqid())` 重命名。
- **验收条件**：上传 `.php`/非图像被拒；上传合法图片成功且文件名被随机化。

### #4 文件包含（fi）
- **问题**：`include($_GET['page'])` 路径用户可控，导致 LFI/RFI。
- **怎么改**：`in_array($file, 白名单)`，非白名单 `echo error; exit;`。
- **验收条件**：`?page=../../../../etc/passwd` 报 File not found；合法页面正常包含。

### #5 反射型 XSS（xss_r）
- **问题**：主动 `X-XSS-Protection:0` 并原样输出 `$_GET['name']`。
- **怎么改**：移除该响应头；`htmlspecialchars($_GET['name'])` 后输出；加 `checkToken`。
- **验收条件**：`<script>alert(1)</script>` 被转义为文本显示，不执行。

### #6 存储型 XSS（xss_s）
- **问题**：仅做 SQL 转义即入库，回显触发持久化 XSS。
- **怎么改**：`htmlspecialchars` 编码 + `$db->prepare` 参数化插入 + `checkToken`。
- **验收条件**：留言中的脚本被转义存储/显示，不在任何访问者浏览器执行。

### #7 CSRF（csrf 改密）
- **问题**：改密无 Token、无原密码校验、GET 传参，可被诱导改密接管账户。
- **怎么改**：`checkToken`；校验 `password_current`（预编译查询）后才允许改；预编译 UPDATE。
- **验收条件**：缺 Token 或原密码错误时改密失败；跨站构造链接无法静默改密。

### #8 暴力破解（brute）
- **问题**：拼接登录 SQL，无失败锁定，口令 md5 无盐。
- **怎么改**：预编译查询；失败计数 `failed_login`，达 3 次锁定 15 分钟；失败 `sleep(rand(2,4))`；`checkToken`。
- **验收条件**：连续失败触发锁定与延时；正确凭据登录成功并重置计数。

### #9 其他模块
- **9a authbypass 读越权**：鉴权从「仅 high/impossible」改为**所有等级要求 admin**；输出统一 `htmlspecialchars`。
  验收：非 admin 调用 `get_user_data.php` 返回 `Access denied`。
- **9b authbypass 写越权 + SQL 注入**：全等级要求 admin；`UPDATE` 改 PDO 预编译、`user_id` 强制 `(int)`。
  验收：非 admin 无法修改；注入字符串不生效。
- **9c open_redirect**：`is_numeric` + switch 数值白名单映射跳转目标。
  验收：`?redirect=http://evil.com` 不跳转外站；合法编号正常跳转。
- **9d weak_id**：会话值改 `sha1(mt_rand().time())`，Cookie 设过期/路径/域/Secure/HttpOnly。
  验收：会话 ID 不可预测、不可自增推断。

### 连带接线修复（保证「功能实际可用」）
- 7 个模块的 `index.php` 在 **low 等级也渲染 `tokenField()`**（否则新加的 `checkToken` 会拒绝所有提交）。
- `brute/index.php` low 分支增加 `$method='POST'`（修复读 `$_POST` 与 GET 表单不匹配）。
- `csrf/index.php` low 等级渲染「当前密码」输入框（否则改密永远失败）。

---

## 二、整改后验证

### 2.1 静态特征扫描（前后对比）
- 工具：[../reports/run-scan.sh](../reports/run-scan.sh)（git HEAD vs 工作区，逐项核对「危险特征消除 / 控制点新增」）。
- 结果：**通过 32 / 失败 0**，导出见 [../reports/scan-report.txt](../reports/scan-report.txt)。

### 2.2 「未引入新问题」回归扫描
对全部整改文件复扫危险写法，`or die` 信息泄露 / `$_GET` 直接入 SQL/HTML / `eval` / 残留拼接查询
**均为 0**；`shell_exec` 仅剩 2 处（exec 两 OS 分支，均在 `is_numeric` 校验之后）。明细见对比说明。

### 2.3 结论
原 9 类问题的危险特征**均已消除或被安全控制点取代**，静态层面确认原问题消除且未引入新危险写法。

---

## 三、待补：动态回归（建议在 PHP+MySQL 的 DVWA 环境执行）

1. `php -l` 对 20 个改动文件做语法校验。
2. 安全等级设为 **low**，按第一节各「验收条件」逐模块走查通过。
3. 确认所有表单页面正常渲染且可成功提交（验证接线修复 A/B/C）。
4. 复跑成员工具做黑盒回归：
   - `成员代码/DVWA SQL 注入简易扫描工具` 对 sqli 页面应**不再报注入**；
   - `成员代码/DVWA 暴力破解工具` 对 brute 应触发锁定而非枚举成功。
5. 生产化建议（见 [auth-logic-review.md](auth-logic-review.md)）：`display_errors=Off`，对 PDO 调用补 `try/catch`，避免异常泄露。

---

## 四、变更清单（20 个文件）

- **源码修复（11）**：exec/sqli/upload/fi/xss_r/xss_s/csrf/brute/open_redirect/weak_id/authbypass 的 `source/low.php`
- **端点修复（2）**：authbypass `get_user_data.php`、`change_user_details.php`
- **接线修复（7）**：exec/sqli/upload/xss_r/xss_s/csrf/brute 的 `index.php`

> 关联：[risk-analysis.md](risk-analysis.md) · [check-record.md](check-record.md) · [auth-logic-review.md](auth-logic-review.md) · [constraint-doc.md](constraint-doc.md) · [securitychecklist.md](securitychecklist.md)
