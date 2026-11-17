# TASK-01 设计令牌与对比度验证记录

日期：2026-11-17。范围：public/assets/app.css（原地修改，原件备份于 storage/ui-v2-backup-20261117/）。
方法：WCAG 2.x 相对亮度公式脚本计算（sRGB 线性化），非肉眼判断。目标：常规文字 ≥4.5:1，大字/图形 ≥3:1。

## 变更摘要

- :root 建立语义令牌：surface-0/1/2、border-subtle/strong、text-primary/secondary/tertiary、brand/brand-hover/brand-soft/brand-soft-strong/accent-text、status-{success,info,warning,orange,purple,danger,neutral}-{bg,text}、success/error/info/warning 反馈面、danger、focus-ring、row-hover、scrollbar、backdrop、spacing/radius/control-h 等。
- 旧变量名（--surface、--surface-muted、--border、--text、--text-muted、--accent、--accent-hover、--accent-soft）保留为别名，插件资源 issue-location.css 无需改动即随主题反转。
- body.theme-dark 仅做令牌反转 + 1 条有意差异（输入框底色 #202522 深于面板）；原约 106 行深色覆盖删除至 3 条规则。
- 状态徽章七色统一映射语义令牌，全部带文字标签；字体栈补中文字体（PingFang SC / Microsoft YaHei / Noto Sans SC）。
- 未改任何选择器名、DOM 结构、表单 action/字段 name/权限逻辑。

## 关键配色对比度（计算值）

### 浅色
| 组合 | 比值 | 判定 |
|---|---|---|
| text-primary #202b25 / page #f4f6f4 | 13.49 | AA/AAA |
| text-secondary #49534d / surface-1 #f7f9f7 | 7.55 | AA |
| text-tertiary #5f6b63 / page #f4f6f4 | 5.13 | AA |
| text-tertiary #5f6b63 / surface-2 #eef2ef | 4.93 | AA |
| placeholder #657169 / #fff | 5.10 | AA |
| on-brand #fff / brand #286247 | 7.17 | AA |
| accent-text #286247 / brand-soft #e9f2ec | 6.28 | AA |
| badge green #10714e / #d7f2e4 | 5.07 | AA |
| badge blue #1857ad / #dbeafe | 5.73 | AA（原 #1668dc 4.25 不达标，已加深） |
| badge amber #946205 / #fdf0c7 | 4.61 | AA |
| badge orange #94421a / #ffe4d1 | 5.66 | AA（原 #c05621 3.76 不达标，已加深） |
| badge purple #5646bb / #e9e2ff | 5.62 | AA（原 #6553d8 4.44 不达标，已加深） |
| badge red #a82f22 / #ffdcdd | 5.34 | AA（原 #c0392b 4.28 不达标，已加深） |
| notice success #285d3b / #edf7ef | 7.04 | AA |
| notice error #82443b / #fff1ee | 6.72 | AA |
| danger 按钮 #fff / #a8514a | 5.33 | AA |
| hero eyebrow #c6dbcd / #286247 | 4.92 | AA（原 #bad1c1 4.43，已提亮） |
| hero hint #e3ede6 / #357553（渐变最亮端） | 4.59 | AA（原 #d3e2d7 4.09，已提亮） |
| 原 eyebrow #78877d / page | 3.48 | 不达标 → 改用 text-tertiary 5.13 |
| 原 muted #6b7770 / page | 4.30 | 不达标 → 改用 #5f6b63 5.13 |

### 深色
| 组合 | 比值 | 判定 |
|---|---|---|
| text-primary #e2e5e3 / surface-0 #2a2e2b | 10.86 | AA/AAA |
| text-secondary #c0c8c2 / #2a2e2b | 8.06 | AA |
| text-tertiary #a7aeab / #2a2e2b | 6.09 | AA |
| text-tertiary #a7aeab / surface-2 #303633 | 5.46 | AA |
| placeholder #929b96 / input #202522 | 5.45 | AA |
| on-brand #fff / brand #356b4e | 6.24 | AA（原白字/#4a8964 4.16 不达标，已加深底色） |
| on-brand #fff / brand-hover #3f7f5c | 4.77 | AA |
| accent-text #7db894 / surface-0 #2a2e2b | 6.01 | AA |
| accent-text #7db894 / brand-soft #303b33 | 5.09 | AA |
| brand-strong #b9dfc4 / #303b33 | 8.01 | AA |
| focus-ring #7db894 / page #202321 | 6.92 | ≥3:1 图形要求 |
| danger #f3e7e5 / #7d4a44 | 5.92 | AA |
| 状态徽章深色文字（#82e3b4 等）/ 半透明底 | 沿用原深色值，均 >7 | AA |

### 焦点环
浅色 #3d7a57 / #fff = 5.10、/ page = 4.69（≥3:1）；深色 #7db894 / page = 6.92。原 #7d9787 对浅底约 2.9 不达标，已替换。

## 验证

- CSS 花括号配对 578/578；全部 var() 引用均有定义；无新增 !important。
- php -l views/layout.php 通过；composer test 全部 11 项通过（改动后复跑）。
- 资源指纹 ?v= 由 filemtime+filesize 自动生成，写回即生效，无缓存残留风险。
- 浏览器双主题截图回归：待 TASK-09 统一执行（需登录态）；本任务未做像素级对照，属未覆盖项。

## 有意保留的硬编码色

项目 hero 渐变与快捷入口（始终深绿底白字，双主题一致）、brand-mark 渐变、状态圆点色（伴随文字标签）、阴影 rgb 值。这些不随主题反转，属设计常量。