# SearchCenter 完整安装包 · 使用说明

本包为「在线查询中心（SearchCenter）」的完整可部署版本，含前台查询模板、现代内核、后台管理系统、IP 防火墙与安装向导，另附初始数据（菜单 / 系统设置 / MAC 地址库 / 软件库 / 驱动库 / 进程库 / 后缀库）。

- 程序版本：1.0.0
- 打包日期：2026-11-22

---

## 一、运行环境

| 项目 | 要求 |
|---|---|
| PHP | 7.2 及以上（推荐 7.3 / 7.4，已在 7.3.4 实测） |
| 扩展 | mysqli、mbstring、json、curl（必需）；openssl（可选，用于加密相关功能） |
| 数据库 | MySQL 5.7 / 8.0（字符集 utf8mb4） |
| Web 服务 | Nginx / Apache 均可，需支持 PHP |
| 目录权限 | 站点根目录与 `kernl/` 可写（安装时写配置与锁文件）；`logs/`、`cache/` 可写 |

php.ini 建议：`upload_max_filesize` 与 `post_max_size` 不小于 32M（后台备份/上传需要）。

---

## 二、部署步骤

1. 把本包全部文件上传到站点根目录（例如 `E:\Web\SearchCenter` 或 `/var/www/searchcenter`）。
2. 确认站点根目录下 **没有** `install.lock`（全新安装首次访问才有向导）。
3. 浏览器访问站点域名或 `http://localhost:88/install/`。
4. 按向导三步走：
   - **环境检查**：确认 PHP 版本与扩展、目录写权限全部“正常”。
   - **安装配置**：填写 MySQL 地址/端口/账号/密码/库名，设置站点名称、后台管理员账号密码，勾选“导入基础数据”。
   - **执行安装**：自动建库建表（17 张表）、导入初始数据、写入 `kernl/config.Class.php`、生成 `install.lock`。
5. 安装完成后：
   - 前台：`http://站点/`
   - 后台：`http://站点/admin/`（账号密码即安装时填写的内容）
   - **建议删除整个 `install` 目录**。

> 重新安装：删除站点根目录的 `install.lock` 后重新访问向导；若目标库已有 `zd_` 开头表，需勾选“重建数据表”（会删除旧表）。

---

## 三、目录结构

```
├── index.php              前台入口（FrontRouter）
├── search.php  search_api.php  api.php  hzsearch.php  hzreport.php
│                          旧入口转发（兼容旧链接）
├── ip_blocked.php         IP 防火墙拦截提示页（未命中规则时不会被访问）
├── install/               安装向导（安装完成后建议删除）
│   ├── index.php          安装向导主程序
│   └── sql/               schema.sql（建表） + data.sql（初始数据）
├── admin/                 后台管理系统
│   ├── index.php          后台入口/路由（?/分组/页面）
│   ├── login.php  login_check.php  code.php
│   └── system/            后台功能页（设置 / 数据库 / 日志 / 信息 / 防火墙 / 查询中心各库）
├── kernl/                 内核
│   ├── config.Class.php   数据库配置（安装时生成）
│   ├── Init.php           初始化（会话/安全头/数据库/模块/IP 库/防火墙）
│   ├── Db.Class.php  Account.class.php  IpFirewall.class.php  HostGuard.class.php
│   ├── cz88_public_v4.czdb / v6.czdb / ipdata.dat     离线 IP 归属地库
│   ├── modules/           System / FileUtil 核心模块
│   └── searchcenter/      查询业务类 SearchCenter.class.php
├── template/searchcenter/ 前台模板（index.html + controller.php + partials + assets）
├── css/ js/ images/ fonts/ plugin/   静态资源
├── logs/                  运行日志（error.log）
└── cache/                 缓存（Bing 壁纸数据等）
```

后台左侧菜单顺序由 `zd_adminlist` 表的 `count` 字段决定（越小越靠前，同组按 `m_e` 兜底排序），本包已按现网顺序固定：

```
系统设置 → 数据库管理 → 系统日志 → 系统信息 → IP 防火墙 → 关于
查询中心设置 → 关键词管理 → MAC 地址库 → 进程信息库 → 后缀名库 → 软件历史库 → 驱动库
```

菜单分组名称来自 `zd_system_setting.system_table`（默认 `系统|查询中心`）。

---

## 四、功能说明

**前台**
- MAC 地址查询（OUI 归属）、IPv4/IPv6 归属地、进程安全等级、文件后缀/进制头、软件历史、驱动查询。
- 首页模板 `template/searchcenter/index.html`，由 `controller.php` 注入配置与列表。
- 支持 `list.php?type=process&kw=xxx&json=1` 返回 JSON。

**后台**
- 查询中心设置：站点标题/副标题/公告/页脚/查询类型开关等。
- 六个数据维护页：MAC 地址库、进程信息库、后缀名库、软件历史库、驱动库、关键词日志。
- 系统设置：基础设置 / 邮件设置 / 上传设置 / 安全防御（已移除“更新与模块”）。
- 数据库管理：表清单与备份。
- IP 防火墙：开关与模式（black/white）、IP 段规则增删、拦截日志；被拦截请求跳转 `ip_blocked.php`（该页固定返回 403）。

---

## 五、数据表清单（17 张）

`zd_adminlist`、`zd_admin_user`、`zd_content_data`、`zd_system_setting`、`zd_system_log`、
`zd_search_log`、`zd_ipfirewall`、`zd_mac_addr`、`zd_softwarelibrary`、`zd_softwaretype`、
`zd_process_list`、`zd_filetypetable`、`zd_device`、`zd_device_name`、`zd_device_library`、
`zd_type`、`zd_report`

初始数据包含：菜单 13 条、系统设置 26 项、内容块 3 条、MAC 地址库（约 3.7 万条）、软件库、驱动库、进程库、后缀库等。

---

## 六、常见问题

1. **访问站点被跳到 `/install/`**：说明 `kernl/config.Class.php` 不存在或数据库配置无效，去向导完成安装即可。
2. **日志出现 `IP库初始化失败: DB is expired`**：离线 cz88 库授权到期，系统会自动回退 `ipdata.dat`，IP 查询仍可用；如需最新库请更新 `cz88_public_v4/v6.czdb`。
3. **后台登录提示密码错误**：登录密码使用可逆加密，密钥取自 `zd_system_setting.encrypted`；若手工改过该值，原密码即失效，可用安装向导重新安装或按同一算法重置。
4. **Nginx 下隐藏敏感文件**：建议加规则禁止下载 `*.sql`、`install.lock`、`logs/`、`install/`：
   ```nginx
   location ~* \.(sql|lock)$ { deny all; }
   location ^~ /install/ { deny all; }
   location ^~ /logs/ { deny all; }
   ```
5. **首页响应慢**：远程数据库或离线 IP 库加载较慢时属正常，可把 MySQL 与应用放同机改善。
