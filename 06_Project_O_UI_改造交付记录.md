# Project O UI V2 改造 — 交付记录（TASK-00 ～ TASK-10）

日期：2026-11-17。执行方式：按用户指示不建分支、原地改动（v0.01 已备份）。
逐任务原件备份：`storage/ui-v2-backup-20261117/`（13 个文件，文件名 = 相对路径以 __ 连接）。
回滚方法：把备份目录中对应文件复制回原路径即可；新增文件 `views/partials/module_nav.php` 回滚时删除，并还原引用它的 6 个视图。

## 变更文件（11 改 + 1 增）

| 文件 | 任务 | 变更 |
|---|---|---|
| public/assets/app.css | 01/03/04/05/06/07/08 | 语义设计令牌（浅/深双套+旧名别名反转）、组件统一、深色覆盖 106 行→3 条、材料折叠面板/KPI/工作队列/设置分组样式、固定分区注释 |
| public/assets/app.js | 03/04 | 材料录入 details 折叠+localStorage 记忆+新增按钮展开聚焦+编辑反馈自动展开；通用列表筛选扩展 data-list-filter 组合下拉+清空按钮 |
| modules/material-management/views/materials.php | 03 | 录入面板改 details 默认折叠；导出表单移入清单工具栏；action/字段/CSRF/权限不变 |
| modules/document-management/views/issues.php | 04/02 | 统一 toolbar（搜索/状态/严重程度/清空/计数/导出）；行 data-status/data-severity；描述两行截断；状态与严重程度分色徽章；共享导航 |
| modules/document-management/views/documents.php | 05/02 | 紧凑 KPI 条+最近更新（前8）+待处理（各前5）队列；空态入口受权限门控；共享导航 |
| modules/document-management/src/DocumentManagementPlugin.php | 05 | documentsIndex 在既有 listIssues/listRfis 结果上派生 recentActivity/pendingIssues/pendingRfis/canCreate；无新 SQL、无服务层改动 |
| modules/document-management/views/rfis.php | 02 | 共享导航（其余未动） |
| modules/document-management/views/settings.php | 02 | 共享导航（其余未动） |
| modules/material-management/views/_nav.php | 02 | 改共享片段渲染，补当前页高亮+aria-current；权限门控保留 |
| modules/labor-crew/views/_nav.php | 02 | 同上 |
| views/admin.php | 07 | 系统配置拆「平台外观」「区域与本地化」fieldset；单一整页保存不变 |
| views/partials/module_nav.php（新增） | 02 | 共享导航渲染片段；菜单与权限仍由插件声明 |

未改：views/layout.php、projects.php（项目首页仅 CSS 微调）、全部 SQL/迁移/服务层规则/权限/导出字段/状态流转。

## 验证结果

- `composer test`：11/11 通过（改造前基线、每任务后、最终各跑一次，全绿）。
- PHP lint：87 个项目 PHP 文件（除 vendor/storage）全部无语法错误。
- `node --check app.js`：通过。
- CSS：花括号 627/627 配对；var() 引用全部有定义；!important 仅存于原有 prefers-reduced-motion。
- 对比度：脚本计算 WCAG 比值，关键组合全部 ≥4.5:1（详见 05_Project_O_UI_TASK01_对比度记录.md）；修复了原浅色 muted 4.30、eyebrow 3.48、四色徽章 3.76–4.44、深色主按钮 4.16 等不达标项。
- 浏览器实测（默认 Edge，登录态，深色主题，1697px 视口）：
  - 项目首页：hero 收紧、卡片/文字深色正常（修复了别名未反转导致的白底黑字回归）。
  - 材料管理：首屏默认见分类栏+清单；快速录入为折叠条；点「新增材料」展开并聚焦名称框，编码建议 L-11/沿用 L-12 正常；导出在工具栏内。
  - 文档首页：KPI 条（问题2/RFI0）+最近更新 2 条+待处理 2 条，状态徽章与时间正常。
  - 问题管理：搜索/状态/严重程度/清空/计数/导出同一工具栏；状态=已关闭→0 条+空态提示；清空→恢复 2 条；「查看/编辑」弹窗焦点进入关闭按钮，Esc 关闭且焦点返回触发按钮。
  - RFI：空态正常，导航高亮正确。
  - 系统设置：两组 fieldset+说明+单一保存按钮。
  - 六页均无页面级横向滚动。

## 未覆盖 / 未决事项

1. 浅色主题浏览器截图回归未做：当前系统设置为深色，切换主题属业务配置写操作，未经确认不代改。令牌浅色值已脚本验证对比度；建议用户在后台切浅色后目测一轮。
2. 五档精确视口（1440×900/1280×800/1024×768/768×1024/390×844）矩阵未逐一截图；响应式规则沿用原断点并新增小屏规则，桌面实测无溢出。
3. 无 JS 真机验证未做：代码层保留服务端表单 POST 与原生 details/select fallback。
4. 问题位置筛选未实施：行数据无稳定位置值，按任务书「仅在可安全使用时实施」跳过。
5. 文档首页详情入口指向台账页（无单条深链机制，未新增路由）。
6. 浏览器自动化通道（browser-agent）因会话不保持三次停在登录页，改用默认浏览器完成回归；该通道问题与本次改造无关。
7. 工作树含用户在途未提交改动，本次未提交 git；是否提交由用户决定。

## 业务等价证据

- 全部表单 action、字段 name、CSRF、权限变量逐一保留（diff 仅涉及结构/样式/客户端增强）。
- 导出仍走原 POST action 与原字段；问题导出按钮可见性与服务端 requireDocumentAccess 口径一致。
- 文档首页新数据全部来自既有服务列表的内存派生，项目范围与可见性不变。
- 测试断言的导航链接标记逐字兼容（document_management_test 曾捕获格式差异并已修正）。