# AI 交互结构化 Prompt 记录

> 用途：将 [risk-analysis.md](risk-analysis.md) 的风险识别结论，转化为可复用的结构化 Prompt。
> 在涉及本靶场/安全相关代码的 AI 协作任务中，按需选用对应模板。
> 结构统一为四段：**背景说明 / 任务范围 / 约束条件 / 禁止行为**。

---

## 模板 0：通用安全编码 Prompt（推荐默认使用）

**【背景说明】**
本项目 `漏洞靶场/DVWA/` 是故意设计的教学靶场，包含命令注入、SQL 注入、文件上传、文件包含、XSS、CSRF、弱认证等已知漏洞。我正在基于其结论编写/审查防护代码或分析文档。已知风险详见 docs/risk-analysis.md。

**【任务范围】**
- 仅针对我明确指定的文件/模块进行分析、讲解或修复。
- 输出需引用具体代码位置（文件:行号）。
- 修复方案以各模块 `impossible.php` 的安全实现为参照。

**【约束条件】**
- 所有用户输入（`$_GET/$_POST/$_REQUEST/$_FILES/$_COOKIE`）一律视为不可信。
- SQL 必须参数化；命令必须 `escapeshellarg`/白名单；输出必须 `htmlspecialchars` 编码；文件路径/类型必须白名单校验。
- 涉及敏感操作（执行命令、读写文件、改库、改密、鉴权）时，必须先说明风险再给方案。
- 仅在隔离/本地环境讨论，默认不暴露到公网。

**【禁止行为】**
- 禁止生成可直接用于攻击真实目标的完整 PoC / 武器化 exploit。
- 禁止在修复代码中引入新的 `shell_exec`/`eval`/动态 `include` 等危险调用。
- 禁止绕过、关闭安全机制（如 `header("X-XSS-Protection: 0")`、禁用 CSRF Token）。
- 未经我确认，禁止修改指定范围之外的文件、禁止提交/推送 git。

---

## 模板 1：命令注入分析/修复（exec 模块）

**【背景说明】** [exec/source/low.php](../漏洞靶场/DVWA/vulnerabilities/exec/source/low.php) 使用 `shell_exec('ping '.$target)` 拼接未过滤输入，存在 RCE。

**【任务范围】** 分析注入点 / 给出按 low→impossible 的防护演进 / 输出修复后代码。

**【约束条件】** 输入须做 IP 格式白名单校验，参数须 `escapeshellarg()`；不得拼接执行。

**【禁止行为】** 不输出可用于真实主机的命令注入 payload；不保留任何命令拼接写法。

---

## 模板 2：SQL 注入分析/修复（sqli / brute / sqli_blind）

**【背景说明】** 多处将 `$id`/`$user` 直接拼入 SQL（如 [sqli/source/low.php:10](../漏洞靶场/DVWA/vulnerabilities/sqli/source/low.php#L10)），存在注入与拖库风险。

**【任务范围】** 定位拼接点 / 改写为预编译查询 / 说明数据泄露面。

**【约束条件】** 必须使用 PDO 或 mysqli prepared statement 绑定参数；口令存储须强哈希加盐。

**【禁止行为】** 不提供针对真实数据库的拖库脚本；修复后不得残留字符串拼接 SQL。

---

## 模板 3：文件上传/包含分析（upload / fi）

**【背景说明】** [upload/source/low.php](../漏洞靶场/DVWA/vulnerabilities/upload/source/low.php) 不校验类型即落地可执行目录；[fi/source/low.php](../漏洞靶场/DVWA/vulnerabilities/fi/source/low.php) 包含路径用户可控。

**【任务范围】** 说明 WebShell/LFI/RFI 成因 / 给出白名单校验与目录隔离方案。

**【约束条件】** 扩展名+MIME+内容三重校验、重命名、上传目录禁脚本执行；包含路径用白名单映射。

**【禁止行为】** 不生成 WebShell 样本；不提供路径穿越读取真实系统文件的完整利用链。

---

## 模板 4：XSS / CSRF 分析（xss_r / xss_s / xss_d / csrf）

**【背景说明】** XSS 系列输出未编码（部分主动关闭浏览器防护）；csrf 模块改密无 Token、用 GET。

**【任务范围】** 区分反射/存储/DOM 型成因 / 给出输出编码与 CSRF Token 方案。

**【约束条件】** 输出统一 `htmlspecialchars()`；敏感操作用 POST + 一次性 Token + 校验原密码；启用 CSP。

**【禁止行为】** 不生成可用于真实站点的窃取 Cookie / 钓鱼 payload；不关闭任何安全响应头。

---

## 使用说明

1. 新对话开始时，先粘贴 [constraint-doc.md](constraint-doc.md)（项目级总约束）。
2. 再根据任务选用上面对应模板补充上下文。
3. 交付前对照 [securitychecklist.md](securitychecklist.md) 自检。
