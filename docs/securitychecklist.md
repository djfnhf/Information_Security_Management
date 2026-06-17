# 安全自检清单（Security Checklist）

> 用途：AI 或开发者在交付分析结论 / 防护代码前，逐项对照自检。
> 每项来源于 [risk-analysis.md](risk-analysis.md) 的风险结论与 [constraint-doc.md](constraint-doc.md) 的约束。
> 勾选 `[x]` 表示已满足；任一项不满足即不应交付。

---

## A. 输入处理
- [ ] 所有用户输入（`$_GET/$_POST/$_REQUEST/$_FILES/$_COOKIE`）均视为不可信并校验。
- [ ] 对类型/长度/格式做了校验（数值用整型校验，路径/类型用白名单）。
- [ ] 未使用 `$_REQUEST` 之类来源不明确的取值方式处理敏感参数。

## B. 命令执行（对应：命令注入 / RCE）
- [ ] 不存在 `shell_exec/system/exec/passthru/popen` 直接拼接用户输入。
- [ ] 确需执行命令时，参数经 `escapeshellarg()` 且命令经白名单约束。

## C. 数据库（对应：SQL 注入）
- [ ] 所有 SQL 使用预编译 + 参数绑定（PDO / mysqli prepared statement）。
- [ ] 不存在将变量直接拼入 SQL 字符串的写法。
- [ ] 口令使用强哈希（bcrypt/argon2）加盐存储，未使用裸 `md5`。

## D. 文件操作（对应：文件上传 / 文件包含）
- [ ] 上传做扩展名 + MIME + 内容三重白名单校验。
- [ ] 上传文件被重命名，且落地目录禁用脚本执行或位于 Web 根之外。
- [ ] `include/require` 的路径不由用户直接控制，使用白名单映射。
- [ ] 已防止路径穿越（`../`），并关闭 `allow_url_include`。

## E. 输出 / XSS（对应：反射/存储/DOM 型 XSS）
- [ ] 所有回显到 HTML 的数据经 `htmlspecialchars()`（或等价）编码。
- [ ] 未主动关闭浏览器安全机制（如 `X-XSS-Protection: 0`）。
- [ ] 配置了合理的 CSP；DOM 操作未将不可信数据写入 `innerHTML` 等汇聚点。

## F. 会话 / 鉴权 / CSRF（对应：CSRF / 弱认证 / 越权 / 弱会话）
- [ ] 敏感操作使用 POST + 一次性 CSRF Token。
- [ ] 改密等操作校验原密码；操作前校验当前用户对资源的权限（防 IDOR）。
- [ ] 登录有失败次数限制 / 锁定 / 验证码，防暴力破解。
- [ ] 会话 ID 由安全随机源生成，不可预测。

## G. 其他
- [ ] 未引入开放重定向（跳转目标做白名单校验）。
- [ ] 加密使用安全模式（非 ECB），密钥管理得当。
- [ ] 错误信息不泄露堆栈/SQL/路径等敏感细节。

## H. 交付与合规
- [ ] 结论/修复引用了具体代码位置（文件:行号）。
- [ ] 未生成可直接攻击真实目标的武器化 exploit / WebShell。
- [ ] 修改范围与我确认一致；未擅自提交/推送 git。
- [ ] 未建议将靶场或敏感数据暴露到公网。

---

> 配套文档：总约束 [constraint-doc.md](constraint-doc.md)；结构化 Prompt 模板 [prompt-records.md](prompt-records.md)。
