# 贡献指南

感谢你愿意帮忙。这个插件的核心价值在「**把各种形态的货代报价单正确解析成运费规则**」，所以最有价值的贡献是**报价单格式适配**。

## 最有用的三件事

1. **贴一张脱敏报价单** —— 你手上的货代报价单如果和示例不一样（多级表头、多工作表、特殊附加费、按邮编分区），开个 Issue 贴出来（金额可以改，结构别动）。这比任何功能建议都值钱。
2. **报一个算错的例子** —— 目的国 + 重量 + 尺寸 + 报价单片段 + 期望价 + 实际价。
3. **改文档** —— 安装步骤哪里看不懂，直接说。

## 报 Bug

请包含：

- 插件版本、WordPress 版本、WooCommerce 版本、PHP 版本
- 复现步骤
- **报价单的脱敏片段**（表头 + 报错的那几行）
- 期望结果与实际结果
- 后台「运费试算」的输出（如果算价相关）

用 [Bug 模板](.github/ISSUE_TEMPLATE/bug_report.md) 提交。

## 提 PR

```bash
git clone https://github.com/siyuanzhuang/crossborder-shipping-rates.git
cd crossborder-shipping-rates
git checkout -b fix/import-multilevel-header
```

**提交前必须跑通**：

```bash
php tests/test-engine.php
php tests/test-import.php
php tests/test-views.php
```

新增逻辑请补对应测试：

- 计价相关 → `tests/test-engine.php`
- 解析/编码相关 → `tests/test-import.php`
- 后台模板改动 → `tests/test-views.php`（用最小 WP 桩函数渲染，能抓出未定义变量）

### 代码风格

- 遵循 [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)：**用 Tab 缩进**、Yoda 条件、函数前缀 `cbsr_`、类前缀 `CBSR_`
- 所有输出转义（`esc_html()` / `esc_attr()` / `esc_url()`），所有输入校验
- 表单处理必须带 `check_admin_referer()` 与 `current_user_can()`
- 中文注释可以，但**标识符和提交信息用英文**
- 不引入 Composer 依赖：插件要能直接丢进 `wp-content/plugins/` 跑

### 提交信息

```
feat: support multi-level header in rate sheet import
fix: 0.1kg no longer falls into the next weight tier
docs: clarify HKD exchange rate example
test: cover GBK file with BOM
```

### 分支与版本

- `main` 保持可用，不做长期开发分支
- 版本号遵循语义化版本，改动写进 [CHANGELOG.md](CHANGELOG.md) 的 `[Unreleased]`
- 插件头 `Version:` 与 `CHANGELOG` 的版本号必须一致

## 许可证

提交 PR 即表示你同意以 [GPL-2.0-or-later](LICENSE) 授权你的贡献。
